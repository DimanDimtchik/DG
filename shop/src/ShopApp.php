<?php

declare(strict_types=1);

final class ShopApp
{
    private static bool $booted = false;

    /** @var array<string, mixed> */
    private static array $config = [];

    public static function boot(): void
    {
        if (self::$booted) {
            return;
        }
        self::$booted = true;
        self::$config = require SHOP_ROOT . '/config/app.php';
        date_default_timezone_set('Europe/Berlin');
        ShopMaintenance::guard();
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
    }

    public static function config(?string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return self::$config;
        }

        return self::$config[$key] ?? $default;
    }

    public static function baseUrl(): string
    {
        $configured = trim((string) self::config('base_url', ''));
        if ($configured !== '') {
            return rtrim($configured, '/');
        }
        $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || ((string) ($_SERVER['SERVER_PORT'] ?? '') === '443');
        $host = (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');

        return ($https ? 'https' : 'http') . '://' . $host;
    }

    /** @return 'square'|'wide'|'tall' */
    public static function logoShape(): string
    {
        $rel = ltrim((string) self::config('logo', '/assets/img/logo.png'), '/');
        $path = SHOP_ROOT . '/' . $rel;
        if (!is_file($path)) {
            return 'wide';
        }
        $size = @getimagesize($path);
        if (!is_array($size) || ($size[0] ?? 0) < 1 || ($size[1] ?? 0) < 1) {
            return 'wide';
        }
        $ratio = $size[0] / $size[1];
        if ($ratio >= 1.25) {
            return 'wide';
        }
        if ($ratio <= 0.85) {
            return 'tall';
        }

        return 'square';
    }
}
