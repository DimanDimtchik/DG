<?php
declare(strict_types=1);

/**
 * LDAP-Authentifizierung (Client) — Vorbereitung für externen Verzeichnisdienst.
 *
 * @see docs/LDAP-INTEGRATION.md
 */
final class LdapAuthenticator
{
    /**
     * @return array{
     *   ready: bool,
     *   items: list<array{id: string, label: string, ok: bool, detail: string}>
     * }
     */
    public static function readiness(): array
    {
        $local = LdapSettings::localConfig();
        $items = [];

        $items[] = [
            'id' => 'extension',
            'label' => 'PHP-Extension ldap',
            'ok' => extension_loaded('ldap'),
            'detail' => extension_loaded('ldap')
                ? 'ldap_* Funktionen verfügbar.'
                : 'Auf Kasserver meist nicht installiert — nach Server-Umzug prüfen.',
        ];

        $items[] = [
            'id' => 'local_config',
            'label' => 'config/ldap.local.php',
            'ok' => $local !== [],
            'detail' => $local !== []
                ? 'Lokale LDAP-Konfiguration vorhanden.'
                : 'Datei aus config/ldap.local.php.example anlegen.',
        ];

        $host = trim((string) ($local['host'] ?? ''));
        $items[] = [
            'id' => 'host',
            'label' => 'LDAP-Host konfiguriert',
            'ok' => $host !== '',
            'detail' => $host !== '' ? $host : 'host in ldap.local.php setzen.',
        ];

        $enabled = !empty($local['enabled']);
        $items[] = [
            'id' => 'enabled',
            'label' => 'LDAP aktiviert (enabled=true)',
            'ok' => $enabled,
            'detail' => $enabled ? 'enabled=true in ldap.local.php.' : 'Erst nach Test auf Root-Server aktivieren.',
        ];

        $connectOk = false;
        if ($enabled && extension_loaded('ldap') && $host !== '') {
            $connectOk = self::testConnection($local) === null;
        }
        $items[] = [
            'id' => 'connect',
            'label' => 'LDAP-Verbindung (Ping)',
            'ok' => $connectOk,
            'detail' => $connectOk
                ? 'Verbindung zum LDAP-Host möglich.'
                : 'Noch nicht verbunden (normal auf Kasserver ohne LDAP-Server).',
        ];

        $ready = true;
        foreach ($items as $item) {
            if (!$item['ok']) {
                $ready = false;
            }
        }

        return ['ready' => $ready, 'items' => $items];
    }

    /**
     * Authentifiziert gegen LDAP. Liefert normalisiertes Benutzerprofil oder null.
     *
     * @return array{
     *   username: string,
     *   email: string,
     *   display_name: string,
     *   external_id: string,
     *   role: string,
     *   employee_active: bool
     * }|null
     */
    public static function authenticate(string $identifier, string $password): ?array
    {
        if (!LdapSettings::isLdapEnabled() || $identifier === '' || $password === '') {
            return null;
        }
        if (!extension_loaded('ldap')) {
            return null;
        }

        $config = LdapSettings::mergedConfig();
        $connection = self::connect($config);
        if ($connection === null) {
            return null;
        }

        try {
            $entry = self::findUserEntry($connection, $config, $identifier);
            if ($entry === null) {
                return null;
            }

            $userDn = (string) ($entry['dn'] ?? '');
            if ($userDn === '') {
                return null;
            }

            if (!@ldap_bind($connection, $userDn, $password)) {
                return null;
            }

            return self::mapEntryToProfile($entry, $config);
        } finally {
            @ldap_unbind($connection);
        }
    }

    /**
     * @param array<string, mixed> $config
     */
    private static function testConnection(array $config): ?string
    {
        $connection = self::connect($config);
        if ($connection === null) {
            return 'Verbindung fehlgeschlagen.';
        }
        @ldap_unbind($connection);

        return null;
    }

    /**
     * @param array<string, mixed> $config
     * @return \LDAP\Connection|resource|null
     */
    private static function connect(array $config)
    {
        $host = trim((string) ($config['host'] ?? ''));
        if ($host === '') {
            return null;
        }

        $port = (int) ($config['port'] ?? 389);
        $uri = (!empty($config['use_ssl']) ? 'ldaps://' : 'ldap://') . $host . ':' . $port;

        $connection = @ldap_connect($uri);
        if ($connection === false) {
            return null;
        }

        @ldap_set_option($connection, LDAP_OPT_PROTOCOL_VERSION, 3);
        @ldap_set_option($connection, LDAP_OPT_REFERRALS, 0);
        $timeout = max(1, (int) ($config['timeout_seconds'] ?? 5));
        @ldap_set_option($connection, LDAP_OPT_NETWORK_TIMEOUT, $timeout);

        if (!empty($config['use_tls']) && empty($config['use_ssl'])) {
            if (!@ldap_start_tls($connection)) {
                return null;
            }
        }

        $bindDn = trim((string) ($config['bind_dn'] ?? ''));
        $bindPassword = (string) ($config['bind_password'] ?? '');
        if ($bindDn !== '') {
            if (!@ldap_bind($connection, $bindDn, $bindPassword)) {
                return null;
            }
        } elseif (!@ldap_bind($connection)) {
            return null;
        }

        return $connection;
    }

    /**
     * @param array<string, mixed> $config
     * @param \LDAP\Connection|resource $connection
     * @return array<string, mixed>|null
     */
    private static function findUserEntry($connection, array $config, string $identifier): ?array
    {
        $baseDn = trim((string) ($config['base_dn'] ?? ''));
        $filterTemplate = trim((string) ($config['user_filter'] ?? ''));
        if ($baseDn === '' || $filterTemplate === '') {
            return null;
        }

        $escaped = self::escapeFilter($identifier);
        $filter = str_replace('%s', $escaped, $filterTemplate);
        if (substr_count($filterTemplate, '%s') >= 2) {
            $filter = preg_replace('/%s/', $escaped, $filterTemplate, 2) ?? $filter;
        }

        $search = @ldap_search($connection, $baseDn, $filter, ['*', '+']);
        if ($search === false) {
            return null;
        }

        $entries = @ldap_get_entries($connection, $search);
        if (!is_array($entries) || ($entries['count'] ?? 0) < 1) {
            return null;
        }

        $entry = $entries[0];
        $entry['dn'] = @ldap_get_dn($connection, $search) ?: ($entry['dn'] ?? '');

        return is_array($entry) ? $entry : null;
    }

    /**
     * @param array<string, mixed> $entry
     * @param array<string, mixed> $config
     * @return array{
     *   username: string,
     *   email: string,
     *   display_name: string,
     *   external_id: string,
     *   role: string,
     *   employee_active: bool
     * }
     */
    private static function mapEntryToProfile(array $entry, array $config): array
    {
        $map = is_array($config['attribute_map'] ?? null) ? $config['attribute_map'] : [];

        $username = self::firstAttribute($entry, (string) ($map['username'] ?? 'uid'));
        $email = self::firstAttribute($entry, (string) ($map['email'] ?? 'mail'));
        $displayName = self::firstAttribute($entry, (string) ($map['display_name'] ?? 'cn'));
        $externalId = self::firstAttribute($entry, (string) ($map['external_id'] ?? 'entryUUID'));
        if ($externalId === '') {
            $externalId = (string) ($entry['dn'] ?? $username);
        }

        if ($username === '') {
            $username = strstr($email, '@', true) ?: $externalId;
        }
        if ($displayName === '') {
            $displayName = $username;
        }

        $role = self::resolveRole($entry, $config);

        return [
            'username' => $username,
            'email' => $email,
            'display_name' => $displayName,
            'external_id' => $externalId,
            'role' => $role,
            'employee_active' => !empty($config['default_employee_active']),
        ];
    }

    /**
     * @param array<string, mixed> $entry
     * @param array<string, mixed> $config
     */
    private static function resolveRole(array $entry, array $config): string
    {
        $groupMap = is_array($config['group_role_map'] ?? null) ? $config['group_role_map'] : [];
        $memberOf = $entry['memberof'] ?? [];
        if (is_string($memberOf)) {
            $memberOf = [$memberOf];
        }
        if (is_array($memberOf) && isset($memberOf['count'])) {
            unset($memberOf['count']);
        }

        foreach ($groupMap as $groupKey => $role) {
            $needle = strtolower((string) $groupKey);
            foreach ((array) $memberOf as $groupDn) {
                if (is_string($groupDn) && stripos($groupDn, $needle) !== false) {
                    return (string) $role;
                }
            }
        }

        return trim((string) ($config['default_role'] ?? 'dg_eigenmitarbeiter'));
    }

    /**
     * @param array<string, mixed> $entry
     */
    private static function firstAttribute(array $entry, string $attribute): string
    {
        if ($attribute === '') {
            return '';
        }
        $key = strtolower($attribute);
        if (!isset($entry[$key])) {
            return '';
        }
        $value = $entry[$key];
        if (is_array($value)) {
            return trim((string) ($value[0] ?? ''));
        }

        return trim((string) $value);
    }

    private static function escapeFilter(string $value): string
    {
        if (function_exists('ldap_escape')) {
            return ldap_escape($value, '', LDAP_ESCAPE_FILTER);
        }

        return addcslashes($value, "\0*()\\");
    }
}
