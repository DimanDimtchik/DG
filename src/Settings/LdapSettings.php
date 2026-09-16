<?php
declare(strict_types=1);

/**
 * LDAP — Einstellungen und Betriebsmodus (Vorbereitung bis Server-Umzug).
 */
final class LdapSettings
{
    public const STORE_KEY = 'ldap';

    public const MODE_LOCAL = 'local';
    public const MODE_HYBRID = 'hybrid';
    public const MODE_LDAP_ONLY = 'ldap_only';

    /**
     * @return array<string, mixed>
     */
    public static function defaults(): array
    {
        return [
            'mode' => self::MODE_LOCAL,
            'jit_provision' => true,
            'default_role' => 'dg_eigenmitarbeiter',
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

        $path = DG_ROOT . '/config/ldap.local.php';
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

        $mode = self::sanitizeMode((string) ($stored['mode'] ?? self::MODE_LOCAL));
        if ($mode !== self::MODE_LOCAL && !self::serverSupportsLdap()) {
            $mode = self::MODE_LOCAL;
        }

        return [
            'mode' => $mode,
            'jit_provision' => !empty($stored['jit_provision']),
            'default_role' => trim((string) ($stored['default_role'] ?? 'dg_eigenmitarbeiter')),
            'local_configured' => $local !== [],
            'local_enabled' => !empty($local['enabled']),
            'server_supports_ldap' => self::serverSupportsLdap(),
            'readiness' => LdapAuthenticator::readiness(),
            'host' => trim((string) ($local['host'] ?? '')),
            'wordpress_base_url' => trim((string) ($local['wordpress_base_url'] ?? '')),
        ];
    }

    public static function mode(): string
    {
        return (string) (self::forForm()['mode'] ?? self::MODE_LOCAL);
    }

    public static function isLdapEnabled(): bool
    {
        if (self::mode() === self::MODE_LOCAL) {
            return false;
        }

        return self::serverSupportsLdap() && !empty(self::localConfig()['enabled']);
    }

    public static function ldapOnly(): bool
    {
        return self::isLdapEnabled() && self::mode() === self::MODE_LDAP_ONLY;
    }

    /** LDAP-Client braucht php-ldap und Konfiguration — nicht auf Kasserver ohne Extension. */
    public static function serverSupportsLdap(): bool
    {
        $local = self::localConfig();
        if (empty($local['enabled'])) {
            return false;
        }

        return extension_loaded('ldap') && trim((string) ($local['host'] ?? '')) !== '';
    }

    /**
     * @throws InvalidArgumentException
     */
    public static function saveFromPost(array $input): void
    {
        $requestedMode = self::sanitizeMode((string) ($input['ldap_mode'] ?? self::MODE_LOCAL));
        if ($requestedMode !== self::MODE_LOCAL && !self::serverSupportsLdap()) {
            throw new InvalidArgumentException(
                'LDAP ist auf diesem Server noch nicht verfügbar. '
                . 'config/ldap.local.php anlegen, php-ldap installieren — siehe docs/LDAP-INTEGRATION.md.'
            );
        }

        $defaultRole = CrmRole::normalize((string) ($input['ldap_default_role'] ?? 'dg_eigenmitarbeiter'));
        if (!CrmRole::isValid($defaultRole)) {
            throw new InvalidArgumentException('Ungültige Standard-Rolle für LDAP-Benutzer.');
        }

        SettingsStore::set(self::STORE_KEY, [
            'mode' => $requestedMode,
            'jit_provision' => !empty($input['ldap_jit_provision']),
            'default_role' => $defaultRole,
        ]);
    }

    public static function sanitizeMode(string $mode): string
    {
        $mode = strtolower(trim($mode));

        return in_array($mode, [self::MODE_LOCAL, self::MODE_HYBRID, self::MODE_LDAP_ONLY], true)
            ? $mode
            : self::MODE_LOCAL;
    }

    /**
     * @return array<string, mixed>
     */
    public static function mergedConfig(): array
    {
        $local = self::localConfig();
        $stored = Database::isConfigured()
            ? SettingsStore::get(self::STORE_KEY, self::defaults())
            : self::defaults();

        return array_merge($local, [
            'mode' => self::mode(),
            'jit_provision' => !empty($stored['jit_provision']),
            'default_role' => trim((string) ($stored['default_role'] ?? ($local['default_role'] ?? 'dg_eigenmitarbeiter'))),
        ]);
    }
}
