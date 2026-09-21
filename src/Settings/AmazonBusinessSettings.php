<?php
declare(strict_types=1);

/** Amazon Business API — Credentials & Schalter (SettingsStore `amazon_business`). */
final class AmazonBusinessSettings
{
    public const STORE_KEY = 'amazon_business';

    public const REGION_EU = 'EU';
    public const REGION_NA = 'NA';
    public const REGION_FE = 'FE';

    /**
     * @return array<string, mixed>
     */
    public static function defaults(): array
    {
        return [
            'enabled' => false,
            'region' => self::REGION_EU,
            'client_id' => '',
            'client_secret' => '',
            'refresh_token' => '',
            'user_email' => '',
            'max_order_amount' => 500.0,
            'access_token' => '',
            'access_token_expires_at' => 0,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function config(): array
    {
        $stored = SettingsStore::get(self::STORE_KEY, self::defaults());
        $defaults = self::defaults();

        return [
            'enabled' => !empty($stored['enabled']),
            'region' => self::sanitizeRegion((string) ($stored['region'] ?? $defaults['region'])),
            'client_id' => trim((string) ($stored['client_id'] ?? '')),
            'client_secret' => (string) ($stored['client_secret'] ?? ''),
            'refresh_token' => (string) ($stored['refresh_token'] ?? ''),
            'user_email' => trim((string) ($stored['user_email'] ?? '')),
            'max_order_amount' => max(0.0, round((float) ($stored['max_order_amount'] ?? $defaults['max_order_amount']), 2)),
            'access_token' => (string) ($stored['access_token'] ?? ''),
            'access_token_expires_at' => (int) ($stored['access_token_expires_at'] ?? 0),
        ];
    }

    /**
     * Formularwerte — Secrets nur als „gesetzt“-Hinweis, nicht im Klartext.
     *
     * @return array<string, mixed>
     */
    public static function forForm(): array
    {
        $cfg = self::config();

        return [
            'enabled' => !empty($cfg['enabled']),
            'region' => (string) $cfg['region'],
            'client_id' => (string) $cfg['client_id'],
            'client_secret_set' => trim((string) $cfg['client_secret']) !== '',
            'refresh_token_set' => trim((string) $cfg['refresh_token']) !== '',
            'user_email' => (string) $cfg['user_email'],
            'max_order_amount' => (float) $cfg['max_order_amount'],
            'ready' => self::isReady($cfg),
            'status_label' => self::statusLabel($cfg),
        ];
    }

    /**
     * @param array<string, mixed>|null $cfg
     */
    public static function isReady(?array $cfg = null): bool
    {
        $cfg ??= self::config();
        if (empty($cfg['enabled'])) {
            return false;
        }

        return trim((string) ($cfg['client_id'] ?? '')) !== ''
            && trim((string) ($cfg['client_secret'] ?? '')) !== ''
            && trim((string) ($cfg['refresh_token'] ?? '')) !== '';
    }

    /**
     * @param array<string, mixed> $cfg
     */
    public static function statusLabel(array $cfg): string
    {
        if (empty($cfg['enabled'])) {
            return 'Deaktiviert';
        }
        if (!self::isReady($cfg)) {
            return 'Aktiv, aber Credentials unvollständig';
        }

        return 'Bereit (Credentials gesetzt)';
    }

    /**
     * @param array<string, mixed> $input
     */
    public static function saveFromPost(array $input): void
    {
        $current = self::config();
        $clientSecret = trim((string) ($input['amazon_client_secret'] ?? ''));
        $refreshToken = trim((string) ($input['amazon_refresh_token'] ?? ''));

        SettingsStore::set(self::STORE_KEY, [
            'enabled' => !empty($input['amazon_enabled']),
            'region' => self::sanitizeRegion((string) ($input['amazon_region'] ?? self::REGION_EU)),
            'client_id' => mb_substr(trim((string) ($input['amazon_client_id'] ?? '')), 0, 255),
            'client_secret' => $clientSecret !== '' ? $clientSecret : (string) $current['client_secret'],
            'refresh_token' => $refreshToken !== '' ? $refreshToken : (string) $current['refresh_token'],
            'user_email' => mb_substr(trim((string) ($input['amazon_user_email'] ?? '')), 0, 255),
            'max_order_amount' => max(0.0, round((float) str_replace(',', '.', (string) ($input['amazon_max_order_amount'] ?? '500')), 2)),
            // Token-Cache bei Credential-Änderung leeren
            'access_token' => '',
            'access_token_expires_at' => 0,
        ]);
    }

    /**
     * @param array{access_token: string, expires_at: int} $token
     */
    public static function storeAccessToken(string $accessToken, int $expiresAt): void
    {
        $cfg = self::config();
        $cfg['access_token'] = $accessToken;
        $cfg['access_token_expires_at'] = $expiresAt;
        SettingsStore::set(self::STORE_KEY, $cfg);
    }

    public static function sanitizeRegion(string $region): string
    {
        $region = strtoupper(trim($region));

        return in_array($region, [self::REGION_EU, self::REGION_NA, self::REGION_FE], true)
            ? $region
            : self::REGION_EU;
    }

    /** API-Host für Ordering / Product APIs. */
    public static function apiBaseUrl(?string $region = null): string
    {
        $region = self::sanitizeRegion($region ?? (string) self::config()['region']);

        return match ($region) {
            self::REGION_NA => 'https://na.business-api.amazon.com',
            self::REGION_FE => 'https://jp.business-api.amazon.com',
            default => 'https://eu.business-api.amazon.com',
        };
    }
}
