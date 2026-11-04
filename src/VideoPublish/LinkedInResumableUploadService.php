<?php
declare(strict_types=1);

/**
 * LinkedIn: initializeUpload → Chunks → finalize → wait AVAILABLE → Posts create.
 * CRM-Scheduling über payload.scheduled_at (kein natives LI-Schedule).
 */
final class LinkedInResumableUploadService
{
    public const MAX_ATTEMPTS = 10;
    public const MIN_BYTES = 75_000; // LinkedIn Feed-Minimum
    public const MAX_BYTES = 524_288_000; // 500 MB Feed; Hosting-Limit oft niedriger

    /**
     * @return array{ok: bool, message: string, done: bool}
     */
    public static function processJob(int $jobId): array
    {
        $job = PublishJobRepository::find($jobId);
        if ($job === null) {
            return ['ok' => false, 'message' => 'Job nicht gefunden.', 'done' => true];
        }
        if ((string) ($job['platform'] ?? '') !== PublishJobRepository::PLATFORM_LINKEDIN) {
            return ['ok' => false, 'message' => 'Kein LinkedIn-Job.', 'done' => true];
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

            $access = LinkedInTokenService::accessToken();
            $owner = LinkedInPublishSettings::organizationUrn();
            if ($owner === '') {
                throw new RuntimeException('Organization-ID fehlt in den LinkedIn-Publish-Einstellungen.');
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

            $phase = (string) ($payload['li_phase'] ?? 'init');
            $instructions = is_array($payload['upload_instructions'] ?? null) ? $payload['upload_instructions'] : [];
            $partIds = is_array($payload['uploaded_part_ids'] ?? null) ? $payload['uploaded_part_ids'] : [];
            $partIndex = (int) ($job['upload_offset'] ?? 0);
            $videoUrn = trim((string) ($job['upload_session_url'] ?? ($payload['video_urn'] ?? '')));
            $uploadToken = (string) ($payload['upload_token'] ?? '');

            if ($phase === 'init' || $videoUrn === '' || $instructions === []) {
                $init = LinkedInApiClient::initializeUpload($access, $owner, $fileSize);
                $payload['li_phase'] = 'parts';
                $payload['video_urn'] = $init['video'];
                $payload['upload_token'] = $init['upload_token'];
                $payload['upload_instructions'] = $init['upload_instructions'];
                $payload['uploaded_part_ids'] = [];
                PublishJobRepository::update($jobId, [
                    'state' => PublishJobRepository::STATE_UPLOADING,
                    'upload_session_url' => $init['video'],
                    'upload_offset' => 0,
                    'attempts' => (int) ($job['attempts'] ?? 0) + 1,
                    'last_error' => '',
                    'payload_json' => json_encode($payload, JSON_UNESCAPED_UNICODE),
                ]);

                return [
                    'ok' => true,
                    'message' => 'Upload initialisiert (' . count($init['upload_instructions']) . ' Teile).',
                    'done' => false,
                ];
            }

            if ($phase === 'parts') {
                if ($partIndex >= count($instructions)) {
                    $payload['li_phase'] = 'finalize';
                    PublishJobRepository::update($jobId, [
                        'payload_json' => json_encode($payload, JSON_UNESCAPED_UNICODE),
                    ]);

                    return ['ok' => true, 'message' => 'Alle Chunks fertig — Finalize.', 'done' => false];
                }
                $part = $instructions[$partIndex];
                if (!is_array($part)) {
                    throw new RuntimeException('Upload-Anweisung ungültig.');
                }
                $etag = LinkedInApiClient::uploadPart(
                    (string) ($part['uploadUrl'] ?? ''),
                    $path,
                    (int) ($part['firstByte'] ?? 0),
                    (int) ($part['lastByte'] ?? 0)
                );
                $partIds[] = $etag;
                $payload['uploaded_part_ids'] = $partIds;
                $next = $partIndex + 1;
                $lastByte = (int) ($part['lastByte'] ?? 0);
                if ($next >= count($instructions)) {
                    $payload['li_phase'] = 'finalize';
                }
                PublishJobRepository::update($jobId, [
                    'state' => PublishJobRepository::STATE_UPLOADING,
                    'upload_offset' => $next,
                    'last_error' => '',
                    'payload_json' => json_encode($payload, JSON_UNESCAPED_UNICODE),
                ]);

                return [
                    'ok' => true,
                    'message' => 'Chunk ' . $next . '/' . count($instructions)
                        . ' (' . min($fileSize, $lastByte + 1) . '/' . $fileSize . ').',
                    'done' => false,
                ];
            }

            if ($phase === 'finalize') {
                LinkedInApiClient::finalizeUpload($access, $videoUrn, $uploadToken, array_map('strval', $partIds));
                $payload['li_phase'] = 'wait_available';
                PublishJobRepository::update($jobId, [
                    'last_error' => '',
                    'payload_json' => json_encode($payload, JSON_UNESCAPED_UNICODE),
                ]);

                return ['ok' => true, 'message' => 'Finalize OK — warte auf AVAILABLE.', 'done' => false];
            }

            if ($phase === 'wait_available') {
                $info = LinkedInApiClient::getVideo($access, $videoUrn);
                $status = strtoupper($info['status']);
                if ($status === 'PROCESSING_FAILED') {
                    throw new RuntimeException('LinkedIn Video-Verarbeitung fehlgeschlagen.');
                }
                if ($status !== 'AVAILABLE') {
                    return [
                        'ok' => true,
                        'message' => 'Video-Status: ' . ($status !== '' ? $status : 'unbekannt') . ' — warte.',
                        'done' => false,
                    ];
                }
                $payload['li_phase'] = 'create_post';
                PublishJobRepository::update($jobId, [
                    'payload_json' => json_encode($payload, JSON_UNESCAPED_UNICODE),
                ]);

                return ['ok' => true, 'message' => 'Video AVAILABLE — erstelle Post.', 'done' => false];
            }

            if ($phase === 'create_post') {
                $title = trim((string) ($payload['title'] ?? 'Video'));
                $commentary = (string) ($payload['commentary'] ?? $payload['description'] ?? $title);
                $postId = LinkedInApiClient::createVideoPost($access, $owner, $videoUrn, $commentary, $title);
                $payload['li_phase'] = 'done';
                $payload['post_urn'] = $postId;
                PublishJobRepository::update($jobId, [
                    'state' => PublishJobRepository::STATE_PUBLISHED,
                    'external_id' => $postId,
                    'upload_offset' => $fileSize,
                    'last_error' => '',
                    'payload_json' => json_encode($payload, JSON_UNESCAPED_UNICODE),
                ]);
                PublishPostRepository::upsertFromJob($jobId, [
                    'platform' => PublishJobRepository::PLATFORM_LINKEDIN,
                    'external_id' => $postId,
                    'title' => $title,
                    'description' => $commentary,
                    'privacy' => 'public',
                    'published_at' => date('Y-m-d H:i:s'),
                    'editable_flags' => 'commentary,delete',
                ]);

                return ['ok' => true, 'message' => 'Veröffentlicht: ' . $postId, 'done' => true];
            }

            throw new RuntimeException('Unbekannte LinkedIn-Phase: ' . $phase);
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
                        'li_phase' => 'init',
                        'upload_instructions' => [],
                        'uploaded_part_ids' => [],
                    ]), JSON_UNESCAPED_UNICODE),
            ]);
            error_log('DG CRM LinkedInResumableUploadService #' . $jobId . ': ' . $e->getMessage());

            return ['ok' => false, 'message' => $e->getMessage(), 'done' => $failed];
        }
    }

    public static function updatePublished(int $jobId, array $fields): void
    {
        $job = PublishJobRepository::find($jobId);
        if ($job === null || (string) ($job['platform'] ?? '') !== PublishJobRepository::PLATFORM_LINKEDIN) {
            throw new InvalidArgumentException('LinkedIn-Job nicht gefunden.');
        }
        $postUrn = (string) ($job['external_id'] ?? '');
        if ($postUrn === '') {
            throw new InvalidArgumentException('Kein LinkedIn-Post verknüpft.');
        }
        $access = LinkedInTokenService::accessToken();
        $post = PublishPostRepository::findByJob($jobId);
        $commentary = (string) ($fields['commentary'] ?? $fields['description'] ?? ($post['description'] ?? ''));
        LinkedInApiClient::updatePostCommentary($access, $postUrn, $commentary);
        if ($post !== null) {
            PublishPostRepository::update((int) $post['id'], [
                'description' => $commentary,
            ]);
        }
        $payload = PublishJobRepository::payload($job);
        $payload['commentary'] = $commentary;
        $payload['description'] = $commentary;
        PublishJobRepository::update($jobId, [
            'payload_json' => json_encode($payload, JSON_UNESCAPED_UNICODE),
        ]);
    }

    public static function deletePublished(int $jobId): void
    {
        $job = PublishJobRepository::find($jobId);
        if ($job === null || (string) ($job['platform'] ?? '') !== PublishJobRepository::PLATFORM_LINKEDIN) {
            throw new InvalidArgumentException('LinkedIn-Job nicht gefunden.');
        }
        $postUrn = (string) ($job['external_id'] ?? '');
        if ($postUrn === '') {
            throw new InvalidArgumentException('Kein LinkedIn-Post verknüpft.');
        }
        $access = LinkedInTokenService::accessToken();
        LinkedInApiClient::deletePost($access, $postUrn);
        $post = PublishPostRepository::findByJob($jobId);
        if ($post !== null) {
            PublishPostRepository::delete((int) $post['id']);
        }
        PublishJobRepository::update($jobId, [
            'external_id' => '',
            'state' => PublishJobRepository::STATE_FAILED,
            'last_error' => 'Auf LinkedIn gelöscht.',
        ]);
    }

    private static function assertAssetAllowed(string $path, int $fileSize, string $mime): void
    {
        if ($fileSize < self::MIN_BYTES) {
            throw new RuntimeException('LinkedIn: Video zu klein (min. 75 KB).');
        }
        $hostMax = VideoAssetStorage::MAX_BYTES;
        if ($fileSize > min(self::MAX_BYTES, $hostMax)) {
            throw new RuntimeException('LinkedIn: Video zu groß für dieses Hosting (max. 100 MB MVP).');
        }
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        if ($ext !== 'mp4' && !str_contains(strtolower($mime), 'mp4')) {
            throw new RuntimeException('LinkedIn akzeptiert nur MP4.');
        }
    }
}
