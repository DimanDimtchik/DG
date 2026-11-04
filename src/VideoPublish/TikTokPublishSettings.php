<?php
declare(strict_types=1);

/**
 * TikTok Content Posting — Phase 4-1: App-Credentials, Posting-Modus, OAuth-Connect.
 * Upload folgt in 4-2 (Inbox zuerst; Direct nur nach Audit).
 */
final class TikTokPublishSettings
{
    public const STORE_KEY = 'tiktok_publish';

    public const MODE_INBOX = 'inbox';
    public const MODE_DIRECT = 'direct';

    public const SCOPE_USER_INFO_BASIC = 'user.info.basic';
    public const SCOPE_USER_INFO_STATS = 'user.info.stats';
    public const SCOPE_VIDEO_LIST = 'video.list';
    public const SCOPE_VIDEO_UPLOAD = 'video.upload';
    public const SCOPE_VIDEO_PUBLISH = 'video.publish';

    /**
     * @return array<string, mixed>
     */
    public static function defaults(): array
    {
        return [
            'enabled' => false,
            'client_key' => '',
            'client_secret' => '',
            'posting_mode' => self::MODE_INBOX,
            'audit_ok' => false,
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
        $mode = (string) ($stored['posting_mode'] ?? self::MODE_INBOX);
        if (!in_array($mode, [self::MODE_INBOX, self::MODE_DIRECT], true)) {
            $mode = self::MODE_INBOX;
        }
        $auditOk = !empty($stored['audit_ok']);
        // Direct ohne Audit-Flag → Inbox (Policy / CRM-Schutz).
        if ($mode === self::MODE_DIRECT && !$auditOk) {
            $mode = self::MODE_INBOX;
        }

        return [
            'enabled' => !empty($stored['enabled']),
            'client_key' => trim((string) ($stored['client_key'] ?? '')),
            'client_secret' => (string) ($stored['client_secret'] ?? ''),
            'posting_mode' => $mode,
            'audit_ok' => $auditOk,
            'app_review_note' => trim((string) ($stored['app_review_note'] ?? '')),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function forForm(): array
    {
        $cfg = self::config();
        $conn = OAuthConnectionRepository::findByPlatform(OAuthConnectionRepository::PLATFORM_TIKTOK);
        $storedMode = self::storedPostingMode();

        return [
            'enabled' => !empty($cfg['enabled']),
            'client_key' => (string) $cfg['client_key'],
            'client_secret_set' => trim((string) $cfg['client_secret']) !== '',
            'posting_mode' => $storedMode,
            'effective_mode' => (string) $cfg['posting_mode'],
            'audit_ok' => !empty($cfg['audit_ok']),
            'app_review_note' => (string) $cfg['app_review_note'],
            'redirect_uri' => self::redirectUri(),
            'scopes' => implode(',', self::scopes($cfg)),
            'credentials_ready' => self::credentialsReady($cfg),
            'connected' => OAuthConnectionRepository::isConnected(OAuthConnectionRepository::PLATFORM_TIKTOK),
            'account_id' => (string) ($conn['account_id'] ?? ''),
            'account_label' => (string) ($conn['account_label'] ?? ''),
            'connection_status' => (string) ($conn['status'] ?? OAuthConnectionRepository::STATUS_DISCONNECTED),
            'connected_at' => (string) ($conn['connected_at'] ?? ''),
            'status_label' => self::statusLabel($cfg, $conn),
            'direct_blocked' => $storedMode === self::MODE_DIRECT && empty($cfg['audit_ok']),
        ];
    }

    /**
     * @param array<string, mixed>|null $cfg
     * @return list<string>
     */
    public static function scopes(?array $cfg = null): array
    {
        $cfg ??= self::config();
        $scopes = [
            self::SCOPE_USER_INFO_BASIC,
            self::SCOPE_USER_INFO_STATS,
            self::SCOPE_VIDEO_LIST,
        ];
        if (($cfg['posting_mode'] ?? self::MODE_INBOX) === self::MODE_DIRECT
            && !empty($cfg['audit_ok'])
        ) {
            $scopes[] = self::SCOPE_VIDEO_PUBLISH;
        } else {
            $scopes[] = self::SCOPE_VIDEO_UPLOAD;
        }

        return $scopes;
    }

    public static function redirectUri(): string
    {
        $base = class_exists('App') ? rtrim(App::publicBaseUrl(), '/') : '';

        return $base . '/oauth/tiktok/callback';
    }

    /**
     * @param array<string, mixed>|null $cfg
     */
    public static function credentialsReady(?array $cfg = null): bool
    {
        $cfg ??= self::config();

        return !empty($cfg['enabled'])
            && trim((string) ($cfg['client_key'] ?? '')) !== ''
            && trim((string) ($cfg['client_secret'] ?? '')) !== '';
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
            return 'Client Key/Secret fehlen';
        }
        $mode = (string) ($cfg['posting_mode'] ?? self::MODE_INBOX);
        $modeLabel = $mode === self::MODE_DIRECT
            ? 'Direct Post (Audit)'
            : 'Inbox / Draft';
        $audit = !empty($cfg['audit_ok']) ? 'Audit ok' : 'ohne Audit';
        $status = (string) ($conn['status'] ?? '');
        if ($status === OAuthConnectionRepository::STATUS_CONNECTED) {
            $label = trim((string) ($conn['account_label'] ?? ''));
            $base = $label !== '' ? 'Verbunden: ' . $label : 'Mit TikTok verbunden';

            return $base . ' · ' . $modeLabel . ' · ' . $audit;
        }
        if ($status === OAuthConnectionRepository::STATUS_REAUTH) {
            return 'Erneut verbinden erforderlich · ' . $modeLabel . ' · ' . $audit;
        }

        return 'Bereit — noch nicht verbunden · ' . $modeLabel . ' · ' . $audit;
    }

    /**
     * @param array<string, mixed> $input
     */
    public static function saveFromPost(array $input): void
    {
        $current = self::config();
        $secret = trim((string) ($input['tiktok_publish_client_secret'] ?? ''));
        $mode = (string) ($input['tiktok_publish_posting_mode'] ?? self::MODE_INBOX);
        if (!in_array($mode, [self::MODE_INBOX, self::MODE_DIRECT], true)) {
            $mode = self::MODE_INBOX;
        }

        SettingsStore::set(self::STORE_KEY, [
            'enabled' => !empty($input['tiktok_publish_enabled']) ? 1 : 0,
            'client_key' => trim((string) ($input['tiktok_publish_client_key'] ?? '')),
            'client_secret' => $secret !== '' ? $secret : (string) $current['client_secret'],
            'posting_mode' => $mode,
            'audit_ok' => !empty($input['tiktok_publish_audit_ok']) ? 1 : 0,
            'app_review_note' => mb_substr(trim((string) ($input['tiktok_publish_app_review_note'] ?? '')), 0, 2000),
        ]);
    }

    private static function storedPostingMode(): string
    {
        if (!class_exists('SettingsStore') || !Database::isConfigured()) {
            return self::MODE_INBOX;
        }
        $stored = SettingsStore::get(self::STORE_KEY, self::defaults());
        $mode = (string) ($stored['posting_mode'] ?? self::MODE_INBOX);

        return in_array($mode, [self::MODE_INBOX, self::MODE_DIRECT], true)
            ? $mode
            : self::MODE_INBOX;
    }
}
