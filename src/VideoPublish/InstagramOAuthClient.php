<?php
declare(strict_types=1);

/** Meta / Instagram OAuth für Content Publishing (Phase 3-1). */
final class InstagramOAuthClient
{
    public static function authorizationUrl(string $state): string
    {
        $cfg = InstagramPublishSettings::config();
        if (!InstagramPublishSettings::credentialsReady($cfg)) {
            throw new RuntimeException('Instagram-OAuth ist nicht konfiguriert.');
        }
        $v = InstagramPublishSettings::GRAPH_VERSION;
        if (($cfg['login_path'] ?? '') === InstagramPublishSettings::LOGIN_INSTAGRAM) {
            $params = [
                'client_id' => (string) $cfg['app_id'],
                'redirect_uri' => InstagramPublishSettings::redirectUri(),
                'scope' => implode(',', InstagramPublishSettings::scopes($cfg)),
                'response_type' => 'code',
                'state' => $state,
            ];

            return 'https://www.instagram.com/oauth/authorize?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986);
        }

        $params = [
            'client_id' => (string) $cfg['app_id'],
            'redirect_uri' => InstagramPublishSettings::redirectUri(),
            'state' => $state,
            'scope' => implode(',', InstagramPublishSettings::scopes($cfg)),
            'response_type' => 'code',
        ];

        return 'https://www.facebook.com/' . $v . '/dialog/oauth?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986);
    }

    /**
     * @return array{
     *   access_token: string,
     *   refresh_token: string,
     *   expires_in: int,
     *   account_id: string,
     *   account_label: string,
     *   meta: array<string, mixed>
     * }
     */
    public static function completeAuthorization(string $code): array
    {
        $cfg = InstagramPublishSettings::config();
        if (!InstagramPublishSettings::credentialsReady($cfg)) {
            throw new RuntimeException('Instagram-OAuth ist nicht konfiguriert.');
        }
        if (($cfg['login_path'] ?? '') === InstagramPublishSettings::LOGIN_INSTAGRAM) {
            return self::completeInstagramLogin($code, $cfg);
        }

        return self::completeFacebookLogin($code, $cfg);
    }

    /**
     * @param array<string, mixed> $cfg
     * @return array{
     *   access_token: string,
     *   refresh_token: string,
     *   expires_in: int,
     *   account_id: string,
     *   account_label: string,
     *   meta: array<string, mixed>
     * }
     */
    private static function completeFacebookLogin(string $code, array $cfg): array
    {
        $v = InstagramPublishSettings::GRAPH_VERSION;
        $short = self::httpJson('GET', 'https://graph.facebook.com/' . $v . '/oauth/access_token?' . http_build_query([
            'client_id' => (string) $cfg['app_id'],
            'client_secret' => (string) $cfg['app_secret'],
            'redirect_uri' => InstagramPublishSettings::redirectUri(),
            'code' => $code,
        ]), null, []);
        $userToken = (string) ($short['access_token'] ?? '');
        if ($userToken === '') {
            throw new RuntimeException('Facebook OAuth: kein Access-Token.');
        }

        $long = self::httpJson('GET', 'https://graph.facebook.com/' . $v . '/oauth/access_token?' . http_build_query([
            'grant_type' => 'fb_exchange_token',
            'client_id' => (string) $cfg['app_id'],
            'client_secret' => (string) $cfg['app_secret'],
            'fb_exchange_token' => $userToken,
        ]), null, []);
        $longUser = (string) ($long['access_token'] ?? $userToken);
        $expiresIn = (int) ($long['expires_in'] ?? ($short['expires_in'] ?? 5184000));

        $me = self::httpJson('GET', 'https://graph.facebook.com/' . $v . '/me?' . http_build_query([
            'fields' => 'id,name',
            'access_token' => $longUser,
        ]), null, []);

        $pages = self::httpJson('GET', 'https://graph.facebook.com/' . $v . '/me/accounts?' . http_build_query([
            'fields' => 'id,name,access_token,instagram_business_account{id,username}',
            'access_token' => $longUser,
        ]), null, []);
        $data = is_array($pages['data'] ?? null) ? $pages['data'] : [];
        $picked = self::pickFacebookPage($data, $cfg);
        if ($picked === null) {
            throw new RuntimeException(
                'Keine Facebook-Page mit verknüpftem Instagram-Professional-Konto gefunden. '
                . 'Page verknüpfen oder Page-/IG-ID in den Einstellungen setzen.'
            );
        }

        $ig = is_array($picked['instagram_business_account'] ?? null)
            ? $picked['instagram_business_account']
            : [];
        $igUserId = (string) ($ig['id'] ?? '');
        $igUser = trim((string) ($ig['username'] ?? ''));
        if ($igUserId === '') {
            throw new RuntimeException('Page ohne Instagram Business Account-ID.');
        }
        $wantIg = (string) ($cfg['ig_user_id'] ?? '');
        if ($wantIg !== '' && $wantIg !== $igUserId) {
            throw new RuntimeException(
                'Verbundene IG-User-ID (' . $igUserId . ') weicht von der hinterlegten ab (' . $wantIg . ').'
            );
        }

        $pageToken = (string) ($picked['access_token'] ?? '');
        if ($pageToken === '') {
            throw new RuntimeException('Kein Page-Access-Token.');
        }
        $pageName = trim((string) ($picked['name'] ?? ''));
        $label = $igUser !== '' ? '@' . $igUser : ($pageName !== '' ? $pageName : $igUserId);

        return [
            'access_token' => $pageToken,
            'refresh_token' => $longUser,
            'expires_in' => max(60, $expiresIn),
            'account_id' => $igUserId,
            'account_label' => $label,
            'meta' => [
                'login_path' => InstagramPublishSettings::LOGIN_FACEBOOK,
                'facebook_user_id' => (string) ($me['id'] ?? ''),
                'facebook_user_name' => (string) ($me['name'] ?? ''),
                'facebook_page_id' => (string) ($picked['id'] ?? ''),
                'facebook_page_name' => $pageName,
                'ig_username' => $igUser,
                'graph_version' => $v,
            ],
        ];
    }

    /**
     * @param list<mixed> $pages
     * @param array<string, mixed> $cfg
     * @return array<string, mixed>|null
     */
    private static function pickFacebookPage(array $pages, array $cfg): ?array
    {
        $wantPage = (string) ($cfg['facebook_page_id'] ?? '');
        $wantIg = (string) ($cfg['ig_user_id'] ?? '');
        $candidates = [];
        foreach ($pages as $page) {
            if (!is_array($page)) {
                continue;
            }
            $ig = $page['instagram_business_account'] ?? null;
            if (!is_array($ig) || (string) ($ig['id'] ?? '') === '') {
                continue;
            }
            $candidates[] = $page;
            $pageId = (string) ($page['id'] ?? '');
            $igId = (string) ($ig['id'] ?? '');
            if ($wantPage !== '' && $pageId === $wantPage) {
                return $page;
            }
            if ($wantIg !== '' && $igId === $wantIg) {
                return $page;
            }
        }
        if ($wantPage !== '' || $wantIg !== '') {
            return null;
        }

        return $candidates[0] ?? null;
    }

    /**
     * @param array<string, mixed> $cfg
     * @return array{
     *   access_token: string,
     *   refresh_token: string,
     *   expires_in: int,
     *   account_id: string,
     *   account_label: string,
     *   meta: array<string, mixed>
     * }
     */
    private static function completeInstagramLogin(string $code, array $cfg): array
    {
        $token = self::httpJson('POST', 'https://api.instagram.com/oauth/access_token', http_build_query([
            'client_id' => (string) $cfg['app_id'],
            'client_secret' => (string) $cfg['app_secret'],
            'grant_type' => 'authorization_code',
            'redirect_uri' => InstagramPublishSettings::redirectUri(),
            'code' => $code,
        ]), ['Content-Type: application/x-www-form-urlencoded']);

        $short = (string) ($token['access_token'] ?? '');
        $userId = (string) ($token['user_id'] ?? '');
        if ($short === '' || $userId === '') {
            // Manche Antworten liefern data[0]
            $data0 = is_array($token['data'][0] ?? null) ? $token['data'][0] : null;
            if (is_array($data0)) {
                $short = (string) ($data0['access_token'] ?? $short);
                $userId = (string) ($data0['user_id'] ?? $userId);
            }
        }
        if ($short === '') {
            throw new RuntimeException('Instagram OAuth: kein Access-Token.');
        }

        $long = self::httpJson('GET', 'https://graph.instagram.com/access_token?' . http_build_query([
            'grant_type' => 'ig_exchange_token',
            'client_secret' => (string) $cfg['app_secret'],
            'access_token' => $short,
        ]), null, []);
        $longToken = (string) ($long['access_token'] ?? $short);
        $expiresIn = (int) ($long['expires_in'] ?? 5184000);

        $me = self::httpJson('GET', 'https://graph.instagram.com/me?' . http_build_query([
            'fields' => 'id,username',
            'access_token' => $longToken,
        ]), null, []);
        $igUserId = (string) ($me['id'] ?? $userId);
        $username = trim((string) ($me['username'] ?? ''));
        if ($igUserId === '') {
            throw new RuntimeException('Instagram-Profil ohne User-ID.');
        }
        $wantIg = (string) ($cfg['ig_user_id'] ?? '');
        if ($wantIg !== '' && $wantIg !== $igUserId) {
            throw new RuntimeException(
                'Verbundene IG-User-ID (' . $igUserId . ') weicht von der hinterlegten ab (' . $wantIg . ').'
            );
        }

        return [
            'access_token' => $longToken,
            'refresh_token' => '',
            'expires_in' => max(60, $expiresIn),
            'account_id' => $igUserId,
            'account_label' => $username !== '' ? '@' . $username : $igUserId,
            'meta' => [
                'login_path' => InstagramPublishSettings::LOGIN_INSTAGRAM,
                'ig_username' => $username,
                'graph_version' => InstagramPublishSettings::GRAPH_VERSION,
            ],
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
            $errObj = is_array($decoded['error'] ?? null) ? $decoded['error'] : [];
            $msg = (string) ($errObj['message'] ?? $decoded['error_message'] ?? $decoded['error_description'] ?? '');
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
