<?php
declare(strict_types=1);

/**
 * LinkedIn Community Management — Video-Publish (getrennt vom Stellenexport).
 * Phase 2-1: Credentials + Review-Status; Upload folgt in 2-2.
 */
final class LinkedInPublishSettings
{
    public const STORE_KEY = 'linkedin_publish';

    public const SCOPE_OPENID = 'openid';
    public const SCOPE_PROFILE = 'profile';
    public const SCOPE_ORG_WRITE = 'w_organization_social';
    public const SCOPE_ORG_READ = 'r_organization_social';
    /** Comments/Reactions (Community Management Feed). */
    public const SCOPE_ORG_FEED_WRITE = 'w_organization_social_feed';
    public const SCOPE_ORG_FEED_READ = 'r_organization_social_feed';

    /** API-Version Header für REST-Calls (YYYYMM). */
    public const API_VERSION = '202610';

    /**
     * @return array<string, mixed>
     */
    public static function defaults(): array
    {
        return [
            'enabled' => false,
            'client_id' => '',
            'client_secret' => '',
            'organization_id' => '',
            'review_tier' => 'none',
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
        $tier = (string) ($stored['review_tier'] ?? 'none');
        if (!in_array($tier, ['none', 'development', 'standard'], true)) {
            $tier = 'none';
        }
        $orgId = preg_replace('/\D+/', '', (string) ($stored['organization_id'] ?? '')) ?? '';

        return [
            'enabled' => !empty($stored['enabled']),
            'client_id' => trim((string) ($stored['client_id'] ?? '')),
            'client_secret' => (string) ($stored['client_secret'] ?? ''),
            'organization_id' => $orgId,
            'review_tier' => $tier,
            'app_review_note' => trim((string) ($stored['app_review_note'] ?? '')),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function forForm(): array
    {
        $cfg = self::config();
        $conn = OAuthConnectionRepository::findByPlatform(OAuthConnectionRepository::PLATFORM_LINKEDIN);

        return [
            'enabled' => !empty($cfg['enabled']),
            'client_id' => (string) $cfg['client_id'],
            'client_secret_set' => trim((string) $cfg['client_secret']) !== '',
            'organization_id' => (string) $cfg['organization_id'],
            'organization_urn' => self::organizationUrn($cfg),
            'review_tier' => (string) $cfg['review_tier'],
            'app_review_note' => (string) $cfg['app_review_note'],
            'redirect_uri' => self::redirectUri(),
            'scopes' => implode(' ', self::scopes()),
            'credentials_ready' => self::credentialsReady($cfg),
            'connected' => OAuthConnectionRepository::isConnected(OAuthConnectionRepository::PLATFORM_LINKEDIN),
            'account_id' => (string) ($conn['account_id'] ?? ''),
            'account_label' => (string) ($conn['account_label'] ?? ''),
            'connection_status' => (string) ($conn['status'] ?? OAuthConnectionRepository::STATUS_DISCONNECTED),
            'connected_at' => (string) ($conn['connected_at'] ?? ''),
            'status_label' => self::statusLabel($cfg, $conn),
            'api_version' => self::API_VERSION,
        ];
    }

    /**
     * @return list<string>
     */
    public static function scopes(): array
    {
        return [
            self::SCOPE_OPENID,
            self::SCOPE_PROFILE,
            self::SCOPE_ORG_WRITE,
            self::SCOPE_ORG_READ,
            self::SCOPE_ORG_FEED_WRITE,
            self::SCOPE_ORG_FEED_READ,
        ];
    }

    public static function redirectUri(): string
    {
        $base = class_exists('App') ? rtrim(App::publicBaseUrl(), '/') : '';

        return $base . '/oauth/linkedin/callback';
    }

    /**
     * @param array<string, mixed>|null $cfg
     */
    public static function credentialsReady(?array $cfg = null): bool
    {
        $cfg ??= self::config();

        return !empty($cfg['enabled'])
            && trim((string) ($cfg['client_id'] ?? '')) !== ''
            && trim((string) ($cfg['client_secret'] ?? '')) !== '';
    }

    /**
     * @param array<string, mixed>|null $cfg
     */
    public static function organizationUrn(?array $cfg = null): string
    {
        $cfg ??= self::config();
        $id = (string) ($cfg['organization_id'] ?? '');

        return $id !== '' ? 'urn:li:organization:' . $id : '';
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
            return 'Client-ID/Secret fehlen';
        }
        $tier = (string) ($cfg['review_tier'] ?? 'none');
        $tierLabel = match ($tier) {
            'standard' => 'Standard Tier',
            'development' => 'Development Tier',
            default => 'Review noch offen',
        };
        $status = (string) ($conn['status'] ?? '');
        if ($status === OAuthConnectionRepository::STATUS_CONNECTED) {
            $label = trim((string) ($conn['account_label'] ?? ''));
            $base = $label !== '' ? 'Verbunden: ' . $label : 'Mit LinkedIn verbunden';

            return $base . ' · ' . $tierLabel;
        }
        if ($status === OAuthConnectionRepository::STATUS_REAUTH) {
            return 'Erneut verbinden erforderlich · ' . $tierLabel;
        }

        return 'Bereit — noch nicht verbunden · ' . $tierLabel;
    }

    /**
     * @param array<string, mixed> $input
     */
    public static function saveFromPost(array $input): void
    {
        $current = self::config();
        $secret = trim((string) ($input['linkedin_publish_client_secret'] ?? ''));
        $tier = (string) ($input['linkedin_publish_review_tier'] ?? 'none');
        if (!in_array($tier, ['none', 'development', 'standard'], true)) {
            $tier = 'none';
        }
        $orgId = preg_replace('/\D+/', '', (string) ($input['linkedin_publish_organization_id'] ?? '')) ?? '';

        SettingsStore::set(self::STORE_KEY, [
            'enabled' => !empty($input['linkedin_publish_enabled']) ? 1 : 0,
            'client_id' => trim((string) ($input['linkedin_publish_client_id'] ?? '')),
            'client_secret' => $secret !== '' ? $secret : (string) $current['client_secret'],
            'organization_id' => $orgId,
            'review_tier' => $tier,
            'app_review_note' => mb_substr(trim((string) ($input['linkedin_publish_app_review_note'] ?? '')), 0, 2000),
        ]);
    }
}
