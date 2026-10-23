<?php

declare(strict_types=1);

/**
 * Weiche E-Mail-Normalisierung für Stammdaten (kein Speichern-Block bei MX).
 */
final class EmailNormalizer
{
    /**
     * Leerzeichen weg, Domain lowercasen. Local-Part unverändert (außer trim).
     */
    public static function normalize(string $raw): string
    {
        $email = trim($raw);
        if ($email === '') {
            return '';
        }
        $email = preg_replace('/[\x{200B}-\x{200D}\x{FEFF}]/u', '', $email) ?? $email;
        $email = preg_replace('/\s+/u', '', $email) ?? $email;
        $email = trim($email);
        if ($email === '') {
            return '';
        }

        $at = strrpos($email, '@');
        if ($at === false) {
            return $email;
        }

        $local = substr($email, 0, $at);
        $domain = strtolower(substr($email, $at + 1));
        $domain = rtrim($domain, '.');

        return $local . '@' . $domain;
    }

    public static function isValidSyntax(string $email): bool
    {
        $email = self::normalize($email);
        if ($email === '') {
            return false;
        }

        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }

    /**
     * @return array{
     *   ok: bool,
     *   normalized: string,
     *   message: string|null,
     *   severity: 'none'|'info'|'warn'|'error'
     * }
     */
    public static function analyze(string $raw): array
    {
        $normalized = self::normalize($raw);
        if ($normalized === '') {
            return [
                'ok' => true,
                'normalized' => '',
                'message' => null,
                'severity' => 'none',
            ];
        }

        if (!self::isValidSyntax($normalized)) {
            $hint = null;
            if (!str_contains($normalized, '@')) {
                $hint = 'E-Mail braucht ein @ (z. B. name@firma.de).';
            } elseif (preg_match('/@[^@]+\.(con|cm|cmo|d)$/i', $normalized)) {
                $hint = 'Domain-Endung sieht nach Tippfehler aus (z. B. .com / .de).';
            } else {
                $hint = 'E-Mail-Format scheint ungültig.';
            }

            return [
                'ok' => false,
                'normalized' => $normalized,
                'message' => $hint,
                'severity' => 'warn',
            ];
        }

        return [
            'ok' => true,
            'normalized' => $normalized,
            'message' => null,
            'severity' => 'none',
        ];
    }
}
