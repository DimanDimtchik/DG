<?php
declare(strict_types=1);

final class App
{
    private static ?array $config = null;

    public static function boot(): void
    {
        date_default_timezone_set(self::config('timezone', 'Europe/Berlin'));

        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_name((string) self::config('session_name', 'dg_crm_session'));
            session_start();
        }
    }

    public static function config(?string $key = null, mixed $default = null): mixed
    {
        if (self::$config === null) {
            self::$config = require DG_ROOT . '/config/app.php';
        }

        if ($key === null) {
            return self::$config;
        }

        return self::$config[$key] ?? $default;
    }

    public static function reloadConfig(): void
    {
        self::$config = null;
    }

    public static function publicBaseUrl(): string
    {
        $configured = trim((string) self::config('public_url', ''));
        if ($configured !== '') {
            return rtrim($configured, '/');
        }

        $host = trim((string) ($_SERVER['HTTP_HOST'] ?? ''));
        if ($host === '') {
            return '';
        }

        $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https');

        return ($https ? 'https' : 'http') . '://' . $host;
    }
}
