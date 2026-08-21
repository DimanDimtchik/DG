<?php
declare(strict_types=1);

/**
 * CSRF-Token-Generierung und -Validierung für Formulare.
 */
final class Csrf
{
    /**
     * Liefert das Session-CSRF-Token (wird bei Bedarf erzeugt).
     */
    public static function token(): string
    {
        if (empty($_SESSION['dg_csrf'])) {
            $_SESSION['dg_csrf'] = bin2hex(random_bytes(16));
        }

        return (string) $_SESSION['dg_csrf'];
    }

    /**
     * Prüft ein übermitteltes Token gegen die Session (timing-safe).
     */
    public static function verify(?string $token): bool
    {
        return is_string($token) && $token !== '' && hash_equals(self::token(), $token);
    }
}
