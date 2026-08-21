<?php

declare(strict_types=1);

/**
 * Lightweight e-mail existence / deliverability check (syntax + DNS MX/A).
 * Does not open SMTP connections.
 */
final class EmailExistenceChecker
{
    public const STATUS_UNKNOWN = 'unknown';
    public const STATUS_OK = 'ok';
    public const STATUS_INVALID = 'invalid';
    public const STATUS_NO_MX = 'no_mx';
    public const STATUS_ERROR = 'error';

    /**
     * @return array{ok: bool, status: string, detail: string, checked_at: string}
     */
    public static function check(string $email): array
    {
        $email = strtolower(trim($email));
        $checkedAt = date('Y-m-d H:i:s');
        if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            return [
                'ok' => false,
                'status' => self::STATUS_INVALID,
                'detail' => 'Ungültiges E-Mail-Format.',
                'checked_at' => $checkedAt,
            ];
        }
        $at = strrpos($email, '@');
        if ($at === false) {
            return [
                'ok' => false,
                'status' => self::STATUS_INVALID,
                'detail' => 'Ungültiges E-Mail-Format.',
                'checked_at' => $checkedAt,
            ];
        }
        $domain = substr($email, $at + 1);
        if ($domain === '' || !self::domainHasMailExchange($domain)) {
            return [
                'ok' => false,
                'status' => self::STATUS_NO_MX,
                'detail' => 'Für die Domain wurden keine MX-/A-Einträge gefunden.',
                'checked_at' => $checkedAt,
            ];
        }

        return [
            'ok' => true,
            'status' => self::STATUS_OK,
            'detail' => 'Domain hat MX- oder A-Eintrag.',
            'checked_at' => $checkedAt,
        ];
    }

    public static function assertDeliverable(string $email): void
    {
        $result = self::check($email);
        if (!$result['ok']) {
            throw new InvalidArgumentException(
                'E-Mail-Adresse scheint nicht erreichbar: ' . $result['detail']
            );
        }
    }

    private static function domainHasMailExchange(string $domain): bool
    {
        $domain = strtolower(rtrim($domain, '.'));
        if ($domain === '') {
            return false;
        }
        if (@checkdnsrr($domain, 'MX')) {
            return true;
        }
        // Some hosts only publish A/AAAA
        if (@checkdnsrr($domain, 'A') || @checkdnsrr($domain, 'AAAA')) {
            return true;
        }
        $hosts = [];
        if (@getmxrr($domain, $hosts) && $hosts !== []) {
            return true;
        }

        return false;
    }
}
