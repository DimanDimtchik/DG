<?php

declare(strict_types=1);

/**
 * Weiche Telefon-Normalisierung (DE-Default → E.164 für tel:-Links).
 */
final class PhoneNormalizer
{
    /** @var array<string, string> ISO-3166 → Ländervorwahl ohne + */
    private const COUNTRY_DIAL = [
        'DE' => '49',
        'AT' => '43',
        'CH' => '41',
        'NL' => '31',
        'FR' => '33',
        'BE' => '32',
        'LU' => '352',
        'IT' => '39',
        'ES' => '34',
        'PL' => '48',
        'CZ' => '420',
        'DK' => '45',
        'SE' => '46',
        'GB' => '44',
        'UK' => '44',
        'US' => '1',
    ];

    /**
     * Speichern: wenn möglich E.164 (+49123…), sonst bereinigte Ziffern/+.
     */
    public static function normalize(string $raw, ?string $defaultCountry = null): string
    {
        $e164 = self::toE164($raw, $defaultCountry);
        if ($e164 !== null) {
            return $e164;
        }

        return self::cleanKeepPlus($raw);
    }

    /**
     * Anzeige: +49 171 1234567 bzw. Original bereinigt.
     */
    public static function formatForDisplay(string $raw, ?string $defaultCountry = null): string
    {
        $e164 = self::toE164($raw, $defaultCountry);
        if ($e164 === null) {
            $clean = self::cleanKeepPlus($raw);

            return $clean !== '' ? $clean : trim($raw);
        }

        $digits = ltrim($e164, '+');
        $cc = self::matchCountryCode($digits);
        if ($cc === null) {
            return $e164;
        }
        $rest = substr($digits, strlen($cc));
        if ($rest === '') {
            return $e164;
        }

        // DE mobil: +49 1xx …
        if ($cc === '49' && str_starts_with($rest, '1') && strlen($rest) >= 10) {
            return '+49 ' . substr($rest, 0, 3) . ' ' . substr($rest, 3);
        }
        if ($cc === '49' && strlen($rest) >= 6) {
            $areaLen = strlen($rest) >= 10 ? 3 : 2;

            return '+49 ' . substr($rest, 0, $areaLen) . ' ' . substr($rest, $areaLen);
        }

        return '+' . $cc . ' ' . $rest;
    }

    /**
     * Längste bekannte Ländervorwahl aus den Ziffern (ohne +).
     */
    private static function matchCountryCode(string $digits): ?string
    {
        $codes = array_values(array_unique(array_values(self::COUNTRY_DIAL)));
        usort($codes, static fn(string $a, string $b): int => strlen($b) <=> strlen($a));
        foreach ($codes as $code) {
            if (str_starts_with($digits, $code)) {
                return $code;
            }
        }

        return null;
    }

    /**
     * Nur Ziffern mit führendem + für tel:-URI, oder null.
     */
    public static function toE164(string $raw, ?string $defaultCountry = null): ?string
    {
        $country = strtoupper(trim((string) ($defaultCountry ?? self::defaultCountry())));
        if ($country === '') {
            $country = 'DE';
        }
        $dial = self::COUNTRY_DIAL[$country] ?? '49';

        $s = trim($raw);
        if ($s === '') {
            return null;
        }
        $s = preg_replace('/[\x{200B}-\x{200D}\x{FEFF}]/u', '', $s) ?? $s;
        // Klammern, Bindestriche, Slash, Leerzeichen, Punkte weg
        $s = preg_replace('/[^\d+]/', '', $s) ?? $s;
        if ($s === '' || $s === '+') {
            return null;
        }

        if (str_starts_with($s, '00')) {
            $s = '+' . substr($s, 2);
        }

        if (str_starts_with($s, '+')) {
            $digits = preg_replace('/\D/', '', substr($s, 1)) ?? '';
            if (strlen($digits) < 8 || strlen($digits) > 15) {
                return null;
            }

            return '+' . $digits;
        }

        $digits = preg_replace('/\D/', '', $s) ?? '';
        if ($digits === '') {
            return null;
        }

        // Nationale 0… → Ländervorwahl
        if (str_starts_with($digits, '0')) {
            $digits = $dial . substr($digits, 1);
        } elseif (!str_starts_with($digits, $dial) && strlen($digits) <= 11) {
            // reine Teilnehmernummer ohne 0
            $digits = $dial . $digits;
        }

        if (strlen($digits) < 8 || strlen($digits) > 15) {
            return null;
        }

        return '+' . $digits;
    }

    /**
     * @return array{
     *   ok: bool,
     *   normalized: string,
     *   display: string,
     *   e164: string|null,
     *   message: string|null,
     *   severity: 'none'|'info'|'warn'|'error'
     * }
     */
    public static function analyze(string $raw, ?string $defaultCountry = null): array
    {
        $normalized = self::normalize($raw, $defaultCountry);
        $e164 = self::toE164($raw, $defaultCountry);
        $display = $normalized !== '' ? self::formatForDisplay($normalized, $defaultCountry) : '';

        if (trim($raw) === '') {
            return [
                'ok' => true,
                'normalized' => '',
                'display' => '',
                'e164' => null,
                'message' => null,
                'severity' => 'none',
            ];
        }

        if ($e164 === null) {
            $digits = preg_replace('/\D/', '', $raw) ?? '';
            $msg = strlen($digits) < 6
                ? 'Telefonnummer wirkt zu kurz.'
                : 'Nummer konnte nicht als internationales Format gelesen werden — wird trotzdem gespeichert.';

            return [
                'ok' => false,
                'normalized' => $normalized,
                'display' => $display !== '' ? $display : $normalized,
                'e164' => null,
                'message' => $msg,
                'severity' => 'warn',
            ];
        }

        return [
            'ok' => true,
            'normalized' => $normalized,
            'display' => $display,
            'e164' => $e164,
            'message' => null,
            'severity' => 'none',
        ];
    }

    public static function defaultCountry(): string
    {
        if (class_exists('CompanySettings')) {
            $c = strtoupper(trim((string) (CompanySettings::config()['country'] ?? 'DE')));

            return $c !== '' ? $c : 'DE';
        }

        return 'DE';
    }

    private static function cleanKeepPlus(string $raw): string
    {
        $s = trim($raw);
        $s = preg_replace('/[\x{200B}-\x{200D}\x{FEFF}]/u', '', $s) ?? $s;
        $s = preg_replace('/[^\d+]/', '', $s) ?? $s;
        if (str_starts_with($s, '00')) {
            $s = '+' . substr($s, 2);
        }

        return $s;
    }
}
