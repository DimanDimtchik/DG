<?php
declare(strict_types=1);

/** Instagram Content Publishing (Reels) — Graph + rupload. */
final class InstagramApiClient
{
    /**
     * @return array{quota_usage: int, quota_total: int, config: array<string, mixed>}
     */
    public static function contentPublishingLimit(string $accessToken, string $igUserId): array
    {
        $raw = self::graphGet($igUserId . '/content_publishing_limit', $accessToken, [
            'fields' => 'config,quota_usage',
        ]);
        $data0 = is_array($raw['data'][0] ?? null) ? $raw['data'][0] : $raw;
        $config = is_array($data0['config'] ?? null) ? $data0['config'] : [];
        $usage = (int) ($data0['quota_usage'] ?? 0);
        $total = (int) ($config['quota_total'] ?? $config['quota_duration'] ?? 100);

        return [
            'quota_usage' => $usage,
            'quota_total' => $total > 0 ? $total : 100,
            'config' => $config,
        ];
    }

    public static function assertPublishQuota(string $accessToken, string $igUserId): void
    {
        try {
            $q = self::contentPublishingLimit($accessToken, $igUserId);
        } catch (Throwable $e) {
            // Quota-Endpoint kann je nach Access fehlen — Upload nicht hart blockieren.
            error_log('DG CRM Instagram quota check: ' . $e->getMessage());

            return;
        }
        if ($q['quota_usage'] >= $q['quota_total']) {
            throw new RuntimeException(
                'Instagram Publish-Quota erschöpft (' . $q['quota_usage'] . '/' . $q['quota_total'] . ' in 24h).'
            );
        }
    }

    /**
     * Resumable Container (nur Facebook Login for Business).
     *
     * @return array{container_id: string, upload_uri: string}
     */
    public static function createReelsResumableContainer(
        string $accessToken,
        string $igUserId,
        string $caption,
        bool $shareToFeed = true
    ): array {
        $params = [
            'media_type' => 'REELS',
            'upload_type' => 'resumable',
            'caption' => $caption,
            'share_to_feed' => $shareToFeed ? 'true' : 'false',
            'access_token' => $accessToken,
        ];
        $raw = self::httpJson(
            'POST',
            self::graphBase() . '/' . rawurlencode($igUserId) . '/media',
            http_build_query($params),
            ['Content-Type: application/x-www-form-urlencoded']
        );
        $id = (string) ($raw['id'] ?? '');
        $uri = (string) ($raw['uri'] ?? '');
        if ($id === '') {
            throw new RuntimeException('Instagram: Container ohne ID.');
        }
        if ($uri === '') {
            // Manche Antworten liefern nur id — Upload-URI selbst bauen.
            $uri = 'https://rupload.facebook.com/ig-api-upload/' . InstagramPublishSettings::GRAPH_VERSION
                . '/' . rawurlencode($id);
        }

        return ['container_id' => $id, 'upload_uri' => $uri];
    }

    /**
     * Container mit öffentlich erreichbarer video_url (IG-Login oder Fallback).
     *
     * @return array{container_id: string}
     */
    public static function createReelsUrlContainer(
        string $accessToken,
        string $igUserId,
        string $videoUrl,
        string $caption,
        bool $shareToFeed = true
    ): array {
        $params = [
            'media_type' => 'REELS',
            'video_url' => $videoUrl,
            'caption' => $caption,
            'share_to_feed' => $shareToFeed ? 'true' : 'false',
            'access_token' => $accessToken,
        ];
        $raw = self::httpJson(
            'POST',
            self::graphBase() . '/' . rawurlencode($igUserId) . '/media',
            http_build_query($params),
            ['Content-Type: application/x-www-form-urlencoded']
        );
        $id = (string) ($raw['id'] ?? '');
        if ($id === '') {
            throw new RuntimeException('Instagram: Container ohne ID (video_url).');
        }

        return ['container_id' => $id];
    }

    public static function uploadResumableFile(string $uploadUri, string $accessToken, string $filePath, int $fileSize): void
    {
        if (!is_readable($filePath)) {
            throw new RuntimeException('Videodatei nicht lesbar.');
        }
        if (!function_exists('curl_init')) {
            throw new RuntimeException('cURL fehlt.');
        }
        $binary = file_get_contents($filePath);
        if ($binary === false || strlen($binary) < 1) {
            throw new RuntimeException('Videodatei konnte nicht gelesen werden.');
        }
        $ch = curl_init($uploadUri);
        if ($ch === false) {
            throw new RuntimeException('cURL init fehlgeschlagen.');
        }
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $binary,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: OAuth ' . $accessToken,
                'offset: 0',
                'file_size: ' . $fileSize,
                'Content-Type: application/octet-stream',
            ],
            CURLOPT_TIMEOUT => 600,
        ]);
        $response = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);
        if ($response === false) {
            throw new RuntimeException('rupload fehlgeschlagen: ' . $err);
        }
        if ($status >= 400) {
            $decoded = json_decode((string) $response, true);
            $msg = '';
            if (is_array($decoded)) {
                $errObj = is_array($decoded['error'] ?? null) ? $decoded['error'] : [];
                $msg = (string) ($errObj['message'] ?? $decoded['debug_info'] ?? '');
            }
            throw new RuntimeException($msg !== '' ? $msg : 'rupload HTTP ' . $status);
        }
    }

    /**
     * @return array{status_code: string, status: string, id: string}
     */
    public static function getContainerStatus(string $accessToken, string $containerId): array
    {
        $raw = self::graphGet($containerId, $accessToken, [
            'fields' => 'status_code,status,id',
        ]);

        return [
            'status_code' => (string) ($raw['status_code'] ?? ''),
            'status' => (string) ($raw['status'] ?? ''),
            'id' => (string) ($raw['id'] ?? $containerId),
        ];
    }

    public static function publishContainer(string $accessToken, string $igUserId, string $containerId): string
    {
        $params = [
            'creation_id' => $containerId,
            'access_token' => $accessToken,
        ];
        $raw = self::httpJson(
            'POST',
            self::graphBase() . '/' . rawurlencode($igUserId) . '/media_publish',
            http_build_query($params),
            ['Content-Type: application/x-www-form-urlencoded']
        );
        $id = (string) ($raw['id'] ?? '');
        if ($id === '') {
            throw new RuntimeException('Instagram media_publish ohne Media-ID.');
        }

        return $id;
    }

    public static function deleteMedia(string $accessToken, string $mediaId): void
    {
        self::httpJson(
            'DELETE',
            self::graphBase() . '/' . rawurlencode($mediaId) . '?' . http_build_query([
                'access_token' => $accessToken,
            ]),
            null,
            []
        );
    }

    /**
     * @return list<array{id: string, text: string, username: string, timestamp: string, like_count: int}>
     */
    public static function listComments(string $accessToken, string $mediaId): array
    {
        $raw = self::graphGet($mediaId . '/comments', $accessToken, [
            'fields' => 'id,text,username,timestamp,like_count',
            'limit' => 50,
        ]);
        $data = is_array($raw['data'] ?? null) ? $raw['data'] : [];
        $out = [];
        foreach ($data as $row) {
            if (!is_array($row)) {
                continue;
            }
            $out[] = [
                'id' => (string) ($row['id'] ?? ''),
                'text' => (string) ($row['text'] ?? ''),
                'username' => (string) ($row['username'] ?? ''),
                'timestamp' => (string) ($row['timestamp'] ?? ''),
                'like_count' => (int) ($row['like_count'] ?? 0),
            ];
        }

        return $out;
    }

    public static function createMediaComment(string $accessToken, string $mediaId, string $message): string
    {
        $message = trim($message);
        if ($message === '') {
            throw new InvalidArgumentException('Kommentartext fehlt.');
        }
        $raw = self::httpJson(
            'POST',
            self::graphBase() . '/' . rawurlencode($mediaId) . '/comments',
            http_build_query([
                'message' => $message,
                'access_token' => $accessToken,
            ]),
            ['Content-Type: application/x-www-form-urlencoded']
        );
        $id = (string) ($raw['id'] ?? '');
        if ($id === '') {
            throw new RuntimeException('Instagram: Kommentar ohne ID.');
        }

        return $id;
    }

    public static function deleteComment(string $accessToken, string $commentId): void
    {
        self::httpJson(
            'DELETE',
            self::graphBase() . '/' . rawurlencode($commentId) . '?' . http_build_query([
                'access_token' => $accessToken,
            ]),
            null,
            []
        );
    }

    public static function hideComment(string $accessToken, string $commentId, bool $hide = true): void
    {
        self::httpJson(
            'POST',
            self::graphBase() . '/' . rawurlencode($commentId),
            http_build_query([
                'hide' => $hide ? 'true' : 'false',
                'access_token' => $accessToken,
            ]),
            ['Content-Type: application/x-www-form-urlencoded']
        );
    }

    /**
     * @return array{plays: int, reach: int, likes: int, comments: int, shares: int, saved: int, total_interactions: int}
     */
    public static function getMediaInsights(string $accessToken, string $mediaId): array
    {
        $out = [
            'plays' => 0,
            'reach' => 0,
            'likes' => 0,
            'comments' => 0,
            'shares' => 0,
            'saved' => 0,
            'total_interactions' => 0,
        ];
        $metrics = 'plays,reach,likes,comments,shares,saved,total_interactions';
        try {
            $raw = self::graphGet($mediaId . '/insights', $accessToken, [
                'metric' => $metrics,
            ]);
        } catch (Throwable $e) {
            // Ältere/eingeschränkte Metriken — Fallback ohne plays
            try {
                $raw = self::graphGet($mediaId . '/insights', $accessToken, [
                    'metric' => 'reach,likes,comments,shares,saved',
                ]);
            } catch (Throwable $e2) {
                throw new RuntimeException('Insights: ' . $e2->getMessage(), 0, $e2);
            }
        }
        $data = is_array($raw['data'] ?? null) ? $raw['data'] : [];
        foreach ($data as $row) {
            if (!is_array($row)) {
                continue;
            }
            $name = (string) ($row['name'] ?? '');
            if ($name === '' || !array_key_exists($name, $out)) {
                continue;
            }
            $values = is_array($row['values'] ?? null) ? $row['values'] : [];
            $val = $values[0]['value'] ?? ($row['value'] ?? 0);
            $out[$name] = (int) $val;
        }

        return $out;
    }

    private static function graphBase(): string
    {
        $cfg = InstagramPublishSettings::config();
        $v = InstagramPublishSettings::GRAPH_VERSION;
        if (($cfg['login_path'] ?? '') === InstagramPublishSettings::LOGIN_INSTAGRAM) {
            return 'https://graph.instagram.com/' . $v;
        }

        return 'https://graph.facebook.com/' . $v;
    }

    /**
     * @param array<string, scalar> $query
     * @return array<string, mixed>
     */
    private static function graphGet(string $path, string $accessToken, array $query = []): array
    {
        $query['access_token'] = $accessToken;
        $url = self::graphBase() . '/' . ltrim($path, '/') . '?' . http_build_query($query);

        return self::httpJson('GET', $url, null, []);
    }

    /**
     * @param list<string> $headers
     * @return array<string, mixed>
     */
    private static function httpJson(string $method, string $url, ?string $body, array $headers): array
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
            CURLOPT_TIMEOUT => 120,
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
        $decoded = json_decode((string) $response, true);
        if (!is_array($decoded)) {
            throw new RuntimeException('Ungültige JSON-Antwort (HTTP ' . $status . ').');
        }
        if ($status >= 400) {
            $errObj = is_array($decoded['error'] ?? null) ? $decoded['error'] : [];
            $msg = (string) ($errObj['message'] ?? $decoded['error_message'] ?? '');
            if ($msg === '') {
                $msg = 'HTTP ' . $status;
            }
            throw new RuntimeException($msg);
        }

        return $decoded;
    }
}
