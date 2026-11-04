<?php
declare(strict_types=1);

/** LinkedIn Videos API + Posts API (Community Management). */
final class LinkedInApiClient
{
    public const VIDEOS_URL = 'https://api.linkedin.com/rest/videos';
    public const POSTS_URL = 'https://api.linkedin.com/rest/posts';
    public const SOCIAL_ACTIONS_URL = 'https://api.linkedin.com/rest/socialActions';
    public const VIDEO_ANALYTICS_URL = 'https://api.linkedin.com/rest/videoAnalytics';

    /**
     * @return array{
     *   video: string,
     *   upload_token: string,
     *   upload_instructions: list<array{uploadUrl: string, firstByte: int, lastByte: int}>,
     *   upload_urls_expire_at: int
     * }
     */
    public static function initializeUpload(string $accessToken, string $ownerUrn, int $fileSizeBytes): array
    {
        $body = json_encode([
            'initializeUploadRequest' => [
                'owner' => $ownerUrn,
                'fileSizeBytes' => $fileSizeBytes,
                'uploadCaptions' => false,
                'uploadThumbnail' => false,
            ],
        ], JSON_UNESCAPED_UNICODE);
        $raw = self::httpJson('POST', self::VIDEOS_URL . '?action=initializeUpload', (string) $body, self::authHeaders($accessToken));
        $value = is_array($raw['value'] ?? null) ? $raw['value'] : [];
        $video = (string) ($value['video'] ?? '');
        $instructions = $value['uploadInstructions'] ?? null;
        if ($video === '' || !is_array($instructions) || $instructions === []) {
            throw new RuntimeException('LinkedIn initializeUpload: keine Upload-Anweisungen.');
        }
        $parts = [];
        foreach ($instructions as $row) {
            if (!is_array($row)) {
                continue;
            }
            $parts[] = [
                'uploadUrl' => (string) ($row['uploadUrl'] ?? ''),
                'firstByte' => (int) ($row['firstByte'] ?? 0),
                'lastByte' => (int) ($row['lastByte'] ?? 0),
            ];
        }
        if ($parts === [] || ($parts[0]['uploadUrl'] ?? '') === '') {
            throw new RuntimeException('LinkedIn initializeUpload: leere Upload-URLs.');
        }

        return [
            'video' => $video,
            'upload_token' => (string) ($value['uploadToken'] ?? ''),
            'upload_instructions' => $parts,
            'upload_urls_expire_at' => (int) ($value['uploadUrlsExpireAt'] ?? 0),
        ];
    }

    /**
     * Lädt einen Multipart-Chunk; liefert ETag (uploadedPartId).
     */
    public static function uploadPart(string $uploadUrl, string $filePath, int $firstByte, int $lastByte): string
    {
        if (!is_readable($filePath)) {
            throw new RuntimeException('Videodatei nicht lesbar.');
        }
        $length = $lastByte - $firstByte + 1;
        if ($length < 1) {
            throw new RuntimeException('Ungültiger Byte-Bereich.');
        }
        $fh = fopen($filePath, 'rb');
        if ($fh === false) {
            throw new RuntimeException('Datei öffnen fehlgeschlagen.');
        }
        if (fseek($fh, $firstByte) !== 0) {
            fclose($fh);
            throw new RuntimeException('Dateioffset ungültig.');
        }
        $data = fread($fh, $length);
        fclose($fh);
        if (!is_string($data) || strlen($data) < 1) {
            throw new RuntimeException('Chunk lesen fehlgeschlagen.');
        }

        $result = self::httpRaw('PUT', $uploadUrl, $data, [
            'Content-Type: application/octet-stream',
            'Content-Length: ' . strlen($data),
        ], false);
        if ($result['status'] >= 400) {
            throw new RuntimeException('LinkedIn Chunk-Upload HTTP ' . $result['status']);
        }
        $etag = (string) ($result['headers']['etag'] ?? $result['headers']['ETag'] ?? '');
        $etag = trim($etag, " \t\"'");
        if ($etag === '') {
            throw new RuntimeException('LinkedIn Chunk ohne ETag.');
        }

        return $etag;
    }

    /**
     * @param list<string> $uploadedPartIds
     */
    public static function finalizeUpload(
        string $accessToken,
        string $videoUrn,
        string $uploadToken,
        array $uploadedPartIds
    ): void {
        $body = json_encode([
            'finalizeUploadRequest' => [
                'video' => $videoUrn,
                'uploadToken' => $uploadToken,
                'uploadedPartIds' => array_values($uploadedPartIds),
            ],
        ], JSON_UNESCAPED_UNICODE);
        self::httpJson('POST', self::VIDEOS_URL . '?action=finalizeUpload', (string) $body, self::authHeaders($accessToken));
    }

    /**
     * @return array{status: string, raw: array<string, mixed>}
     */
    public static function getVideo(string $accessToken, string $videoUrn): array
    {
        $url = self::VIDEOS_URL . '/' . rawurlencode($videoUrn);
        $raw = self::httpJson('GET', $url, null, self::authHeaders($accessToken));
        $status = (string) ($raw['status'] ?? '');

        return ['status' => $status, 'raw' => $raw];
    }

    /**
     * @return string Post-URN (x-restli-id)
     */
    public static function createVideoPost(
        string $accessToken,
        string $authorUrn,
        string $videoUrn,
        string $commentary,
        string $title
    ): string {
        $body = json_encode([
            'author' => $authorUrn,
            'commentary' => $commentary,
            'visibility' => 'PUBLIC',
            'distribution' => [
                'feedDistribution' => 'MAIN_FEED',
                'targetEntities' => [],
                'thirdPartyDistributionChannels' => [],
            ],
            'content' => [
                'media' => [
                    'title' => mb_substr(trim($title) !== '' ? trim($title) : 'Video', 0, 200),
                    'id' => $videoUrn,
                ],
            ],
            'lifecycleState' => 'PUBLISHED',
            'isReshareDisabledByAuthor' => false,
        ], JSON_UNESCAPED_UNICODE);
        $result = self::httpRaw('POST', self::POSTS_URL, (string) $body, array_merge(self::authHeaders($accessToken), [
            'Content-Type: application/json',
        ]), false);
        if ($result['status'] >= 400) {
            throw new RuntimeException(self::errorMessage($result['body'], $result['status']));
        }
        $postId = (string) ($result['headers']['x-restli-id'] ?? $result['headers']['X-RestLi-Id'] ?? '');
        if ($postId === '') {
            $json = json_decode($result['body'], true);
            if (is_array($json)) {
                $postId = (string) ($json['id'] ?? '');
            }
        }
        if ($postId === '') {
            throw new RuntimeException('LinkedIn Post ohne ID.');
        }

        return $postId;
    }

    public static function updatePostCommentary(string $accessToken, string $postUrn, string $commentary): void
    {
        $url = self::POSTS_URL . '/' . rawurlencode($postUrn);
        $body = json_encode([
            'patch' => [
                '$set' => [
                    'commentary' => $commentary,
                ],
            ],
        ], JSON_UNESCAPED_UNICODE);
        $result = self::httpRaw('POST', $url, (string) $body, array_merge(self::authHeaders($accessToken), [
            'Content-Type: application/json',
            'X-RestLi-Method: PARTIAL_UPDATE',
        ]), false);
        if ($result['status'] >= 400) {
            throw new RuntimeException(self::errorMessage($result['body'], $result['status']));
        }
    }

    public static function deletePost(string $accessToken, string $postUrn): void
    {
        $url = self::POSTS_URL . '/' . rawurlencode($postUrn);
        $result = self::httpRaw('DELETE', $url, null, array_merge(self::authHeaders($accessToken), [
            'X-RestLi-Method: DELETE',
        ]), false);
        if ($result['status'] >= 400 && $result['status'] !== 404) {
            throw new RuntimeException(self::errorMessage($result['body'], $result['status']));
        }
    }

    /**
     * @return list<array{id: string, text: string, actor: string, created_ms: int, likes: int}>
     */
    public static function listComments(string $accessToken, string $postUrn): array
    {
        $url = self::SOCIAL_ACTIONS_URL . '/' . rawurlencode($postUrn) . '/comments';
        $raw = self::httpJson('GET', $url, null, self::authHeaders($accessToken));
        $elements = $raw['elements'] ?? null;
        if (!is_array($elements)) {
            return [];
        }
        $out = [];
        foreach ($elements as $el) {
            if (!is_array($el)) {
                continue;
            }
            $likes = 0;
            if (isset($el['likesSummary']) && is_array($el['likesSummary'])) {
                $likes = (int) ($el['likesSummary']['totalLikes'] ?? $el['likesSummary']['aggregatedTotalLikes'] ?? 0);
            }
            $out[] = [
                'id' => (string) ($el['id'] ?? ''),
                'text' => (string) ($el['message']['text'] ?? ''),
                'actor' => (string) ($el['actor'] ?? ''),
                'created_ms' => (int) ($el['created']['time'] ?? 0),
                'likes' => $likes,
            ];
        }

        return $out;
    }

    public static function createComment(
        string $accessToken,
        string $postUrn,
        string $actorUrn,
        string $text
    ): string {
        $text = trim($text);
        if ($text === '') {
            throw new InvalidArgumentException('Kommentartext fehlt.');
        }
        $url = self::SOCIAL_ACTIONS_URL . '/' . rawurlencode($postUrn) . '/comments';
        $body = json_encode([
            'actor' => $actorUrn,
            'object' => $postUrn,
            'message' => ['text' => $text],
        ], JSON_UNESCAPED_UNICODE);
        $result = self::httpRaw('POST', $url, (string) $body, self::authHeaders($accessToken), false);
        if ($result['status'] >= 400) {
            throw new RuntimeException(self::errorMessage($result['body'], $result['status']));
        }
        $id = (string) ($result['headers']['x-restli-id'] ?? '');
        if ($id === '') {
            $json = json_decode($result['body'], true);
            $id = is_array($json) ? (string) ($json['id'] ?? '') : '';
        }
        if ($id === '') {
            throw new RuntimeException('Kommentar ohne ID.');
        }

        return $id;
    }

    public static function deleteComment(
        string $accessToken,
        string $postUrn,
        string $commentId,
        string $actorUrn
    ): void {
        $url = self::SOCIAL_ACTIONS_URL . '/' . rawurlencode($postUrn)
            . '/comments/' . rawurlencode($commentId)
            . '?actor=' . rawurlencode($actorUrn);
        $result = self::httpRaw('DELETE', $url, null, array_merge(self::authHeaders($accessToken), [
            'X-RestLi-Method: DELETE',
        ]), false);
        if ($result['status'] >= 400 && $result['status'] !== 404) {
            throw new RuntimeException(self::errorMessage($result['body'], $result['status']));
        }
    }

    /**
     * Likes/Comments-Zähler am Post (Social Actions).
     *
     * @return array{likes: int, comments: int}
     */
    public static function getSocialSummary(string $accessToken, string $postUrn): array
    {
        $url = self::SOCIAL_ACTIONS_URL . '/' . rawurlencode($postUrn);
        try {
            $raw = self::httpJson('GET', $url, null, self::authHeaders($accessToken));
        } catch (Throwable) {
            return ['likes' => 0, 'comments' => 0];
        }
        $likes = 0;
        $comments = 0;
        if (isset($raw['likesSummary']) && is_array($raw['likesSummary'])) {
            $likes = (int) ($raw['likesSummary']['totalLikes'] ?? $raw['likesSummary']['aggregatedTotalLikes'] ?? 0);
        }
        if (isset($raw['commentsSummary']) && is_array($raw['commentsSummary'])) {
            $comments = (int) ($raw['commentsSummary']['totalFirstLevelComments']
                ?? $raw['commentsSummary']['aggregatedTotalComments']
                ?? $raw['commentsSummary']['count']
                ?? 0);
        }

        return ['likes' => $likes, 'comments' => $comments];
    }

    /**
     * Video-Analytics (nur ugcPost). Fehlt die Metrik → 0.
     *
     * @return array{video_views: int, viewers: int, time_watched_ms: int}
     */
    public static function getVideoInsights(string $accessToken, string $ugcPostUrn): array
    {
        $out = [
            'video_views' => 0,
            'viewers' => 0,
            'time_watched_ms' => 0,
        ];
        if ($ugcPostUrn === '' || !str_contains($ugcPostUrn, 'ugcPost')) {
            return $out;
        }
        foreach (
            [
                'VIDEO_VIEW' => 'video_views',
                'VIEWER' => 'viewers',
                'TIME_WATCHED_FOR_VIDEO_VIEWS' => 'time_watched_ms',
            ] as $type => $key
        ) {
            $url = self::VIDEO_ANALYTICS_URL
                . '?q=entity'
                . '&entity=' . rawurlencode($ugcPostUrn)
                . '&type=' . rawurlencode($type)
                . '&aggregation=ALL';
            try {
                $raw = self::httpJson('GET', $url, null, self::authHeaders($accessToken));
                $elements = $raw['elements'] ?? null;
                if (!is_array($elements) || $elements === []) {
                    continue;
                }
                $sum = 0;
                foreach ($elements as $el) {
                    if (is_array($el)) {
                        $sum += (int) ($el['value'] ?? 0);
                    }
                }
                $out[$key] = $sum;
            } catch (Throwable) {
                // Insights optional — Post/Comments bleiben nutzbar
            }
        }

        return $out;
    }

    /**
     * @return list<string>
     */
    private static function authHeaders(string $accessToken): array
    {
        return [
            'Authorization: Bearer ' . $accessToken,
            'Linkedin-Version: ' . LinkedInPublishSettings::API_VERSION,
            'X-Restli-Protocol-Version: 2.0.0',
            'Content-Type: application/json',
        ];
    }

    /**
     * @param list<string> $headers
     * @return array<string, mixed>
     */
    private static function httpJson(string $method, string $url, ?string $body, array $headers): array
    {
        $result = self::httpRaw($method, $url, $body, $headers, false);
        $decoded = json_decode($result['body'], true);
        if ($result['status'] >= 400) {
            throw new RuntimeException(self::errorMessage($result['body'], $result['status']));
        }
        if ($result['body'] !== '' && !is_array($decoded)) {
            // finalizeUpload kann leeren Body liefern
            return [];
        }

        return is_array($decoded) ? $decoded : [];
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
        bool $unused
    ): array {
        unset($unused);
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
                    $headerLines[strtolower(trim($parts[0]))] = trim($parts[1]);
                }

                return $len;
            },
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
            if (isset($decoded['message']) && is_string($decoded['message'])) {
                return $decoded['message'];
            }
            if (isset($decoded['error_description']) && is_string($decoded['error_description'])) {
                return $decoded['error_description'];
            }
        }

        return 'HTTP ' . $status;
    }
}
