<?php
declare(strict_types=1);

/**
 * Xing E-Recruiting API — nur mit Kunden-Jobvertrag (organization_id + order_id).
 * OAuth 1.0a (PLAINTEXT) laut Xing-Vendor-Beispielen; Credentials pro Instanz.
 */
final class XingRecruitingSettings
{
    public const STORE_KEY = 'xing_recruiting';

    /** Öffentliche Test-IDs von Xing (Postings nur 1 Tag aktiv). */
    public const TEST_ORGANIZATION_ID = '5160';
    public const TEST_ORDER_ID_CORE = '1205255';

    /**
     * @return array<string, mixed>
     */
    public static function defaults(): array
    {
        return [
            'enabled' => false,
            'use_test_contract' => false,
            'auto_activate' => true,
            'auto_publish_queued' => false,
            'organization_id' => '',
            'order_id' => '',
            'company_name' => '',
            'company_profile_url' => '',
            'consumer_key' => '',
            'consumer_secret' => '',
            'access_token' => '',
            'access_token_secret' => '',
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
            'use_test_contract' => !empty($stored['use_test_contract']),
            'auto_activate' => array_key_exists('auto_activate', $stored)
                ? !empty($stored['auto_activate'])
                : !empty($defaults['auto_activate']),
            'auto_publish_queued' => !empty($stored['auto_publish_queued']),
            'organization_id' => trim((string) ($stored['organization_id'] ?? '')),
            'order_id' => trim((string) ($stored['order_id'] ?? '')),
            'company_name' => trim((string) ($stored['company_name'] ?? '')),
            'company_profile_url' => trim((string) ($stored['company_profile_url'] ?? '')),
            'consumer_key' => trim((string) ($stored['consumer_key'] ?? '')),
            'consumer_secret' => (string) ($stored['consumer_secret'] ?? ''),
            'access_token' => (string) ($stored['access_token'] ?? ''),
            'access_token_secret' => (string) ($stored['access_token_secret'] ?? ''),
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
            'use_test_contract' => !empty($cfg['use_test_contract']),
            'auto_activate' => !empty($cfg['auto_activate']),
            'auto_publish_queued' => !empty($cfg['auto_publish_queued']),
            'organization_id' => (string) $cfg['organization_id'],
            'order_id' => (string) $cfg['order_id'],
            'company_name' => (string) $cfg['company_name'],
            'company_profile_url' => (string) $cfg['company_profile_url'],
            'consumer_key' => (string) $cfg['consumer_key'],
            'consumer_secret_set' => trim((string) $cfg['consumer_secret']) !== '',
            'access_token_set' => trim((string) $cfg['access_token']) !== '',
            'access_token_secret_set' => trim((string) $cfg['access_token_secret']) !== '',
            'effective_organization_id' => self::effectiveOrganizationId($cfg),
            'effective_order_id' => self::effectiveOrderId($cfg),
            'ready' => self::isReady($cfg),
            'contract_ready' => self::hasContract($cfg),
            'oauth_ready' => self::hasOAuth($cfg),
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

        return self::hasContract($cfg) && self::hasOAuth($cfg);
    }

    /**
     * Kunden-Jobvertrag (oder explizite Test-IDs).
     *
     * @param array<string, mixed>|null $cfg
     */
    public static function hasContract(?array $cfg = null): bool
    {
        $cfg ??= self::config();
        $org = self::effectiveOrganizationId($cfg);
        $order = self::effectiveOrderId($cfg);

        return $org !== '' && ctype_digit($org) && $order !== '' && ctype_digit($order);
    }

    /**
     * @param array<string, mixed>|null $cfg
     */
    public static function hasOAuth(?array $cfg = null): bool
    {
        $cfg ??= self::config();

        return trim((string) ($cfg['consumer_key'] ?? '')) !== ''
            && trim((string) ($cfg['consumer_secret'] ?? '')) !== ''
            && trim((string) ($cfg['access_token'] ?? '')) !== ''
            && trim((string) ($cfg['access_token_secret'] ?? '')) !== '';
    }

    /**
     * @param array<string, mixed> $cfg
     */
    public static function effectiveOrganizationId(array $cfg): string
    {
        if (!empty($cfg['use_test_contract'])) {
            return self::TEST_ORGANIZATION_ID;
        }

        return preg_replace('/\D+/', '', (string) ($cfg['organization_id'] ?? '')) ?? '';
    }

    /**
     * @param array<string, mixed> $cfg
     */
    public static function effectiveOrderId(array $cfg): string
    {
        if (!empty($cfg['use_test_contract'])) {
            return self::TEST_ORDER_ID_CORE;
        }

        return preg_replace('/\D+/', '', (string) ($cfg['order_id'] ?? '')) ?? '';
    }

    /**
     * @param array<string, mixed> $cfg
     */
    public static function statusLabel(array $cfg): string
    {
        if (empty($cfg['enabled'])) {
            return 'Deaktiviert';
        }
        if (!self::hasContract($cfg)) {
            return 'Aktiv, aber ohne Jobvertrag (organization_id / order_id)';
        }
        if (!self::hasOAuth($cfg)) {
            return 'Vertrag gesetzt, OAuth-Credentials unvollständig';
        }
        if (!empty($cfg['use_test_contract'])) {
            return 'Bereit (Test-Vertrag Xing)';
        }

        return 'Bereit (Kunden-Jobvertrag)';
    }

    public static function resolveCompanyName(?array $cfg = null): string
    {
        $cfg ??= self::config();
        $name = trim((string) ($cfg['company_name'] ?? ''));
        if ($name !== '') {
            return mb_substr($name, 0, 255);
        }
        try {
            $company = CompanySettings::config();
            $name = trim((string) ($company['name'] ?? ''));
        } catch (Throwable) {
            $name = '';
        }

        return $name !== '' ? mb_substr($name, 0, 255) : 'Arbeitgeber';
    }

    /**
     * @param array<string, mixed> $input
     */
    public static function saveFromPost(array $input): void
    {
        $current = self::config();
        $consumerSecret = trim((string) ($input['xing_consumer_secret'] ?? ''));
        $accessToken = trim((string) ($input['xing_access_token'] ?? ''));
        $accessSecret = trim((string) ($input['xing_access_token_secret'] ?? ''));

        SettingsStore::set(self::STORE_KEY, [
            'enabled' => !empty($input['xing_enabled']),
            'use_test_contract' => !empty($input['xing_use_test_contract']),
            'auto_activate' => !empty($input['xing_auto_activate']),
            'auto_publish_queued' => !empty($input['xing_auto_publish_queued']),
            'organization_id' => mb_substr(preg_replace('/\D+/', '', (string) ($input['xing_organization_id'] ?? '')) ?? '', 0, 32),
            'order_id' => mb_substr(preg_replace('/\D+/', '', (string) ($input['xing_order_id'] ?? '')) ?? '', 0, 32),
            'company_name' => mb_substr(trim((string) ($input['xing_company_name'] ?? '')), 0, 255),
            'company_profile_url' => mb_substr(trim((string) ($input['xing_company_profile_url'] ?? '')), 0, 500),
            'consumer_key' => mb_substr(trim((string) ($input['xing_consumer_key'] ?? '')), 0, 255),
            'consumer_secret' => $consumerSecret !== '' ? $consumerSecret : (string) $current['consumer_secret'],
            'access_token' => $accessToken !== '' ? $accessToken : (string) $current['access_token'],
            'access_token_secret' => $accessSecret !== '' ? $accessSecret : (string) $current['access_token_secret'],
        ]);
    }
}
