<?php
declare(strict_types=1);

/**
 * TikTok: Creator-Info → Inbox/Direct init → FILE_UPLOAD Chunks → Status.
 * Inbox = Draft in App; Direct nur mit audit_ok.
 */
final class TikTokPublishService
{
    public const MAX_ATTEMPTS = 12;
    public const MIN_BYTES = 1024;
    public const MAX_BYTES = 4_294_967_296; // API 4 GB; Hosting oft 100 MB

    /**
     * @return array{ok: bool, message: string, done: bool}
     */
    public static function processJob(int $jobId): array
    {
        $job = PublishJobRepository::find($jobId);
        if ($job === null) {
            return ['ok' => false, 'message' => 'Job nicht gefunden.', 'done' => true];
        }
        if ((string) ($job['platform'] ?? '') !== PublishJobRepository::PLATFORM_TIKTOK) {
            return ['ok' => false, 'message' => 'Kein TikTok-Job.', 'done' => true];
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

            $access = TikTokTokenService::accessToken();
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

            $phase = (string) ($payload['tt_phase'] ?? 'init');
            $cfg = TikTokPublishSettings::config();
            $mode = (string) ($cfg['posting_mode'] ?? TikTokPublishSettings::MODE_INBOX);

            if ($phase === 'init') {
                $creator = TikTokApiClient::queryCreatorInfo($access);
                $payload['creator_info'] = [
                    'username' => $creator['creator_username'],
                    'nickname' => $creator['creator_nickname'],
                    'max_video_post_duration_sec' => $creator['max_video_post_duration_sec'],
                    'privacy_level_options' => $creator['privacy_level_options'],
                ];
                $plan = TikTokApiClient::chunkPlan($fileSize);
                $payload['chunk_size'] = $plan['chunk_size'];
                $payload['total_chunk_count'] = $plan['total_chunk_count'];
                $payload['chunk_index'] = 0;
                $payload['posting_mode'] = $mode;

                if ($mode === TikTokPublishSettings::MODE_DIRECT) {
                    $privacy = trim((string) ($payload['privacy_level'] ?? ''));
                    if ($privacy === '') {
                        throw new RuntimeException('Privacy-Stufe wählen (keine Vorbelegung).');
                    }
                    $opts = $creator['privacy_level_options'];
                    if ($opts !== [] && !in_array($privacy, $opts, true)) {
                        throw new RuntimeException(
                            'Privacy „' . $privacy . '“ ist für dieses Konto nicht erlaubt.'
                        );
                    }
                    $title = mb_substr((string) ($payload['title'] ?? $payload['caption'] ?? ''), 0, 2200);
                    $postInfo = [
                        'privacy_level' => $privacy,
                        'title' => $title,
                        'disable_duet' => !empty($payload['disable_duet']) || $creator['duet_disabled'],
                        'disable_stitch' => !empty($payload['disable_stitch']) || $creator['stitch_disabled'],
                        'disable_comment' => !empty($payload['disable_comment']) || $creator['comment_disabled'],
                        'brand_content_toggle' => !empty($payload['brand_content_toggle']),
                        'brand_organic_toggle' => !empty($payload['brand_organic_toggle']),
                    ];
                    if (!empty($payload['is_aigc'])) {
                        $postInfo['is_aigc'] = true;
                    }
                    $init = TikTokApiClient::initDirectPost(
                        $access,
                        $postInfo,
                        $fileSize,
                        $plan['chunk_size'],
                        $plan['total_chunk_count']
                    );
                } else {
                    $init = TikTokApiClient::initInboxUpload(
                        $access,
                        $fileSize,
                        $plan['chunk_size'],
                        $plan['total_chunk_count']
                    );
                }

                $payload['tt_phase'] = 'upload';
                $payload['publish_id'] = $init['publish_id'];
                $payload['upload_url'] = $init['upload_url'];
                PublishJobRepository::update($jobId, [
                    'state' => PublishJobRepository::STATE_UPLOADING,
                    'upload_session_url' => $init['publish_id'],
                    'upload_offset' => 0,
                    'attempts' => (int) ($job['attempts'] ?? 0) + 1,
                    'last_error' => '',
                    'payload_json' => json_encode($payload, JSON_UNESCAPED_UNICODE),
                ]);

                return [
                    'ok' => true,
                    'message' => ($mode === TikTokPublishSettings::MODE_DIRECT
                        ? 'Direct-Init OK — lade Datei hoch.'
                        : 'Inbox-Init OK — lade Datei hoch.'),
                    'done' => false,
                ];
            }

            $publishId = (string) ($payload['publish_id'] ?? $job['upload_session_url'] ?? '');
            $uploadUrl = (string) ($payload['upload_url'] ?? '');
            $chunkSize = (int) ($payload['chunk_size'] ?? 0);
            $totalChunks = (int) ($payload['total_chunk_count'] ?? 0);
            $chunkIndex = (int) ($payload['chunk_index'] ?? 0);

            if ($phase === 'upload') {
                if ($publishId === '' || $uploadUrl === '' || $chunkSize < 1 || $totalChunks < 1) {
                    throw new RuntimeException('Upload-Session unvollständig — Job neu starten.');
                }
                if ($chunkIndex >= $totalChunks) {
                    $payload['tt_phase'] = 'wait';
                    PublishJobRepository::update($jobId, [
                        'payload_json' => json_encode($payload, JSON_UNESCAPED_UNICODE),
                    ]);

                    return ['ok' => true, 'message' => 'Chunks fertig — warte auf TikTok-Status.', 'done' => false];
                }
                $offset = $chunkIndex * $chunkSize;
                if ($offset >= $fileSize) {
                    $payload['tt_phase'] = 'wait';
                    $payload['chunk_index'] = $totalChunks;
                    PublishJobRepository::update($jobId, [
                        'upload_offset' => $fileSize,
                        'payload_json' => json_encode($payload, JSON_UNESCAPED_UNICODE),
                    ]);

                    return ['ok' => true, 'message' => 'Upload komplett — Status prüfen.', 'done' => false];
                }
                $length = min($chunkSize, $fileSize - $offset);
                $ctype = TikTokApiClient::contentTypeForPath($path, (string) ($job['mime_type'] ?? ''));
                TikTokApiClient::uploadChunk($uploadUrl, $path, $offset, $length, $fileSize, $ctype);
                $chunkIndex++;
                $payload['chunk_index'] = $chunkIndex;
                $newOffset = min($fileSize, $offset + $length);
                if ($chunkIndex >= $totalChunks) {
                    $payload['tt_phase'] = 'wait';
                }
                PublishJobRepository::update($jobId, [
                    'state' => PublishJobRepository::STATE_UPLOADING,
                    'upload_offset' => $newOffset,
                    'last_error' => '',
                    'payload_json' => json_encode($payload, JSON_UNESCAPED_UNICODE),
                ]);

                return [
                    'ok' => true,
                    'message' => 'Chunk ' . $chunkIndex . '/' . $totalChunks
                        . ' (' . $newOffset . '/' . $fileSize . ').',
                    'done' => false,
                ];
            }

            if ($phase === 'wait') {
                if ($publishId === '') {
                    throw new RuntimeException('publish_id fehlt.');
                }
                $st = TikTokApiClient::fetchPublishStatus($access, $publishId);
                $status = $st['status'];
                if ($status === 'FAILED') {
                    $reason = $st['fail_reason'] !== '' ? $st['fail_reason'] : 'unbekannt';
                    throw new RuntimeException('TikTok fehlgeschlagen: ' . $reason);
                }
                if (in_array($status, ['PROCESSING_UPLOAD', 'PROCESSING_DOWNLOAD'], true)) {
                    return [
                        'ok' => true,
                        'message' => 'TikTok verarbeitet Upload (' . $status . ') — warte.',
                        'done' => false,
                    ];
                }
                if ($status === 'SEND_TO_USER_INBOX') {
                    return self::markDone(
                        $jobId,
                        $payload,
                        $publishId,
                        $fileSize,
                        'Inbox',
                        'Draft in TikTok-Inbox — Creator in der App fortsetzen.'
                    );
                }
                if ($status === 'PUBLISH_COMPLETE') {
                    $ext = $publishId;
                    if ($st['publicly_available_post_ids'] !== []) {
                        $ext = $st['publicly_available_post_ids'][0];
                    }

                    return self::markDone(
                        $jobId,
                        $payload,
                        $ext,
                        $fileSize,
                        'Direct',
                        'Veröffentlicht: ' . $ext
                    );
                }

                return [
                    'ok' => true,
                    'message' => 'Status: ' . ($status !== '' ? $status : 'unbekannt') . ' — warte.',
                    'done' => false,
                ];
            }

            throw new RuntimeException('Unbekannte TikTok-Phase: ' . $phase);
        } catch (Throwable $e) {
            $attempts = (int) ($job['attempts'] ?? 0) + 1;
            $failed = $attempts >= self::MAX_ATTEMPTS;
            $payload = PublishJobRepository::payload($job);
            PublishJobRepository::update($jobId, [
                'state' => $failed ? PublishJobRepository::STATE_FAILED : PublishJobRepository::STATE_PENDING,
                'attempts' => $attempts,
                'last_error' => $e->getMessage(),
                'payload_json' => json_encode(
                    array_merge($payload, [
                        'tt_phase' => 'init',
                        'publish_id' => '',
                        'upload_url' => '',
                        'chunk_index' => 0,
                    ]),
                    JSON_UNESCAPED_UNICODE
                ),
            ]);
            error_log('DG CRM TikTokPublishService #' . $jobId . ': ' . $e->getMessage());

            return ['ok' => false, 'message' => $e->getMessage(), 'done' => $failed];
        }
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{ok: bool, message: string, done: bool}
     */
    private static function markDone(
        int $jobId,
        array $payload,
        string $externalId,
        int $fileSize,
        string $modeLabel,
        string $message
    ): array {
        $title = trim((string) ($payload['title'] ?? 'Video'));
        $caption = (string) ($payload['caption'] ?? $payload['title'] ?? '');
        $payload['tt_phase'] = 'done';
        PublishJobRepository::update($jobId, [
            'state' => PublishJobRepository::STATE_PUBLISHED,
            'external_id' => $externalId,
            'upload_offset' => $fileSize,
            'last_error' => '',
            'payload_json' => json_encode($payload, JSON_UNESCAPED_UNICODE),
        ]);
        PublishPostRepository::upsertFromJob($jobId, [
            'platform' => PublishJobRepository::PLATFORM_TIKTOK,
            'external_id' => $externalId,
            'title' => $title !== '' ? $title : 'TikTok',
            'description' => $caption,
            'privacy' => (string) ($payload['privacy_level'] ?? ($modeLabel === 'Inbox' ? 'inbox' : 'unknown')),
            'published_at' => date('Y-m-d H:i:s'),
            'editable_flags' => '',
        ]);

        return ['ok' => true, 'message' => $message, 'done' => true];
    }

    private static function assertAssetAllowed(string $path, int $fileSize, string $mime): void
    {
        if ($fileSize < self::MIN_BYTES) {
            throw new RuntimeException('TikTok: Video zu klein.');
        }
        $hostMax = VideoAssetStorage::MAX_BYTES;
        if ($fileSize > min(self::MAX_BYTES, $hostMax)) {
            throw new RuntimeException('TikTok: Video zu groß für dieses Hosting (max. 100 MB MVP).');
        }
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $okExt = in_array($ext, ['mp4', 'mov', 'webm', 'm4v'], true);
        $okMime = str_contains(strtolower($mime), 'mp4')
            || str_contains(strtolower($mime), 'quicktime')
            || str_contains(strtolower($mime), 'webm');
        if (!$okExt && !$okMime) {
            throw new RuntimeException('TikTok: MP4, MOV oder WebM erforderlich.');
        }
    }
}
