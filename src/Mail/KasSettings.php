<?php
declare(strict_types=1);

/** KAS-API-Zugang (config/kas.local.php) — optional für Postfach-Anlage bei Kasserver. */
final class KasSettings
{
    /** @return array{configured: bool, kas_login: string, kas_auth_type: string, kas_auth_data: string}> */
    public static function config(): array
    {
        $path = DG_ROOT . '/config/kas.local.php';
        if (!is_readable($path)) {
            return self::empty();
        }

        $cfg = require $path;
        if (!is_array($cfg)) {
            return self::empty();
        }

        $login = trim((string) ($cfg['kas_login'] ?? ''));
        $authData = trim((string) ($cfg['kas_auth_data'] ?? ''));
        $authType = trim((string) ($cfg['kas_auth_type'] ?? 'plain'));
        if (!in_array($authType, ['plain', 'session'], true)) {
            $authType = 'plain';
        }

        return [
            'configured' => $login !== '' && $authData !== '',
            'kas_login' => $login,
            'kas_auth_type' => $authType,
            'kas_auth_data' => $authData,
        ];
    }

    public static function isConfigured(): bool
    {
        return self::config()['configured'];
    }

    /** @return array{configured: bool, kas_login: string, kas_auth_type: string, kas_auth_data: string}> */
    private static function empty(): array
    {
        return [
            'configured' => false,
            'kas_login' => '',
            'kas_auth_type' => 'plain',
            'kas_auth_data' => '',
        ];
    }
}
