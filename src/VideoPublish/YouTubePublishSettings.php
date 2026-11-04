<?php
declare(strict_types=1);

/** GCP OAuth-Client für YouTube Data API (pro Instanz). */
final class YouTubePublishSettings
{
    public const STORE_KEY = 'youtube_publish';

    public const SCOPE_UPLOAD = 'https://www.googleapis.com/auth/youtube.upload';
    public const SCOPE_FORCE_SSL = 'https://www.googleapis.com/auth/youtube.force-ssl';

    /**
     * @return array<string, mixed>
     */
    public static function defaults(): array
    {
        return [
            'enabled' => false,
            'client_id' => '',
            'client_secret' => '',
            'oauth_mode' => 'testing',
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
        $mode = (string) ($stored['oauth_mode'] ?? 'testing');
        if (!in_array($mode, ['testing', 'production'], true)) {
            $mode = 'testing';
        }

        return [
            'enabled' => !empty($stored['enabled']),
            'client_id' => trim((string) ($stored['client_id'] ?? '')),
            'client_secret' => (string) ($stored['client_secret'] ?? ''),
            'oauth_mode' => $mode,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function forForm(): array
    {
        $cfg = self::config();
        $conn = OAuthConnectionRepository::findByPlatform(OAuthConnectionRepository::PLATFORM_YOUTUBE);

        return [
            'enabled' => !empty($cfg['enabled']),
            'client_id' => (string) $cfg['client_id'],
            'client_secret_set' => trim((string) $cfg['client_secret']) !== '',
            'oauth_mode' => (string) $cfg['oauth_mode'],
            'redirect_uri' => self::redirectUri(),
            'scopes' => implode(' ', self::scopes()),
            'credentials_ready' => self::credentialsReady($cfg),
            'connected' => OAuthConnectionRepository::isConnected(OAuthConnectionRepository::PLATFORM_YOUTUBE),
            'channel_id' => (string) ($conn['account_id'] ?? ''),
            'channel_label' => (string) ($conn['account_label'] ?? ''),
            'connection_status' => (string) ($conn['status'] ?? OAuthConnectionRepository::STATUS_DISCONNECTED),
            'connected_at' => (string) ($conn['connected_at'] ?? ''),
            'status_label' => self::statusLabel($cfg, $conn),
        ];
    }

    /**
     * @return list<string>
     */
    public static function scopes(): array
    {
        return [self::SCOPE_UPLOAD, self::SCOPE_FORCE_SSL];
    }

    public static function redirectUri(): string
    {
        $base = class_exists('App') ? rtrim(App::publicBaseUrl(), '/') : '';

        return $base . '/oauth/youtube/callback';
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
     * @param array<string, mixed> $cfg
     * @param array<string, mixed>|null $conn
     */
    public static function statusLabel(array $cfg, ?array $conn): string
    {
        if (empty($cfg['enabled'])) {
            return 'Ausgeschaltet';
        }
        if (!self::credentialsReady($cfg)) {
            return 'GCP Client-ID/Secret fehlen';
        }
        $status = (string) ($conn['status'] ?? '');
        if ($status === OAuthConnectionRepository::STATUS_CONNECTED) {
            $label = trim((string) ($conn['account_label'] ?? ''));

            return $label !== '' ? 'Verbunden: ' . $label : 'Mit YouTube verbunden';
        }
        if ($status === OAuthConnectionRepository::STATUS_REAUTH) {
            return 'Erneut verbinden erforderlich';
        }

        return 'Bereit — noch nicht verbunden';
    }

    /**
     * @param array<string, mixed> $input
     */
    public static function saveFromPost(array $input): void
    {
        $current = self::config();
        $secret = trim((string) ($input['youtube_client_secret'] ?? ''));
        $mode = (string) ($input['youtube_oauth_mode'] ?? 'testing');
        if (!in_array($mode, ['testing', 'production'], true)) {
            $mode = 'testing';
        }

        SettingsStore::set(self::STORE_KEY, [
            'enabled' => !empty($input['youtube_enabled']) ? 1 : 0,
            'client_id' => trim((string) ($input['youtube_client_id'] ?? '')),
            'client_secret' => $secret !== '' ? $secret : (string) $current['client_secret'],
            'oauth_mode' => $mode,
        ]);
    }
}
