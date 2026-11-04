<?php
declare(strict_types=1);

/**
 * Instagram / Meta — Video-Publish (Reels).
 * Phase 3-1: App-Credentials, Access-Stufe, OAuth-Connect; Upload folgt in 3-2.
 */
final class InstagramPublishSettings
{
    public const STORE_KEY = 'instagram_publish';

    public const LOGIN_FACEBOOK = 'facebook';
    public const LOGIN_INSTAGRAM = 'instagram';

    public const ACCESS_STANDARD = 'standard';
    public const ACCESS_ADVANCED = 'advanced';

    public const GRAPH_VERSION = 'v21.0';

    /** Facebook-Login-Pfad (Page + IG Professional). */
    public const SCOPE_FB_IG_BASIC = 'instagram_basic';
    public const SCOPE_FB_CONTENT_PUBLISH = 'instagram_content_publish';
    public const SCOPE_FB_PAGES_SHOW = 'pages_show_list';
    public const SCOPE_FB_PAGES_ENGAGE = 'pages_read_engagement';
    public const SCOPE_FB_BUSINESS = 'business_management';

    /** Instagram-Login-Pfad. */
    public const SCOPE_IG_BASIC = 'instagram_business_basic';
    public const SCOPE_IG_CONTENT_PUBLISH = 'instagram_business_content_publish';

    /**
     * @return array<string, mixed>
     */
    public static function defaults(): array
    {
        return [
            'enabled' => false,
            'app_id' => '',
            'app_secret' => '',
            'login_path' => self::LOGIN_FACEBOOK,
            'access_level' => self::ACCESS_STANDARD,
            'facebook_page_id' => '',
            'ig_user_id' => '',
            'app_review_note' => '',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function config(): array
    {
        if (!class_exists('SettingsStore') || !Database::isConfigured()) {
            return self::defaults();
        }
        $stored = SettingsStore::get(self::STORE_KEY, self::defaults());
        $login = (string) ($stored['login_path'] ?? self::LOGIN_FACEBOOK);
        if (!in_array($login, [self::LOGIN_FACEBOOK, self::LOGIN_INSTAGRAM], true)) {
            $login = self::LOGIN_FACEBOOK;
        }
        $access = (string) ($stored['access_level'] ?? self::ACCESS_STANDARD);
        if (!in_array($access, [self::ACCESS_STANDARD, self::ACCESS_ADVANCED], true)) {
            $access = self::ACCESS_STANDARD;
        }
        $pageId = preg_replace('/\D+/', '', (string) ($stored['facebook_page_id'] ?? '')) ?? '';
        $igId = preg_replace('/\D+/', '', (string) ($stored['ig_user_id'] ?? '')) ?? '';

        return [
            'enabled' => !empty($stored['enabled']),
            'app_id' => trim((string) ($stored['app_id'] ?? '')),
            'app_secret' => (string) ($stored['app_secret'] ?? ''),
            'login_path' => $login,
            'access_level' => $access,
            'facebook_page_id' => $pageId,
            'ig_user_id' => $igId,
            'app_review_note' => trim((string) ($stored['app_review_note'] ?? '')),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function forForm(): array
    {
        $cfg = self::config();
        $conn = OAuthConnectionRepository::findByPlatform(OAuthConnectionRepository::PLATFORM_INSTAGRAM);

        return [
            'enabled' => !empty($cfg['enabled']),
            'app_id' => (string) $cfg['app_id'],
            'app_secret_set' => trim((string) $cfg['app_secret']) !== '',
            'login_path' => (string) $cfg['login_path'],
            'access_level' => (string) $cfg['access_level'],
            'facebook_page_id' => (string) $cfg['facebook_page_id'],
            'ig_user_id' => (string) $cfg['ig_user_id'],
            'app_review_note' => (string) $cfg['app_review_note'],
            'redirect_uri' => self::redirectUri(),
            'scopes' => implode(',', self::scopes($cfg)),
            'graph_version' => self::GRAPH_VERSION,
            'credentials_ready' => self::credentialsReady($cfg),
            'connected' => OAuthConnectionRepository::isConnected(OAuthConnectionRepository::PLATFORM_INSTAGRAM),
            'account_id' => (string) ($conn['account_id'] ?? ''),
            'account_label' => (string) ($conn['account_label'] ?? ''),
            'connection_status' => (string) ($conn['status'] ?? OAuthConnectionRepository::STATUS_DISCONNECTED),
            'connected_at' => (string) ($conn['connected_at'] ?? ''),
            'status_label' => self::statusLabel($cfg, $conn),
        ];
    }

    /**
     * @param array<string, mixed>|null $cfg
     * @return list<string>
     */
    public static function scopes(?array $cfg = null): array
    {
        $cfg ??= self::config();
        if (($cfg['login_path'] ?? '') === self::LOGIN_INSTAGRAM) {
            return [
                self::SCOPE_IG_BASIC,
                self::SCOPE_IG_CONTENT_PUBLISH,
            ];
        }

        return [
            self::SCOPE_FB_IG_BASIC,
            self::SCOPE_FB_CONTENT_PUBLISH,
            self::SCOPE_FB_PAGES_SHOW,
            self::SCOPE_FB_PAGES_ENGAGE,
            self::SCOPE_FB_BUSINESS,
        ];
    }

    public static function redirectUri(): string
    {
        $base = class_exists('App') ? rtrim(App::publicBaseUrl(), '/') : '';

        return $base . '/oauth/instagram/callback';
    }

    /**
     * @param array<string, mixed>|null $cfg
     */
    public static function credentialsReady(?array $cfg = null): bool
    {
        $cfg ??= self::config();

        return !empty($cfg['enabled'])
            && trim((string) ($cfg['app_id'] ?? '')) !== ''
            && trim((string) ($cfg['app_secret'] ?? '')) !== '';
    }

    /**
     * @param array<string, mixed> $cfg
     * @param array<string, mixed>|null $conn
     */
    public static function statusLabel(array $cfg, ?array $conn): string
    {
        if (empty($cfg['enabled'])) {
            return 'Ausgeschaltet';
        }
        if (!self::credentialsReady($cfg)) {
            return 'App-ID/Secret fehlen';
        }
        $access = (string) ($cfg['access_level'] ?? self::ACCESS_STANDARD);
        $accessLabel = $access === self::ACCESS_ADVANCED
            ? 'Advanced Access'
            : 'Standard Access';
        $login = (string) ($cfg['login_path'] ?? self::LOGIN_FACEBOOK) === self::LOGIN_INSTAGRAM
            ? 'IG-Login'
            : 'FB-Login';
        $status = (string) ($conn['status'] ?? '');
        if ($status === OAuthConnectionRepository::STATUS_CONNECTED) {
            $label = trim((string) ($conn['account_label'] ?? ''));
            $base = $label !== '' ? 'Verbunden: ' . $label : 'Mit Instagram verbunden';

            return $base . ' · ' . $login . ' · ' . $accessLabel;
        }
        if ($status === OAuthConnectionRepository::STATUS_REAUTH) {
            return 'Erneut verbinden erforderlich · ' . $login . ' · ' . $accessLabel;
        }

        return 'Bereit — noch nicht verbunden · ' . $login . ' · ' . $accessLabel;
    }

    /**
     * @param array<string, mixed> $input
     */
    public static function saveFromPost(array $input): void
    {
        $current = self::config();
        $secret = trim((string) ($input['instagram_publish_app_secret'] ?? ''));
        $login = (string) ($input['instagram_publish_login_path'] ?? self::LOGIN_FACEBOOK);
        if (!in_array($login, [self::LOGIN_FACEBOOK, self::LOGIN_INSTAGRAM], true)) {
            $login = self::LOGIN_FACEBOOK;
        }
        $access = (string) ($input['instagram_publish_access_level'] ?? self::ACCESS_STANDARD);
        if (!in_array($access, [self::ACCESS_STANDARD, self::ACCESS_ADVANCED], true)) {
            $access = self::ACCESS_STANDARD;
        }
        $pageId = preg_replace('/\D+/', '', (string) ($input['instagram_publish_facebook_page_id'] ?? '')) ?? '';
        $igId = preg_replace('/\D+/', '', (string) ($input['instagram_publish_ig_user_id'] ?? '')) ?? '';

        SettingsStore::set(self::STORE_KEY, [
            'enabled' => !empty($input['instagram_publish_enabled']) ? 1 : 0,
            'app_id' => trim((string) ($input['instagram_publish_app_id'] ?? '')),
            'app_secret' => $secret !== '' ? $secret : (string) $current['app_secret'],
            'login_path' => $login,
            'access_level' => $access,
            'facebook_page_id' => $pageId,
            'ig_user_id' => $igId,
            'app_review_note' => mb_substr(trim((string) ($input['instagram_publish_app_review_note'] ?? '')), 0, 2000),
        ]);
    }
}
