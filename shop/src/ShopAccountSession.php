<?php

declare(strict_types=1);

final class ShopAccountSession
{
    private const KEY = 'shop_saas_token';

    public static function token(): ?string
    {
        $t = $_SESSION[self::KEY] ?? null;
        return is_string($t) && $t !== '' ? $t : null;
    }

    public static function setToken(string $token): void
    {
        $_SESSION[self::KEY] = $token;
    }

    public static function clear(): void
    {
        unset($_SESSION[self::KEY], $_SESSION['shop_saas_account']);
    }

    /** @param array<string, mixed> $account */
    public static function setAccount(array $account): void
    {
        $_SESSION['shop_saas_account'] = $account;
    }

    /** @return array<string, mixed>|null */
    public static function account(): ?array
    {
        $a = $_SESSION['shop_saas_account'] ?? null;
        return is_array($a) ? $a : null;
    }

    public static function requireLogin(): string
    {
        $token = self::token();
        if ($token === null) {
            header('Location: /konto/login', true, 302);
            exit;
        }
        return $token;
    }
}
