<?php
declare(strict_types=1);

final class Csrf
{
    public static function token(): string
    {
        if (empty($_SESSION['dg_csrf'])) {
            $_SESSION['dg_csrf'] = bin2hex(random_bytes(16));
        }

        return (string) $_SESSION['dg_csrf'];
    }

    public static function verify(?string $token): bool
    {
        return is_string($token) && $token !== '' && hash_equals(self::token(), $token);
    }
}
