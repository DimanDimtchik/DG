<?php
declare(strict_types=1);

/** LinkedIn OAuth 2.0 (3-legged) für Community Management / Video-Publish. */
final class LinkedInOAuthClient
{
    public const AUTH_URL = 'https://www.linkedin.com/oauth/v2/authorization';
    public const TOKEN_URL = 'https://www.linkedin.com/oauth/v2/accessToken';
    public const USERINFO_URL = 'https://api.linkedin.com/v2/userinfo';
    public const ORG_ACL_URL = 'https://api.linkedin.com/rest/organizationAcls';

    public static function authorizationUrl(string $state): string
    {
        $cfg = LinkedInPublishSettings::config();
        if (!LinkedInPublishSettings::credentialsReady($cfg)) {
            throw new RuntimeException('LinkedIn-OAuth ist nicht konfiguriert.');
        }

        $params = [
            'response_type' => 'code',
            'client_id' => (string) $cfg['client_id'],
            'redirect_uri' => LinkedInPublishSettings::redirectUri(),
            'state' => $state,
            'scope' => implode(' ', LinkedInPublishSettings::scopes()),
        ];

        return self::AUTH_URL . '?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986);
    }

    /**
     * @return array{access_token: string, refresh_token: string, expires_in: int, scope: string, token_type: string}
     */
    public static function exchangeCode(string $code): array
    {
        $cfg = LinkedInPublishSettings::config();
        if (!LinkedInPublishSettings::credentialsReady($cfg)) {
            throw new RuntimeException('LinkedIn-OAuth ist nicht konfiguriert.');
        }

        return self::tokenRequest([
            'grant_type' => 'authorization_code',
            'code' => $code,
            'client_id' => (string) $cfg['client_id'],
            'client_secret' => (string) $cfg['client_secret'],
            'redirect_uri' => LinkedInPublishSettings::redirectUri(),
        ]);
    }

    /**
     * @return array{access_token: string, refresh_token: string, expires_in: int, scope: string, token_type: string}
     */
    public static function refresh(string $refreshToken): array
    {
        $cfg = LinkedInPublishSettings::config();
        if (!LinkedInPublishSettings::credentialsReady($cfg)) {
            throw new RuntimeException('LinkedIn-OAuth ist nicht konfiguriert.');
        }
        if ($refreshToken === '') {
            throw new RuntimeException('Kein Refresh-Token — LinkedIn erneut verbinden.');
        }
        $result = self::tokenRequest([
            'grant_type' => 'refresh_token',
            'refresh_token' => $refreshToken,
            'client_id' => (string) $cfg['client_id'],
            'client_secret' => (string) $cfg['client_secret'],
        ]);
        if (($result['refresh_token'] ?? '') === '') {
            $result['refresh_token'] = $refreshToken;
        }

        return $result;
    }

    /**
     * @return array{member_id: string, name: string}
     */
    public static function fetchMemberProfile(string $accessToken): array
    {
        $raw = self::httpJson('GET', self::USERINFO_URL, null, [
            'Authorization: Bearer ' . $accessToken,
        ]);
        $sub = (string) ($raw['sub'] ?? '');
        $name = trim((string) ($raw['name'] ?? ''));
        if ($name === '') {
            $name = trim((string) ($raw['given_name'] ?? '') . ' ' . (string) ($raw['family_name'] ?? ''));
        }
        if ($sub === '') {
            throw new RuntimeException('LinkedIn-Profil ohne Member-ID.');
        }

        return [
            'member_id' => $sub,
            'name' => $name !== '' ? $name : $sub,
        ];
    }

    /**
     * Prüft optional, ob der Member Admin der konfigurierten Organization ist.
     * Bei fehlender Org-ID oder API-Fehler: nur Hinweis, Connect nicht blockieren (Dev-Tier).
     *
     * @return array{ok: bool, message: string}
     */
    public static function verifyOrganizationAdmin(string $accessToken, string $organizationId): array
    {
        if ($organizationId === '') {
            return ['ok' => true, 'message' => 'Keine Organization-ID hinterlegt — Rollenprüfung übersprungen.'];
        }
        try {
            $url = self::ORG_ACL_URL
                . '?q=roleAssignee'
                . '&role=ADMINISTRATOR'
                . '&state=APPROVED'
                . '&count=100';
            $raw = self::httpJson('GET', $url, null, [
                'Authorization: Bearer ' . $accessToken,
                'Linkedin-Version: ' . LinkedInPublishSettings::API_VERSION,
                'X-Restli-Protocol-Version: 2.0.0',
            ]);
            $elements = $raw['elements'] ?? null;
            if (!is_array($elements)) {
                return ['ok' => true, 'message' => 'Organisationsrollen nicht prüfbar (Antwort).'];
            }
            $want = 'urn:li:organization:' . $organizationId;
            foreach ($elements as $el) {
                if (!is_array($el)) {
                    continue;
                }
                $org = (string) ($el['organization'] ?? '');
                if ($org === $want || str_ends_with($org, ':' . $organizationId)) {
                    return ['ok' => true, 'message' => 'Page-Admin bestätigt.'];
                }
            }

            return [
                'ok' => false,
                'message' => 'Verbundenes Konto ist kein Administrator der hinterlegten Unternehmensseite.',
            ];
        } catch (Throwable $e) {
            return [
                'ok' => true,
                'message' => 'Rollenprüfung übersprungen: ' . $e->getMessage(),
            ];
        }
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
            throw new RuntimeException('LinkedIn OAuth: ' . $msg);
        }

        return [
            'access_token' => $access,
            'refresh_token' => (string) ($raw['refresh_token'] ?? ''),
            'expires_in' => (int) ($raw['expires_in'] ?? 5184000),
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
            $msg = (string) ($decoded['message'] ?? $decoded['error_description'] ?? '');
            if ($msg === '' && isset($decoded['error']) && is_string($decoded['error'])) {
                $msg = $decoded['error'];
            }
            if ($msg === '') {
                $msg = 'HTTP ' . $status;
            }
            throw new RuntimeException($msg);
        }

        return $decoded;
    }
}
