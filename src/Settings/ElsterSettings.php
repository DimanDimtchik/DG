<?php
declare(strict_types=1);

/**
 * ELSTER/ERiC — Einstellungen und Betriebsmodus.
 *
 * Modus csv  = nur CSV-Export (Kasserver / ohne ERiC) — Standard
 * Modus eric = direkte Übermittlung via ERiC-Worker (nur Root-Server)
 */
final class ElsterSettings
{
    public const STORE_KEY = 'elster';

    public const MODE_CSV = 'csv';
    public const MODE_ERIC = 'eric';

    /**
     * @return array<string, mixed>
     */
    public static function defaults(): array
    {
        return [
            'mode' => self::MODE_CSV,
            'eric_test_mode' => true,
            'eric_worker_url' => '',
            'manufacturer_id' => '',
            'certificate_uploaded' => false,
            'certificate_label' => '',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function localConfig(): array
    {
        static $cached = null;
        if ($cached !== null) {
            return $cached;
        }

        $path = DG_ROOT . '/config/elster.local.php';
        if (!is_file($path)) {
            $cached = [];

            return $cached;
        }

        $config = require $path;
        $cached = is_array($config) ? $config : [];

        return $cached;
    }

    /**
     * @return array<string, mixed>
     */
    public static function forForm(): array
    {
        $stored = Database::isConfigured()
            ? SettingsStore::get(self::STORE_KEY, self::defaults())
            : self::defaults();
        $local = self::localConfig();

        $mode = self::sanitizeMode((string) ($stored['mode'] ?? self::MODE_CSV));
        if ($mode === self::MODE_ERIC && !self::serverSupportsEric()) {
            $mode = self::MODE_CSV;
        }

        return [
            'mode' => $mode,
            'eric_test_mode' => !empty($stored['eric_test_mode']),
            'eric_worker_url' => trim((string) ($stored['eric_worker_url'] ?? '')),
            'manufacturer_id' => trim((string) ($stored['manufacturer_id'] ?? '')),
            'certificate_uploaded' => !empty($stored['certificate_uploaded']),
            'certificate_label' => trim((string) ($stored['certificate_label'] ?? '')),
            'local_configured' => $local !== [],
            'eric_path' => trim((string) ($local['eric_path'] ?? '')),
            'server_supports_eric' => self::serverSupportsEric(),
            'readiness' => ElsterEricClient::readiness(),
        ];
    }

    public static function mode(): string
    {
        return (string) (self::forForm()['mode'] ?? self::MODE_CSV);
    }

    public static function isEricMode(): bool
    {
        return self::mode() === self::MODE_ERIC;
    }

    public static function isCsvMode(): bool
    {
        return self::mode() === self::MODE_CSV;
    }

    /** ERiC braucht Root/Dedicated — nicht auf Kasserver Shared Hosting. */
    public static function serverSupportsEric(): bool
    {
        $local = self::localConfig();
        if (!empty($local['force_enable_eric'])) {
            return true;
        }
        if (!empty($local['server_ready'])) {
            return true;
        }

        return false;
    }

    /**
     * @throws InvalidArgumentException
     */
    public static function saveFromPost(array $input): void
    {
        $requestedMode = self::sanitizeMode((string) ($input['elster_mode'] ?? self::MODE_CSV));
        if ($requestedMode === self::MODE_ERIC && !self::serverSupportsEric()) {
            throw new InvalidArgumentException(
                'Direkte ELSTER-Übermittlung (ERiC) ist erst nach dem Server-Umzug verfügbar. '
                . 'Siehe docs/SERVER-MIGRATION.md.'
            );
        }

        SettingsStore::set(self::STORE_KEY, [
            'mode' => $requestedMode,
            'eric_test_mode' => !empty($input['elster_eric_test_mode']),
            'eric_worker_url' => trim((string) ($input['elster_eric_worker_url'] ?? '')),
            'manufacturer_id' => trim((string) ($input['elster_manufacturer_id'] ?? '')),
            'certificate_uploaded' => !empty(SettingsStore::get(self::STORE_KEY, [])['certificate_uploaded']),
            'certificate_label' => trim((string) (SettingsStore::get(self::STORE_KEY, [])['certificate_label'] ?? '')),
        ]);
    }

    public static function sanitizeMode(string $mode): string
    {
        $mode = strtolower(trim($mode));

        return in_array($mode, [self::MODE_CSV, self::MODE_ERIC], true) ? $mode : self::MODE_CSV;
    }
}
