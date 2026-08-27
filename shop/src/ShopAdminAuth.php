<?php

declare(strict_types=1);

/** Einfacher Admin-Zugang für Shop-Einstellungen (Wartungsmodus). */
final class ShopAdminAuth
{
    private const SESSION_KEY = 'shop_admin_ok';

    public static function isConfigured(): bool
    {
        $hash = self::passwordHash();

        return $hash !== '';
    }

    public static function check(): bool
    {
        return !empty($_SESSION[self::SESSION_KEY]);
    }

    public static function attempt(string $password): bool
    {
        $hash = self::passwordHash();
        if ($hash === '') {
            return false;
        }
        if (!password_verify($password, $hash)) {
            return false;
        }
        $_SESSION[self::SESSION_KEY] = true;

        return true;
    }

    public static function requireLogin(): void
    {
        if (self::check()) {
            return;
        }
        header('Location: /admin/login?return=' . rawurlencode((string) ($_SERVER['REQUEST_URI'] ?? '/admin/wartung')), true, 302);
        exit;
    }

    public static function logout(): void
    {
        unset($_SESSION[self::SESSION_KEY]);
    }

    private static function passwordHash(): string
    {
        $local = SHOP_ROOT . '/config/admin.local.php';
        if (!is_readable($local)) {
            return '';
        }
        $cfg = require $local;
        if (!is_array($cfg)) {
            return '';
        }

        return trim((string) ($cfg['password_hash'] ?? ''));
    }
}
