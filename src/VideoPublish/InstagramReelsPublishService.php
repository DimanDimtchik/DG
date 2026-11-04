<?php
declare(strict_types=1);

/**
 * Instagram Reels: Container → (rupload | video_url) → Status FINISHED → media_publish.
 * CRM-Scheduling über payload.scheduled_at.
 */
final class InstagramReelsPublishService
{
    public const MAX_ATTEMPTS = 12;
    public const MIN_BYTES = 1024;
    public const MAX_BYTES = 314_572_800; // 300 MB API; Hosting oft 100 MB

    /**
     * @return array{ok: bool, message: string, done: bool}
     */
    public static function processJob(int $jobId): array
    {
        $job = PublishJobRepository::find($jobId);
        if ($job === null) {
            return ['ok' => false, 'message' => 'Job nicht gefunden.', 'done' => true];
        }
        if ((string) ($job['platform'] ?? '') !== PublishJobRepository::PLATFORM_INSTAGRAM) {
            return ['ok' => false, 'message' => 'Kein Instagram-Job.', 'done' => true];
        }
        $state = (string) ($job['state'] ?? '');
        if ($state === PublishJobRepository::STATE_PUBLISHED) {
            return ['ok' => true, 'message' => 'Bereits veröffentlicht.', 'done' => true];
        }
        if ($state === PublishJobRepository::STATE_FAILED && (int) ($job['attempts'] ?? 0) >= self::MAX_ATTEMPTS) {
            return ['ok' => false, 'message' => 'Max. Versuche erreicht.', 'done' => true];
        }

        try {
            $payload = PublishJobRepository::payload($job);
            $scheduled = trim((string) ($payload['scheduled_at'] ?? ''));
            if ($scheduled !== '') {
                $ts = strtotime($scheduled);
                if ($ts !== false && $ts > time()) {
                    return [
                        'ok' => true,
                        'message' => 'Geplant für ' . date('Y-m-d H:i', $ts) . ' — warte.',
                        'done' => false,
                    ];
                }
            }

            $access = InstagramTokenService::accessToken();
            $conn = OAuthConnectionRepository::findByPlatform(OAuthConnectionRepository::PLATFORM_INSTAGRAM);
            $igUserId = (string) ($conn['account_id'] ?? '');
            if ($igUserId === '') {
                throw new RuntimeException('Instagram User-ID fehlt — erneut verbinden.');
            }

            $stored = (string) ($job['stored_name'] ?? '');
            $path = VideoAssetStorage::absolutePath($stored);
            $fileSize = (int) ($job['size_bytes'] ?? 0);
            if ($fileSize < 1 && is_file($path)) {
                $fileSize = (int) filesize($path);
            }
            if (!is_file($path) || $fileSize < 1) {
                throw new RuntimeException('Videodatei fehlt.');
            }
            self::assertAssetAllowed($path, $fileSize, (string) ($job['mime_type'] ?? ''));

            $phase = (string) ($payload['ig_phase'] ?? 'init');
            $caption = (string) ($payload['caption'] ?? $payload['description'] ?? $payload['title'] ?? '');
            $shareToFeed = !isset($payload['share_to_feed']) || !empty($payload['share_to_feed']);
            $loginPath = (string) (InstagramPublishSettings::config()['login_path'] ?? InstagramPublishSettings::LOGIN_FACEBOOK);
            $useResumable = $loginPath === InstagramPublishSettings::LOGIN_FACEBOOK;

            if ($phase === 'init') {
                InstagramApiClient::assertPublishQuota($access, $igUserId);
                if ($useResumable) {
                    $created = InstagramApiClient::createReelsResumableContainer(
                        $access,
                        $igUserId,
                        $caption,
                        $shareToFeed
                    );
                    $payload['ig_phase'] = 'upload';
                    $payload['container_id'] = $created['container_id'];
                    $payload['upload_uri'] = $created['upload_uri'];
                    $payload['upload_mode'] = 'resumable';
                } else {
                    $videoUrl = VideoAssetStorage::signedPublicUrl($stored, 7200);
                    $created = InstagramApiClient::createReelsUrlContainer(
                        $access,
                        $igUserId,
                        $videoUrl,
                        $caption,
                        $shareToFeed
                    );
                    $payload['ig_phase'] = 'wait';
                    $payload['container_id'] = $created['container_id'];
                    $payload['upload_mode'] = 'video_url';
                    $payload['video_url'] = $videoUrl;
                }
                PublishJobRepository::update($jobId, [
                    'state' => PublishJobRepository::STATE_UPLOADING,
                    'upload_session_url' => (string) $payload['container_id'],
                    'upload_offset' => 0,
                    'attempts' => (int) ($job['attempts'] ?? 0) + 1,
                    'last_error' => '',
                    'payload_json' => json_encode($payload, JSON_UNESCAPED_UNICODE),
                ]);

                return [
                    'ok' => true,
                    'message' => $useResumable
                        ? 'Container erstellt — lade Datei hoch.'
                        : 'Container mit video_url erstellt — warte auf Verarbeitung.',
                    'done' => false,
                ];
            }

            $containerId = (string) ($payload['container_id'] ?? $job['upload_session_url'] ?? '');
            if ($containerId === '') {
                throw new RuntimeException('Container-ID fehlt.');
            }

            if ($phase === 'upload') {
                $uri = (string) ($payload['upload_uri'] ?? '');
                if ($uri === '') {
                    throw new RuntimeException('Upload-URI fehlt.');
                }
                InstagramApiClient::uploadResumableFile($uri, $access, $path, $fileSize);
                $payload['ig_phase'] = 'wait';
                PublishJobRepository::update($jobId, [
                    'upload_offset' => $fileSize,
                    'payload_json' => json_encode($payload, JSON_UNESCAPED_UNICODE),
                ]);

                return ['ok' => true, 'message' => 'Upload fertig — warte auf FINISHED.', 'done' => false];
            }

            if ($phase === 'wait') {
                $status = InstagramApiClient::getContainerStatus($access, $containerId);
                $code = strtoupper((string) ($status['status_code'] ?? ''));
                if ($code === 'ERROR' || $code === 'EXPIRED') {
                    throw new RuntimeException(
                        'Instagram Container ' . $code
                        . ($status['status'] !== '' ? ': ' . $status['status'] : '')
                    );
                }
                if ($code !== 'FINISHED') {
                    return [
                        'ok' => true,
                        'message' => 'Container-Status: ' . ($code !== '' ? $code : 'IN_PROGRESS') . ' — warte.',
                        'done' => false,
                    ];
                }
                $payload['ig_phase'] = 'publish';
                PublishJobRepository::update($jobId, [
                    'payload_json' => json_encode($payload, JSON_UNESCAPED_UNICODE),
                ]);

                return ['ok' => true, 'message' => 'Container FINISHED — veröffentliche.', 'done' => false];
            }

            if ($phase === 'publish') {
                $mediaId = InstagramApiClient::publishContainer($access, $igUserId, $containerId);
                $payload['ig_phase'] = 'done';
                $payload['media_id'] = $mediaId;
                $title = trim((string) ($payload['title'] ?? 'Reel'));
                PublishJobRepository::update($jobId, [
                    'state' => PublishJobRepository::STATE_PUBLISHED,
                    'external_id' => $mediaId,
                    'upload_offset' => $fileSize,
                    'last_error' => '',
                    'payload_json' => json_encode($payload, JSON_UNESCAPED_UNICODE),
                ]);
                $editable = $useResumable ? 'delete' : '';
                PublishPostRepository::upsertFromJob($jobId, [
                    'platform' => PublishJobRepository::PLATFORM_INSTAGRAM,
                    'external_id' => $mediaId,
                    'title' => $title,
                    'description' => $caption,
                    'privacy' => 'public',
                    'published_at' => date('Y-m-d H:i:s'),
                    'editable_flags' => $editable,
                ]);

                return ['ok' => true, 'message' => 'Veröffentlicht: ' . $mediaId, 'done' => true];
            }

            throw new RuntimeException('Unbekannte Instagram-Phase: ' . $phase);
        } catch (Throwable $e) {
            $attempts = (int) ($job['attempts'] ?? 0) + 1;
            $failed = $attempts >= self::MAX_ATTEMPTS;
            PublishJobRepository::update($jobId, [
                'state' => $failed ? PublishJobRepository::STATE_FAILED : PublishJobRepository::STATE_PENDING,
                'attempts' => $attempts,
                'last_error' => mb_substr($e->getMessage(), 0, 1000),
                'upload_session_url' => $failed ? (string) ($job['upload_session_url'] ?? '') : '',
                'upload_offset' => $failed ? (int) ($job['upload_offset'] ?? 0) : 0,
                'payload_json' => $failed
                    ? (string) ($job['payload_json'] ?? '')
                    : json_encode(array_merge(PublishJobRepository::payload($job), [
                        'ig_phase' => 'init',
                        'container_id' => '',
                        'upload_uri' => '',
                    ]), JSON_UNESCAPED_UNICODE),
            ]);
            error_log('DG CRM InstagramReelsPublishService #' . $jobId . ': ' . $e->getMessage());

            return ['ok' => false, 'message' => $e->getMessage(), 'done' => $failed];
        }
    }

    public static function deletePublished(int $jobId): void
    {
        $job = PublishJobRepository::find($jobId);
        if ($job === null || (string) ($job['platform'] ?? '') !== PublishJobRepository::PLATFORM_INSTAGRAM) {
            throw new InvalidArgumentException('Instagram-Job nicht gefunden.');
        }
        $loginPath = (string) (InstagramPublishSettings::config()['login_path'] ?? '');
        if ($loginPath !== InstagramPublishSettings::LOGIN_FACEBOOK) {
            throw new RuntimeException('Löschen nur mit Facebook-Login-Pfad möglich.');
        }
        $mediaId = (string) ($job['external_id'] ?? '');
        if ($mediaId === '') {
            throw new InvalidArgumentException('Keine Instagram-Media-ID verknüpft.');
        }
        $access = InstagramTokenService::accessToken();
        InstagramApiClient::deleteMedia($access, $mediaId);
        $post = PublishPostRepository::findByJob($jobId);
        if ($post !== null) {
            PublishPostRepository::delete((int) $post['id']);
        }
        PublishJobRepository::update($jobId, [
            'external_id' => '',
            'state' => PublishJobRepository::STATE_FAILED,
            'last_error' => 'Auf Instagram gelöscht.',
        ]);
    }

    private static function assertAssetAllowed(string $path, int $fileSize, string $mime): void
    {
        if ($fileSize < self::MIN_BYTES) {
            throw new RuntimeException('Instagram: Video zu klein.');
        }
        $hostMax = VideoAssetStorage::MAX_BYTES;
        if ($fileSize > min(self::MAX_BYTES, $hostMax)) {
            throw new RuntimeException('Instagram: Video zu groß für dieses Hosting (max. 100 MB MVP).');
        }
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $okExt = in_array($ext, ['mp4', 'mov', 'm4v'], true);
        $okMime = str_contains(strtolower($mime), 'mp4')
            || str_contains(strtolower($mime), 'quicktime');
        if (!$okExt && !$okMime) {
            throw new RuntimeException('Instagram Reels: MP4 oder MOV erforderlich.');
        }
    }
}
