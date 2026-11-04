<?php
declare(strict_types=1);

/** TikTok Content Posting API — Inbox / Direct, Chunk-Upload, Status. */
final class TikTokApiClient
{
    public const API_BASE = 'https://open.tiktokapis.com';
    public const MIN_CHUNK = 5_000_000;
    public const MAX_CHUNK = 64_000_000;
    public const PREFERRED_CHUNK = 10_000_000;

    /**
     * @return array{
     *   creator_username: string,
     *   creator_nickname: string,
     *   privacy_level_options: list<string>,
     *   comment_disabled: bool,
     *   duet_disabled: bool,
     *   stitch_disabled: bool,
     *   max_video_post_duration_sec: int,
     *   raw: array<string, mixed>
     * }
     */
    public static function queryCreatorInfo(string $accessToken): array
    {
        $raw = self::apiJson('POST', '/v2/post/publish/creator_info/query/', $accessToken, new stdClass());
        $data = is_array($raw['data'] ?? null) ? $raw['data'] : [];
        $options = [];
        foreach ($data['privacy_level_options'] ?? [] as $opt) {
            if (is_string($opt) && $opt !== '') {
                $options[] = $opt;
            }
        }

        return [
            'creator_username' => (string) ($data['creator_username'] ?? ''),
            'creator_nickname' => (string) ($data['creator_nickname'] ?? ''),
            'privacy_level_options' => $options,
            'comment_disabled' => !empty($data['comment_disabled']),
            'duet_disabled' => !empty($data['duet_disabled']),
            'stitch_disabled' => !empty($data['stitch_disabled']),
            'max_video_post_duration_sec' => (int) ($data['max_video_post_duration_sec'] ?? 0),
            'raw' => $data,
        ];
    }

    /**
     * @return array{chunk_size: int, total_chunk_count: int}
     */
    public static function chunkPlan(int $fileSize): array
    {
        if ($fileSize < 1) {
            throw new InvalidArgumentException('Dateigröße ungültig.');
        }
        if ($fileSize <= self::MIN_CHUNK) {
            return [
                'chunk_size' => $fileSize,
                'total_chunk_count' => 1,
            ];
        }
        $chunk = min(self::MAX_CHUNK, max(self::MIN_CHUNK, self::PREFERRED_CHUNK));
        $count = (int) ceil($fileSize / $chunk);

        return [
            'chunk_size' => $chunk,
            'total_chunk_count' => max(1, $count),
        ];
    }

    /**
     * Inbox Upload init (`video.upload`).
     *
     * @return array{publish_id: string, upload_url: string}
     */
    public static function initInboxUpload(
        string $accessToken,
        int $videoSize,
        int $chunkSize,
        int $totalChunkCount
    ): array {
        $body = [
            'source_info' => [
                'source' => 'FILE_UPLOAD',
                'video_size' => $videoSize,
                'chunk_size' => $chunkSize,
                'total_chunk_count' => $totalChunkCount,
            ],
        ];

        return self::parseInitResponse(
            self::apiJson('POST', '/v2/post/publish/inbox/video/init/', $accessToken, $body)
        );
    }

    /**
     * Direct Post init (`video.publish`).
     *
     * @param array<string, mixed> $postInfo
     * @return array{publish_id: string, upload_url: string}
     */
    public static function initDirectPost(
        string $accessToken,
        array $postInfo,
        int $videoSize,
        int $chunkSize,
        int $totalChunkCount
    ): array {
        $body = [
            'post_info' => $postInfo,
            'source_info' => [
                'source' => 'FILE_UPLOAD',
                'video_size' => $videoSize,
                'chunk_size' => $chunkSize,
                'total_chunk_count' => $totalChunkCount,
            ],
        ];

        return self::parseInitResponse(
            self::apiJson('POST', '/v2/post/publish/video/init/', $accessToken, $body)
        );
    }

    /**
     * Ein Chunk per PUT an upload_url.
     *
     * @return array{ok: bool, status: int}
     */
    public static function uploadChunk(
        string $uploadUrl,
        string $filePath,
        int $offset,
        int $length,
        int $fileSize,
        string $contentType
    ): array {
        if (!is_readable($filePath)) {
            throw new RuntimeException('Videodatei nicht lesbar.');
        }
        if ($length < 1) {
            throw new RuntimeException('Kein Restinhalt mehr zum Hochladen.');
        }
        $fh = fopen($filePath, 'rb');
        if ($fh === false) {
            throw new RuntimeException('Datei öffnen fehlgeschlagen.');
        }
        if (fseek($fh, $offset) !== 0) {
            fclose($fh);
            throw new RuntimeException('Dateioffset ungültig.');
        }
        $data = fread($fh, $length);
        fclose($fh);
        if (!is_string($data) || strlen($data) !== $length) {
            throw new RuntimeException('Chunk lesen fehlgeschlagen.');
        }
        $end = $offset + $length - 1;
        $headers = [
            'Content-Type: ' . $contentType,
            'Content-Length: ' . $length,
            'Content-Range: bytes ' . $offset . '-' . $end . '/' . $fileSize,
        ];
        $result = self::httpRaw('PUT', $uploadUrl, $data, $headers);
        $status = $result['status'];
        // TikTok: 200/201 fertig für Chunk; manche Antworten 206
        if ($status >= 200 && $status < 300) {
            return ['ok' => true, 'status' => $status];
        }
        throw new RuntimeException(
            'TikTok Chunk-Upload HTTP ' . $status . ': ' . mb_substr($result['body'], 0, 400)
        );
    }

    /**
     * @return array{
     *   status: string,
     *   fail_reason: string,
     *   uploaded_bytes: int,
     *   publicly_available_post_ids: list<string>,
     *   raw: array<string, mixed>
     * }
     */
    public static function fetchPublishStatus(string $accessToken, string $publishId): array
    {
        $raw = self::apiJson('POST', '/v2/post/publish/status/fetch/', $accessToken, [
            'publish_id' => $publishId,
        ]);
        $data = is_array($raw['data'] ?? null) ? $raw['data'] : [];
        $postIds = [];
        foreach ($data['publicaly_available_post_id'] ?? [] as $id) {
            if (is_scalar($id) && (string) $id !== '') {
                $postIds[] = (string) $id;
            }
        }

        return [
            'status' => strtoupper((string) ($data['status'] ?? '')),
            'fail_reason' => (string) ($data['fail_reason'] ?? ''),
            'uploaded_bytes' => (int) ($data['uploaded_bytes'] ?? 0),
            'publicly_available_post_ids' => $postIds,
            'raw' => $data,
        ];
    }

    public static function contentTypeForPath(string $path, string $mimeHint = ''): string
    {
        $mime = strtolower(trim($mimeHint));
        if (str_contains($mime, 'webm')) {
            return 'video/webm';
        }
        if (str_contains($mime, 'quicktime') || str_contains($mime, 'mov')) {
            return 'video/quicktime';
        }
        if (str_contains($mime, 'mp4')) {
            return 'video/mp4';
        }
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        return match ($ext) {
            'webm' => 'video/webm',
            'mov', 'qt' => 'video/quicktime',
            default => 'video/mp4',
        };
    }

    /**
     * Display API: Video-Metriken für eigene Videos (Scope video.list).
     *
     * @return array{
     *   id: string,
     *   title: string,
     *   share_url: string,
     *   create_time: int,
     *   duration: int,
     *   view_count: int,
     *   like_count: int,
     *   comment_count: int,
     *   share_count: int
     * }
     */
    public static function queryVideoStats(string $accessToken, string $videoId): array
    {
        $videoId = trim($videoId);
        if ($videoId === '') {
            throw new InvalidArgumentException('video_id fehlt.');
        }
        $fields = 'id,title,create_time,share_url,duration,like_count,comment_count,share_count,view_count';
        $path = '/v2/video/query/?fields=' . rawurlencode($fields);
        $raw = self::apiJson('POST', $path, $accessToken, [
            'filters' => [
                'video_ids' => [$videoId],
            ],
        ]);
        $videos = is_array($raw['data']['videos'] ?? null) ? $raw['data']['videos'] : [];
        $first = is_array($videos[0] ?? null) ? $videos[0] : null;
        if ($first === null) {
            throw new RuntimeException(
                'Video-ID nicht im verbundenen Konto gefunden (nur eigene öffentliche Posts).'
            );
        }

        return [
            'id' => (string) ($first['id'] ?? $videoId),
            'title' => (string) ($first['title'] ?? ''),
            'share_url' => (string) ($first['share_url'] ?? ''),
            'create_time' => (int) ($first['create_time'] ?? 0),
            'duration' => (int) ($first['duration'] ?? 0),
            'view_count' => (int) ($first['view_count'] ?? 0),
            'like_count' => (int) ($first['like_count'] ?? 0),
            'comment_count' => (int) ($first['comment_count'] ?? 0),
            'share_count' => (int) ($first['share_count'] ?? 0),
        ];
    }

    /**
     * Account-Kennzahlen (Scope user.info.stats).
     *
     * @return array{
     *   follower_count: int,
     *   following_count: int,
     *   likes_count: int,
     *   video_count: int,
     *   display_name: string
     * }
     */
    public static function fetchAccountStats(string $accessToken): array
    {
        $url = self::API_BASE . '/v2/user/info/?' . http_build_query([
            'fields' => 'open_id,display_name,follower_count,following_count,likes_count,video_count',
        ], '', '&', PHP_QUERY_RFC3986);
        $rawHttp = self::httpRaw('GET', $url, null, [
            'Authorization: Bearer ' . $accessToken,
        ]);
        $decoded = json_decode($rawHttp['body'], true);
        if (!is_array($decoded)) {
            throw new RuntimeException('Ungültige User-Info-Antwort (HTTP ' . $rawHttp['status'] . ').');
        }
        $err = is_array($decoded['error'] ?? null) ? $decoded['error'] : [];
        $code = (string) ($err['code'] ?? '');
        if ($rawHttp['status'] >= 400 || ($code !== '' && strtolower($code) !== 'ok')) {
            $msg = (string) ($err['message'] ?? $code);
            throw new RuntimeException($msg !== '' ? $msg : ('HTTP ' . $rawHttp['status']));
        }
        $user = is_array($decoded['data']['user'] ?? null) ? $decoded['data']['user'] : [];

        return [
            'follower_count' => (int) ($user['follower_count'] ?? 0),
            'following_count' => (int) ($user['following_count'] ?? 0),
            'likes_count' => (int) ($user['likes_count'] ?? 0),
            'video_count' => (int) ($user['video_count'] ?? 0),
            'display_name' => (string) ($user['display_name'] ?? ''),
        ];
    }

    /** Heuristik: Item-/Post-ID vs. publish_id aus Content Posting. */
    public static function looksLikeVideoItemId(string $id): bool
    {
        $id = trim($id);
        if ($id === '') {
            return false;
        }
        if (str_contains($id, '~') || str_starts_with($id, 'v_')) {
            return false;
        }

        return (bool) preg_match('/^\d{5,}$/', $id);
    }

    /**
     * @param array<string, mixed> $raw
     * @return array{publish_id: string, upload_url: string}
     */
    private static function parseInitResponse(array $raw): array
    {
        $data = is_array($raw['data'] ?? null) ? $raw['data'] : [];
        $publishId = (string) ($data['publish_id'] ?? '');
        $uploadUrl = (string) ($data['upload_url'] ?? '');
        if ($publishId === '') {
            throw new RuntimeException('TikTok: Init ohne publish_id.');
        }
        if ($uploadUrl === '') {
            throw new RuntimeException('TikTok: Init ohne upload_url (FILE_UPLOAD).');
        }

        return [
            'publish_id' => $publishId,
            'upload_url' => $uploadUrl,
        ];
    }

    /**
     * @param object|array<string, mixed> $body
     * @return array<string, mixed>
     */
    private static function apiJson(string $method, string $path, string $accessToken, object|array $body): array
    {
        $json = json_encode($body, JSON_UNESCAPED_UNICODE);
        if (!is_string($json)) {
            throw new RuntimeException('JSON-Encode fehlgeschlagen.');
        }
        $raw = self::httpRaw($method, self::API_BASE . $path, $json, [
            'Authorization: Bearer ' . $accessToken,
            'Content-Type: application/json; charset=UTF-8',
        ]);
        $decoded = json_decode($raw['body'], true);
        if (!is_array($decoded)) {
            throw new RuntimeException('Ungültige TikTok-JSON-Antwort (HTTP ' . $raw['status'] . ').');
        }
        $err = is_array($decoded['error'] ?? null) ? $decoded['error'] : [];
        $code = (string) ($err['code'] ?? '');
        if ($raw['status'] >= 400 || ($code !== '' && strtolower($code) !== 'ok')) {
            $msg = (string) ($err['message'] ?? '');
            if ($msg === '') {
                $msg = $code !== '' ? $code : ('HTTP ' . $raw['status']);
            }
            throw new RuntimeException($msg);
        }

        return $decoded;
    }

    /**
     * @param list<string> $headers
     * @return array{status: int, body: string}
     */
    private static function httpRaw(string $method, string $url, ?string $body, array $headers): array
    {
        if (!function_exists('curl_init')) {
            throw new RuntimeException('cURL fehlt.');
        }
        $ch = curl_init($url);
        if ($ch === false) {
            throw new RuntimeException('cURL init fehlgeschlagen.');
        }
        $opts = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => 300,
        ];
        if ($body !== null) {
            $opts[CURLOPT_POSTFIELDS] = $body;
        }
        curl_setopt_array($ch, $opts);
        $response = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);
        if ($response === false) {
            throw new RuntimeException('HTTP-Fehler: ' . $err);
        }

        return ['status' => $status, 'body' => (string) $response];
    }
}
