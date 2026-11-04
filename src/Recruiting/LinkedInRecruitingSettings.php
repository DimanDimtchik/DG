<?php
declare(strict_types=1);

/**
 * LinkedIn Job Posting API — nur nach App-Review / Partner-Freigabe.
 * OAuth2 Client Credentials; Credentials + Firmen-Seiten-ID pro Instanz.
 *
 * Hinweis LinkedIn: neue Job-Posting-Partnerschaften sind derzeit eingeschränkt;
 * Zugang nur nach Freigabe (Talent Solutions / Apply Connect).
 */
final class LinkedInRecruitingSettings
{
    public const STORE_KEY = 'linkedin_recruiting';

    public const API_VERSION = '202604';

    /**
     * @return array<string, mixed>
     */
    public static function defaults(): array
    {
        return [
            'enabled' => false,
            'app_review_approved' => false,
            'auto_publish_queued' => false,
            'listing_type' => 'BASIC',
            'company_id' => '',
            'client_id' => '',
            'client_secret' => '',
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
        $listing = strtoupper(trim((string) ($stored['listing_type'] ?? 'BASIC')));
        if (!in_array($listing, ['BASIC', 'PREMIUM'], true)) {
            $listing = 'BASIC';
        }

        return [
            'enabled' => !empty($stored['enabled']),
            'app_review_approved' => !empty($stored['app_review_approved']),
            'auto_publish_queued' => !empty($stored['auto_publish_queued']),
            'listing_type' => $listing,
            'company_id' => preg_replace('/\D+/', '', (string) ($stored['company_id'] ?? '')) ?? '',
            'client_id' => trim((string) ($stored['client_id'] ?? '')),
            'client_secret' => (string) ($stored['client_secret'] ?? ''),
            'access_token' => (string) ($stored['access_token'] ?? ''),
            'access_token_expires_at' => (int) ($stored['access_token_expires_at'] ?? 0),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function forForm(): array
    {
        $cfg = self::config();

        return [
            'enabled' => !empty($cfg['enabled']),
            'app_review_approved' => !empty($cfg['app_review_approved']),
            'auto_publish_queued' => !empty($cfg['auto_publish_queued']),
            'listing_type' => (string) $cfg['listing_type'],
            'company_id' => (string) $cfg['company_id'],
            'client_id' => (string) $cfg['client_id'],
            'client_secret_set' => trim((string) $cfg['client_secret']) !== '',
            'token_cached' => trim((string) $cfg['access_token']) !== ''
                && (int) $cfg['access_token_expires_at'] > time() + 60,
            'ready' => self::isReady($cfg),
            'credentials_ready' => self::hasCredentials($cfg),
            'status_label' => self::statusLabel($cfg),
            'company_urn' => self::companyUrn($cfg),
        ];
    }

    /**
     * @param array<string, mixed>|null $cfg
     */
    public static function isReady(?array $cfg = null): bool
    {
        $cfg ??= self::config();
        if (empty($cfg['enabled']) || empty($cfg['app_review_approved'])) {
            return false;
        }

        return self::hasCredentials($cfg) && self::companyId($cfg) !== '';
    }

    /**
     * @param array<string, mixed>|null $cfg
     */
    public static function hasCredentials(?array $cfg = null): bool
    {
        $cfg ??= self::config();

        return trim((string) ($cfg['client_id'] ?? '')) !== ''
            && trim((string) ($cfg['client_secret'] ?? '')) !== '';
    }

    /**
     * @param array<string, mixed>|null $cfg
     */
    public static function companyId(?array $cfg = null): string
    {
        $cfg ??= self::config();

        return preg_replace('/\D+/', '', (string) ($cfg['company_id'] ?? '')) ?? '';
    }

    /**
     * @param array<string, mixed>|null $cfg
     */
    public static function companyUrn(?array $cfg = null): string
    {
        $id = self::companyId($cfg);

        return $id !== '' ? 'urn:li:company:' . $id : '';
    }

    /**
     * @param array<string, mixed> $cfg
     */
    public static function statusLabel(array $cfg): string
    {
        if (empty($cfg['enabled'])) {
            return 'Deaktiviert';
        }
        if (empty($cfg['app_review_approved'])) {
            return 'Aktiv, aber App-Review noch nicht bestätigt';
        }
        if (self::companyId($cfg) === '') {
            return 'Freigabe gesetzt, Firmen-Seiten-ID fehlt';
        }
        if (!self::hasCredentials($cfg)) {
            return 'Freigabe gesetzt, OAuth-Credentials unvollständig';
        }

        return 'Bereit (App-Review bestätigt)';
    }

    /**
     * @param array<string, mixed> $input
     */
    public static function saveFromPost(array $input): void
    {
        $current = self::config();
        $clientSecret = trim((string) ($input['linkedin_client_secret'] ?? ''));
        $listing = strtoupper(trim((string) ($input['linkedin_listing_type'] ?? 'BASIC')));
        if (!in_array($listing, ['BASIC', 'PREMIUM'], true)) {
            $listing = 'BASIC';
        }

        SettingsStore::set(self::STORE_KEY, [
            'enabled' => !empty($input['linkedin_enabled']),
            'app_review_approved' => !empty($input['linkedin_app_review_approved']),
            'auto_publish_queued' => !empty($input['linkedin_auto_publish_queued']),
            'listing_type' => $listing,
            'company_id' => mb_substr(preg_replace('/\D+/', '', (string) ($input['linkedin_company_id'] ?? '')) ?? '', 0, 32),
            'client_id' => mb_substr(trim((string) ($input['linkedin_client_id'] ?? '')), 0, 255),
            'client_secret' => $clientSecret !== '' ? $clientSecret : (string) $current['client_secret'],
            // Token-Cache bei Credential-Änderung leeren
            'access_token' => '',
            'access_token_expires_at' => 0,
        ]);
    }

    public static function storeAccessToken(string $accessToken, int $expiresAt): void
    {
        $cfg = self::config();
        $cfg['access_token'] = $accessToken;
        $cfg['access_token_expires_at'] = $expiresAt;
        SettingsStore::set(self::STORE_KEY, $cfg);
    }
}
