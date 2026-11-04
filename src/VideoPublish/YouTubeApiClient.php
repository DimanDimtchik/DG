<?php
declare(strict_types=1);

/** YouTube Data API v3: Resumable Upload, Update, Delete. */
final class YouTubeApiClient
{
    public const UPLOAD_INIT_URL = 'https://www.googleapis.com/upload/youtube/v3/videos?uploadType=resumable&part=snippet,status';
    public const VIDEOS_URL = 'https://www.googleapis.com/youtube/v3/videos';
    public const CHUNK_BYTES = 2_097_152; // 2 MiB

    /**
     * @param array<string, mixed> $payload title, description, tags?, privacy, publishAt?, categoryId?
     */
    public static function startResumableSession(string $accessToken, array $payload, string $mime, int $fileSize): string
    {
        $privacy = self::sanitizePrivacy((string) ($payload['privacy'] ?? 'private'));
        $status = [
            'privacyStatus' => $privacy,
            'selfDeclaredMadeForKids' => false,
        ];
        $publishAt = trim((string) ($payload['publishAt'] ?? ''));
        if ($publishAt !== '' && $privacy === 'private') {
            $ts = strtotime($publishAt);
            if ($ts !== false && $ts > time() + 60) {
                $status['publishAt'] = gmdate('Y-m-d\TH:i:s\Z', $ts);
            }
        }
        $snippet = [
            'title' => mb_substr(trim((string) ($payload['title'] ?? 'Video')), 0, 100),
            'description' => (string) ($payload['description'] ?? ''),
            'categoryId' => (string) ($payload['categoryId'] ?? '22'),
        ];
        $tags = $payload['tags'] ?? [];
        if (is_string($tags) && $tags !== '') {
            $tags = array_values(array_filter(array_map('trim', explode(',', $tags))));
        }
        if (is_array($tags) && $tags !== []) {
            $snippet['tags'] = array_slice(array_map('strval', $tags), 0, 20);
        }
        $body = json_encode(['snippet' => $snippet, 'status' => $status], JSON_UNESCAPED_UNICODE);
        if (!is_string($body)) {
            throw new RuntimeException('Metadaten ungültig.');
        }

        $headers = [
            'Authorization: Bearer ' . $accessToken,
            'Content-Type: application/json; charset=UTF-8',
            'X-Upload-Content-Length: ' . $fileSize,
            'X-Upload-Content-Type: ' . $mime,
        ];
        $result = self::httpRaw('POST', self::UPLOAD_INIT_URL, $body, $headers, true);
        $location = (string) ($result['headers']['location'] ?? $result['headers']['Location'] ?? '');
        if ($location === '') {
            $msg = self::errorMessage($result['body'], $result['status']);
            throw new RuntimeException('Resumable-Session fehlgeschlagen: ' . $msg);
        }

        return $location;
    }

    /**
     * @return array{done: bool, offset: int, video_id: string, raw: string}
     */
    public static function uploadChunk(
        string $sessionUrl,
        string $accessToken,
        string $filePath,
        int $offset,
        int $fileSize
    ): array {
        if (!is_readable($filePath)) {
            throw new RuntimeException('Videodatei nicht lesbar.');
        }
        $chunk = self::CHUNK_BYTES;
        $length = min($chunk, max(0, $fileSize - $offset));
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
            'Authorization: Bearer ' . $accessToken,
            'Content-Type: application/octet-stream',
            'Content-Length: ' . $length,
            'Content-Range: bytes ' . $offset . '-' . $end . '/' . $fileSize,
        ];
        $result = self::httpRaw('PUT', $sessionUrl, $data, $headers, false);
        $status = $result['status'];
        $body = $result['body'];

        if ($status === 200 || $status === 201) {
            $json = json_decode($body, true);
            $videoId = is_array($json) ? (string) ($json['id'] ?? '') : '';
            if ($videoId === '') {
                throw new RuntimeException('Upload fertig, aber keine Video-ID.');
            }

            return ['done' => true, 'offset' => $fileSize, 'video_id' => $videoId, 'raw' => $body];
        }
        if ($status === 308) {
            $range = (string) ($result['headers']['range'] ?? $result['headers']['Range'] ?? '');
            $newOffset = $offset + $length;
            if (preg_match('/bytes=0-(\d+)/', $range, $m) === 1) {
                $newOffset = (int) $m[1] + 1;
            }

            return ['done' => false, 'offset' => $newOffset, 'video_id' => '', 'raw' => $body];
        }

        throw new RuntimeException('Chunk-Upload HTTP ' . $status . ': ' . self::errorMessage($body, $status));
    }

    /**
     * @param array<string, mixed> $fields title?, description?, privacy?
     */
    public static function updateVideo(string $accessToken, string $videoId, array $fields): void
    {
        $snippet = [
            'title' => mb_substr(trim((string) ($fields['title'] ?? 'Video')), 0, 100),
            'description' => (string) ($fields['description'] ?? ''),
            'categoryId' => (string) ($fields['categoryId'] ?? '22'),
        ];
        $status = [
            'privacyStatus' => self::sanitizePrivacy((string) ($fields['privacy'] ?? 'private')),
        ];
        $body = json_encode([
            'id' => $videoId,
            'snippet' => $snippet,
            'status' => $status,
        ], JSON_UNESCAPED_UNICODE);
        $url = self::VIDEOS_URL . '?part=snippet,status';
        $result = self::httpRaw('PUT', $url, (string) $body, [
            'Authorization: Bearer ' . $accessToken,
            'Content-Type: application/json; charset=UTF-8',
        ], false);
        if ($result['status'] >= 400) {
            throw new RuntimeException(self::errorMessage($result['body'], $result['status']));
        }
    }

    public static function deleteVideo(string $accessToken, string $videoId): void
    {
        $url = self::VIDEOS_URL . '?id=' . rawurlencode($videoId);
        $result = self::httpRaw('DELETE', $url, null, [
            'Authorization: Bearer ' . $accessToken,
        ], false);
        if ($result['status'] >= 400 && $result['status'] !== 404) {
            throw new RuntimeException(self::errorMessage($result['body'], $result['status']));
        }
    }

    public static function sanitizePrivacy(string $privacy): string
    {
        $privacy = strtolower(trim($privacy));

        return in_array($privacy, ['public', 'unlisted', 'private'], true) ? $privacy : 'private';
    }

    /**
     * @param list<string> $headers
     * @return array{status: int, body: string, headers: array<string, string>}
     */
    private static function httpRaw(
        string $method,
        string $url,
        ?string $body,
        array $headers,
        bool $wantHeaderLocation
    ): array {
        if (!function_exists('curl_init')) {
            throw new RuntimeException('cURL fehlt.');
        }
        $ch = curl_init($url);
        if ($ch === false) {
            throw new RuntimeException('cURL init fehlgeschlagen.');
        }
        $headerLines = [];
        $opts = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => 120,
            CURLOPT_HEADERFUNCTION => static function ($ch, string $line) use (&$headerLines): int {
                $len = strlen($line);
                $parts = explode(':', $line, 2);
                if (count($parts) === 2) {
                    $headerLines[trim($parts[0])] = trim($parts[1]);
                }

                return $len;
            },
        ];
        if ($body !== null) {
            $opts[CURLOPT_POSTFIELDS] = $body;
        }
        if ($wantHeaderLocation) {
            $opts[CURLOPT_HEADER] = false;
        }
        curl_setopt_array($ch, $opts);
        $response = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);
        if ($response === false) {
            throw new RuntimeException('HTTP-Fehler: ' . $err);
        }

        return [
            'status' => $status,
            'body' => (string) $response,
            'headers' => $headerLines,
        ];
    }

    private static function errorMessage(string $body, int $status): string
    {
        $decoded = json_decode($body, true);
        if (is_array($decoded)) {
            if (isset($decoded['error']['message']) && is_string($decoded['error']['message'])) {
                return $decoded['error']['message'];
            }
            if (isset($decoded['error_description']) && is_string($decoded['error_description'])) {
                return $decoded['error_description'];
            }
        }

        return 'HTTP ' . $status;
    }
}
