<?php
declare(strict_types=1);

/**
 * Cron Settings.
 */
final class CronSettings
{
    /**
     * Methode retention token.
     * @return string
     */
    public static function retentionToken(): string
    {
        $local = DG_ROOT . '/config/cron.local.php';
        if (is_file($local)) {
    /** @var array<string, mixed> $config */
            $config = require $local;

            return trim((string) ($config['retention_token'] ?? ''));
        }

        return '';
    }

    /**
     * Prüft: is authorized.
     * @param string|null $token
     * @return bool
     */
    public static function isAuthorized(?string $token): bool
    {
        $expected = self::retentionToken();
        if ($expected === '') {
            return false;
        }

        return hash_equals($expected, trim((string) $token));
    }
}
