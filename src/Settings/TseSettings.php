<?php
declare(strict_types=1);

/**
 * TSE (Technische Sicherheitseinrichtung) — Zugangsdaten und Betriebsmodus.
 *
 * Secrets können in der Instanz-DB liegen oder aus der Umgebung kommen
 * (FISKALY_SIGN_DE_API_KEY / _SECRET, TSE_Test_ID / TSE_TSS_ID, TSE_TEST_Client_ID / TSE_CLIENT_ID).
 */
final class TseSettings
{
    public const STORE_KEY = 'tse';
    public const ENV_TEST = 'test';
    public const ENV_LIVE = 'live';

    public const TEST_BASE_URL = 'https://kassensichv-middleware.fiskaly.com/api/v2';
    public const LIVE_BASE_URL = 'https://kassensichv.fiskaly.com/api/v2';

    /**
     * @return array<string, mixed>
     */
    public static function defaults(): array
    {
        return [
            'enabled' => false,
            'environment' => self::ENV_TEST,
            'api_key' => '',
            'api_secret' => '',
            'tss_id' => '',
            'client_id' => '',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function stored(): array
    {
        if (!class_exists('SettingsStore') || !Database::isConfigured()) {
            return self::defaults();
        }

        try {
            return SettingsStore::get(self::STORE_KEY, self::defaults());
        } catch (Throwable) {
            return self::defaults();
        }
    }

    /**
     * Wirksame Konfiguration: gespeicherte Werte, leere Felder aus der Umgebung.
     *
     * @return array{
     *   enabled: bool,
     *   environment: string,
     *   api_key: string,
     *   api_secret: string,
     *   tss_id: string,
     *   client_id: string,
     *   base_url: string
     * }
     */
    public static function effective(): array
    {
        $stored = self::stored();
        $env = self::sanitizeEnvironment((string) ($stored['environment'] ?? self::ENV_TEST));
        $apiKey = trim((string) ($stored['api_key'] ?? ''));
        $apiSecret = trim((string) ($stored['api_secret'] ?? ''));
        $tssId = self::sanitizeUuid((string) ($stored['tss_id'] ?? ''));
        $clientId = self::sanitizeUuid((string) ($stored['client_id'] ?? ''));

        if ($apiKey === '') {
            $apiKey = trim((string) (getenv('FISKALY_SIGN_DE_API_KEY') ?: ''));
        }
        if ($apiSecret === '') {
            $apiSecret = trim((string) (getenv('FISKALY_SIGN_DE_API_SECRET') ?: ''));
        }
        if ($tssId === '') {
            $tssId = self::sanitizeUuid((string) (getenv('TSE_TSS_ID') ?: getenv('TSE_Test_ID') ?: ''));
        }
        if ($clientId === '') {
            $clientId = self::sanitizeUuid((string) (getenv('TSE_CLIENT_ID') ?: getenv('TSE_TEST_Client_ID') ?: ''));
        }

        return [
            'enabled' => !empty($stored['enabled']),
            'environment' => $env,
            'api_key' => $apiKey,
            'api_secret' => $apiSecret,
            'tss_id' => $tssId,
            'client_id' => $clientId,
            'base_url' => $env === self::ENV_LIVE ? self::LIVE_BASE_URL : self::TEST_BASE_URL,
        ];
    }

    /**
     * Formularwerte — Secrets nur als gesetzt/nicht gesetzt.
     *
     * @return array<string, mixed>
     */
    public static function forForm(): array
    {
        $eff = self::effective();
        $stored = self::stored();

        return [
            'enabled' => !empty($eff['enabled']),
            'environment' => $eff['environment'],
            'api_key_set' => trim((string) $eff['api_key']) !== '',
            'api_secret_set' => trim((string) $eff['api_secret']) !== '',
            'tss_id' => (string) $eff['tss_id'],
            'client_id' => (string) $eff['client_id'],
            'ready' => self::isReady($eff),
            'from_env' => [
                'api_key' => trim((string) ($stored['api_key'] ?? '')) === '' && trim((string) $eff['api_key']) !== '',
                'api_secret' => trim((string) ($stored['api_secret'] ?? '')) === '' && trim((string) $eff['api_secret']) !== '',
                'tss_id' => self::sanitizeUuid((string) ($stored['tss_id'] ?? '')) === '' && (string) $eff['tss_id'] !== '',
                'client_id' => self::sanitizeUuid((string) ($stored['client_id'] ?? '')) === '' && (string) $eff['client_id'] !== '',
            ],
        ];
    }

    /**
     * @param array<string, mixed>|null $cfg
     */
    public static function isReady(?array $cfg = null): bool
    {
        $cfg ??= self::effective();

        return trim((string) ($cfg['api_key'] ?? '')) !== ''
            && trim((string) ($cfg['api_secret'] ?? '')) !== ''
            && self::sanitizeUuid((string) ($cfg['tss_id'] ?? '')) !== ''
            && self::sanitizeUuid((string) ($cfg['client_id'] ?? '')) !== '';
    }

    public static function isEnabled(): bool
    {
        return !empty(self::effective()['enabled']);
    }

    /**
     * @throws InvalidArgumentException
     */
    public static function saveFromPost(array $input): void
    {
        if (!class_exists('SettingsStore') || !Database::isConfigured()) {
            throw new InvalidArgumentException('Datenbank nicht verbunden.');
        }

        $current = self::stored();
        $enabled = !empty($input['tse_enabled']);
        $environment = self::sanitizeEnvironment((string) ($input['tse_environment'] ?? self::ENV_TEST));

        $apiKey = trim((string) ($input['tse_api_key'] ?? ''));
        if ($apiKey === '') {
            $apiKey = trim((string) ($current['api_key'] ?? ''));
        }

        $apiSecret = (string) ($input['tse_api_secret'] ?? '');
        if (trim($apiSecret) === '') {
            $apiSecret = (string) ($current['api_secret'] ?? '');
        } else {
            $apiSecret = trim($apiSecret);
        }

        $tssId = self::sanitizeUuid((string) ($input['tse_tss_id'] ?? ''));
        $clientId = self::sanitizeUuid((string) ($input['tse_client_id'] ?? ''));

        $next = [
            'enabled' => $enabled,
            'environment' => $environment,
            'api_key' => $apiKey,
            'api_secret' => $apiSecret,
            'tss_id' => $tssId,
            'client_id' => $clientId,
        ];

        if ($enabled) {
            $check = self::effective();
            foreach (['api_key', 'api_secret', 'tss_id', 'client_id', 'environment'] as $key) {
                if (trim((string) ($next[$key] ?? '')) !== '') {
                    $check[$key] = $next[$key];
                }
            }
            $check['enabled'] = true;
            if (!self::isReady($check)) {
                throw new InvalidArgumentException(
                    'TSE einschalten braucht API-Schlüssel, API-Geheimnis, TSE-ID und Kassen-ID.'
                );
            }
        }

        SettingsStore::set(self::STORE_KEY, $next);
    }

    public static function sanitizeEnvironment(string $env): string
    {
        $env = strtolower(trim($env));

        return $env === self::ENV_LIVE ? self::ENV_LIVE : self::ENV_TEST;
    }

    public static function sanitizeUuid(string $value): string
    {
        $value = strtolower(trim($value));
        if ($value === '') {
            return '';
        }
        if (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/', $value)) {
            return '';
        }

        return $value;
    }
}
