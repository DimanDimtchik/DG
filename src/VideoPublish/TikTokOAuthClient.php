<?php
declare(strict_types=1);

/** TikTok Login Kit OAuth 2.0 (v2) für Content Posting — Phase 4-1. */
final class TikTokOAuthClient
{
    public const AUTH_URL = 'https://www.tiktok.com/v2/auth/authorize/';
    public const TOKEN_URL = 'https://open.tiktokapis.com/v2/oauth/token/';
    public const USER_INFO_URL = 'https://open.tiktokapis.com/v2/user/info/';

    public static function authorizationUrl(string $state): string
    {
        $cfg = TikTokPublishSettings::config();
        if (!TikTokPublishSettings::credentialsReady($cfg)) {
            throw new RuntimeException('TikTok-OAuth ist nicht konfiguriert.');
        }

        $params = [
            'client_key' => (string) $cfg['client_key'],
            'response_type' => 'code',
            'scope' => implode(',', TikTokPublishSettings::scopes($cfg)),
            'redirect_uri' => TikTokPublishSettings::redirectUri(),
            'state' => $state,
        ];

        return self::AUTH_URL . '?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986);
    }

    /**
     * @return array{
     *   access_token: string,
     *   refresh_token: string,
     *   expires_in: int,
     *   refresh_expires_in: int,
     *   open_id: string,
     *   scope: string,
     *   token_type: string
     * }
     */
    public static function exchangeCode(string $code): array
    {
        $cfg = TikTokPublishSettings::config();
        if (!TikTokPublishSettings::credentialsReady($cfg)) {
            throw new RuntimeException('TikTok-OAuth ist nicht konfiguriert.');
        }

        return self::tokenRequest([
            'client_key' => (string) $cfg['client_key'],
            'client_secret' => (string) $cfg['client_secret'],
            'code' => $code,
            'grant_type' => 'authorization_code',
            'redirect_uri' => TikTokPublishSettings::redirectUri(),
        ]);
    }

    /**
     * @return array{
     *   access_token: string,
     *   refresh_token: string,
     *   expires_in: int,
     *   refresh_expires_in: int,
     *   open_id: string,
     *   scope: string,
     *   token_type: string
     * }
     */
    public static function refresh(string $refreshToken): array
    {
        $cfg = TikTokPublishSettings::config();
        if (!TikTokPublishSettings::credentialsReady($cfg)) {
            throw new RuntimeException('TikTok-OAuth ist nicht konfiguriert.');
        }
        if ($refreshToken === '') {
            throw new RuntimeException('Kein Refresh-Token — TikTok erneut verbinden.');
        }

        return self::tokenRequest([
            'client_key' => (string) $cfg['client_key'],
            'client_secret' => (string) $cfg['client_secret'],
            'grant_type' => 'refresh_token',
            'refresh_token' => $refreshToken,
        ]);
    }

    /**
     * @return array{open_id: string, display_name: string, avatar_url: string}
     */
    public static function fetchCreatorProfile(string $accessToken): array
    {
        $url = self::USER_INFO_URL . '?' . http_build_query([
            'fields' => 'open_id,union_id,avatar_url,display_name',
        ], '', '&', PHP_QUERY_RFC3986);
        $raw = self::httpJson('GET', $url, null, [
            'Authorization: Bearer ' . $accessToken,
        ]);
        $user = is_array($raw['data']['user'] ?? null) ? $raw['data']['user'] : [];
        $openId = (string) ($user['open_id'] ?? '');
        $name = trim((string) ($user['display_name'] ?? ''));
        if ($openId === '') {
            throw new RuntimeException('TikTok-Profil ohne open_id.');
        }

        return [
            'open_id' => $openId,
            'display_name' => $name !== '' ? $name : $openId,
            'avatar_url' => (string) ($user['avatar_url'] ?? ''),
        ];
    }

    /**
     * @param array<string, string> $fields
     * @return array{
     *   access_token: string,
     *   refresh_token: string,
     *   expires_in: int,
     *   refresh_expires_in: int,
     *   open_id: string,
     *   scope: string,
     *   token_type: string
     * }
     */
    private static function tokenRequest(array $fields): array
    {
        $raw = self::httpJson('POST', self::TOKEN_URL, http_build_query($fields), [
            'Content-Type: application/x-www-form-urlencoded',
            'Cache-Control: no-cache',
        ]);
        $access = (string) ($raw['access_token'] ?? '');
        if ($access === '') {
            $msg = (string) ($raw['error_description'] ?? $raw['error'] ?? 'Token-Antwort ohne access_token');
            throw new RuntimeException('TikTok OAuth: ' . $msg);
        }

        return [
            'access_token' => $access,
            'refresh_token' => (string) ($raw['refresh_token'] ?? ''),
            'expires_in' => (int) ($raw['expires_in'] ?? 86400),
            'refresh_expires_in' => (int) ($raw['refresh_expires_in'] ?? 0),
            'open_id' => (string) ($raw['open_id'] ?? ''),
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
            if ($msg === '' && isset($decoded['error']) && is_string($decoded['error'])) {
                $msg = $decoded['error'];
            }
            $errObj = is_array($decoded['error'] ?? null) ? $decoded['error'] : [];
            if ($msg === '') {
                $msg = (string) ($errObj['message'] ?? '');
            }
            if ($msg === '') {
                $msg = 'HTTP ' . $status;
            }
            throw new RuntimeException($msg);
        }
        // user/info: error.code !== ok
        $errWrap = is_array($decoded['error'] ?? null) ? $decoded['error'] : null;
        if ($errWrap !== null) {
            $code = (string) ($errWrap['code'] ?? '');
            if ($code !== '' && strtolower($code) !== 'ok') {
                throw new RuntimeException((string) ($errWrap['message'] ?? $code));
            }
        }

        return $decoded;
    }
}
