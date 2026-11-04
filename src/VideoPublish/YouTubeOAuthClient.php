<?php
declare(strict_types=1);

/** Google OAuth 2.0 (Web Server) für YouTube Data API. */
final class YouTubeOAuthClient
{
    public const AUTH_URL = 'https://accounts.google.com/o/oauth2/v2/auth';
    public const TOKEN_URL = 'https://oauth2.googleapis.com/token';
    public const CHANNELS_URL = 'https://www.googleapis.com/youtube/v3/channels';

    public static function authorizationUrl(string $state, bool $forceConsent = true): string
    {
        $cfg = YouTubePublishSettings::config();
        if (!YouTubePublishSettings::credentialsReady($cfg)) {
            throw new RuntimeException('YouTube-OAuth ist nicht konfiguriert.');
        }
        $params = [
            'client_id' => (string) $cfg['client_id'],
            'redirect_uri' => YouTubePublishSettings::redirectUri(),
            'response_type' => 'code',
            'scope' => implode(' ', YouTubePublishSettings::scopes()),
            'access_type' => 'offline',
            'include_granted_scopes' => 'true',
            'state' => $state,
        ];
        if ($forceConsent) {
            $params['prompt'] = 'consent';
        }

        return self::AUTH_URL . '?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986);
    }

    /**
     * @return array{access_token: string, refresh_token: string, expires_in: int, scope: string, token_type: string}
     */
    public static function exchangeCode(string $code): array
    {
        $cfg = YouTubePublishSettings::config();
        if (!YouTubePublishSettings::credentialsReady($cfg)) {
            throw new RuntimeException('YouTube-OAuth ist nicht konfiguriert.');
        }

        return self::tokenRequest([
            'code' => $code,
            'client_id' => (string) $cfg['client_id'],
            'client_secret' => (string) $cfg['client_secret'],
            'redirect_uri' => YouTubePublishSettings::redirectUri(),
            'grant_type' => 'authorization_code',
        ]);
    }

    /**
     * @return array{access_token: string, refresh_token: string, expires_in: int, scope: string, token_type: string}
     */
    public static function refresh(string $refreshToken): array
    {
        $cfg = YouTubePublishSettings::config();
        if (!YouTubePublishSettings::credentialsReady($cfg)) {
            throw new RuntimeException('YouTube-OAuth ist nicht konfiguriert.');
        }
        $result = self::tokenRequest([
            'client_id' => (string) $cfg['client_id'],
            'client_secret' => (string) $cfg['client_secret'],
            'refresh_token' => $refreshToken,
            'grant_type' => 'refresh_token',
        ]);
        if (($result['refresh_token'] ?? '') === '') {
            $result['refresh_token'] = $refreshToken;
        }

        return $result;
    }

    /**
     * @return array{channel_id: string, title: string}
     */
    public static function fetchMyChannel(string $accessToken): array
    {
        $url = self::CHANNELS_URL . '?part=snippet&mine=true';
        $raw = self::httpJson('GET', $url, null, [
            'Authorization: Bearer ' . $accessToken,
        ]);
        $items = $raw['items'] ?? null;
        if (!is_array($items) || $items === []) {
            throw new RuntimeException('Kein YouTube-Kanal für dieses Google-Konto gefunden.');
        }
        $first = $items[0];
        if (!is_array($first)) {
            throw new RuntimeException('Ungültige Kanal-Antwort.');
        }
        $id = (string) ($first['id'] ?? '');
        $title = (string) ($first['snippet']['title'] ?? $id);
        if ($id === '') {
            throw new RuntimeException('Kanal-ID fehlt.');
        }

        return ['channel_id' => $id, 'title' => $title];
    }

    /**
     * @param array<string, string> $fields
     * @return array{access_token: string, refresh_token: string, expires_in: int, scope: string, token_type: string}
     */
    private static function tokenRequest(array $fields): array
    {
        $raw = self::httpJson('POST', self::TOKEN_URL, http_build_query($fields), [
            'Content-Type: application/x-www-form-urlencoded',
        ]);
        $access = (string) ($raw['access_token'] ?? '');
        if ($access === '') {
            $msg = (string) ($raw['error_description'] ?? $raw['error'] ?? 'Token-Antwort ohne access_token');
            throw new RuntimeException('Google OAuth: ' . $msg);
        }

        return [
            'access_token' => $access,
            'refresh_token' => (string) ($raw['refresh_token'] ?? ''),
            'expires_in' => (int) ($raw['expires_in'] ?? 3600),
            'scope' => (string) ($raw['scope'] ?? ''),
            'token_type' => (string) ($raw['token_type'] ?? 'Bearer'),
        ];
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
            CURLOPT_TIMEOUT => 60,
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
            $msg = (string) ($decoded['error_description'] ?? '');
            if ($msg === '' && isset($decoded['error']) && is_array($decoded['error'])) {
                $msg = (string) ($decoded['error']['message'] ?? '');
            }
            if ($msg === '') {
                $msg = is_string($decoded['error'] ?? null) ? (string) $decoded['error'] : 'HTTP ' . $status;
            }
            throw new RuntimeException($msg);
        }

        return $decoded;
    }
}
