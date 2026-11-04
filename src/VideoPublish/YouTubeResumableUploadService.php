<?php
declare(strict_types=1);

/** Verarbeitet einen Publish-Job (Resumable Chunks). */
final class YouTubeResumableUploadService
{
    public const MAX_ATTEMPTS = 8;

    /**
     * @return array{ok: bool, message: string, done: bool}
     */
    public static function processJob(int $jobId): array
    {
        $job = PublishJobRepository::find($jobId);
        if ($job === null) {
            return ['ok' => false, 'message' => 'Job nicht gefunden.', 'done' => true];
        }
        $state = (string) ($job['state'] ?? '');
        if ($state === PublishJobRepository::STATE_PUBLISHED) {
            return ['ok' => true, 'message' => 'Bereits veröffentlicht.', 'done' => true];
        }
        if ($state === PublishJobRepository::STATE_FAILED && (int) ($job['attempts'] ?? 0) >= self::MAX_ATTEMPTS) {
            return ['ok' => false, 'message' => 'Max. Versuche erreicht.', 'done' => true];
        }

        try {
            $access = YouTubeTokenService::accessToken();
            $payload = PublishJobRepository::payload($job);
            $stored = (string) ($job['stored_name'] ?? '');
            $path = VideoAssetStorage::absolutePath($stored);
            $fileSize = (int) ($job['size_bytes'] ?? 0);
            if ($fileSize < 1 && is_file($path)) {
                $fileSize = (int) filesize($path);
            }
            if ($fileSize < 1 || !is_file($path)) {
                throw new RuntimeException('Videodatei fehlt.');
            }
            $mime = (string) ($job['mime_type'] ?? 'video/mp4');
            $session = trim((string) ($job['upload_session_url'] ?? ''));
            $offset = (int) ($job['upload_offset'] ?? 0);

            if ($session === '' || $state === PublishJobRepository::STATE_PENDING) {
                $session = YouTubeApiClient::startResumableSession($access, $payload, $mime, $fileSize);
                PublishJobRepository::update($jobId, [
                    'state' => PublishJobRepository::STATE_UPLOADING,
                    'upload_session_url' => $session,
                    'upload_offset' => 0,
                    'attempts' => (int) ($job['attempts'] ?? 0) + 1,
                    'last_error' => '',
                ]);
                $offset = 0;
            }

            $result = YouTubeApiClient::uploadChunk($session, $access, $path, $offset, $fileSize);
            if (!$result['done']) {
                PublishJobRepository::update($jobId, [
                    'state' => PublishJobRepository::STATE_UPLOADING,
                    'upload_offset' => $result['offset'],
                    'last_error' => '',
                ]);

                return [
                    'ok' => true,
                    'message' => 'Chunk hochgeladen (' . $result['offset'] . '/' . $fileSize . ').',
                    'done' => false,
                ];
            }

            $videoId = $result['video_id'];
            PublishJobRepository::update($jobId, [
                'state' => PublishJobRepository::STATE_PUBLISHED,
                'upload_offset' => $fileSize,
                'external_id' => $videoId,
                'last_error' => '',
            ]);
            PublishPostRepository::upsertFromJob($jobId, [
                'platform' => PublishJobRepository::PLATFORM_YOUTUBE,
                'external_id' => $videoId,
                'title' => (string) ($payload['title'] ?? ''),
                'description' => (string) ($payload['description'] ?? ''),
                'privacy' => YouTubeApiClient::sanitizePrivacy((string) ($payload['privacy'] ?? 'private')),
                'published_at' => date('Y-m-d H:i:s'),
            ]);

            return ['ok' => true, 'message' => 'Veröffentlicht: ' . $videoId, 'done' => true];
        } catch (Throwable $e) {
            $attempts = (int) ($job['attempts'] ?? 0) + 1;
            $failed = $attempts >= self::MAX_ATTEMPTS;
            PublishJobRepository::update($jobId, [
                'state' => $failed ? PublishJobRepository::STATE_FAILED : PublishJobRepository::STATE_PENDING,
                'attempts' => $attempts,
                'last_error' => mb_substr($e->getMessage(), 0, 1000),
                // Session ggf. ungültig → neu starten
                'upload_session_url' => $failed ? (string) ($job['upload_session_url'] ?? '') : '',
                'upload_offset' => $failed ? (int) ($job['upload_offset'] ?? 0) : 0,
            ]);
            error_log('DG CRM YouTubeResumableUploadService #' . $jobId . ': ' . $e->getMessage());

            return ['ok' => false, 'message' => $e->getMessage(), 'done' => $failed];
        }
    }

    public static function updatePublished(int $jobId, array $fields): void
    {
        $job = PublishJobRepository::find($jobId);
        if ($job === null) {
            throw new InvalidArgumentException('Job nicht gefunden.');
        }
        $videoId = (string) ($job['external_id'] ?? '');
        if ($videoId === '') {
            throw new InvalidArgumentException('Kein YouTube-Video verknüpft.');
        }
        $access = YouTubeTokenService::accessToken();
        $post = PublishPostRepository::findByJob($jobId);
        $title = (string) ($fields['title'] ?? ($post['title'] ?? ''));
        $description = (string) ($fields['description'] ?? ($post['description'] ?? ''));
        $privacy = YouTubeApiClient::sanitizePrivacy((string) ($fields['privacy'] ?? ($post['privacy'] ?? 'private')));
        YouTubeApiClient::updateVideo($access, $videoId, [
            'title' => $title,
            'description' => $description,
            'privacy' => $privacy,
        ]);
        if ($post !== null) {
            PublishPostRepository::update((int) $post['id'], [
                'title' => $title,
                'description' => $description,
                'privacy' => $privacy,
            ]);
        }
        $payload = PublishJobRepository::payload($job);
        $payload['title'] = $title;
        $payload['description'] = $description;
        $payload['privacy'] = $privacy;
        PublishJobRepository::update($jobId, [
            'payload_json' => json_encode($payload, JSON_UNESCAPED_UNICODE),
        ]);
    }

    public static function deletePublished(int $jobId): void
    {
        $job = PublishJobRepository::find($jobId);
        if ($job === null) {
            throw new InvalidArgumentException('Job nicht gefunden.');
        }
        $videoId = (string) ($job['external_id'] ?? '');
        if ($videoId === '') {
            throw new InvalidArgumentException('Kein YouTube-Video verknüpft.');
        }
        $access = YouTubeTokenService::accessToken();
        YouTubeApiClient::deleteVideo($access, $videoId);
        $post = PublishPostRepository::findByJob($jobId);
        if ($post !== null) {
            PublishPostRepository::delete((int) $post['id']);
        }
        PublishJobRepository::update($jobId, [
            'external_id' => '',
            'state' => PublishJobRepository::STATE_FAILED,
            'last_error' => 'Auf YouTube gelöscht.',
        ]);
    }
}
