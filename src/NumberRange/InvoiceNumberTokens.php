<?php
declare(strict_types=1);

final class InvoiceNumberTokens
{
    /** @return array<string, array{title: string, items: list<array{label: string, codes: list<string>}>}> */
    public static function referenceGroups(): array
    {
        return [
            'zeit' => [
                'title' => 'Zeit',
                'items' => [
                    ['label' => 'Jahr (4 Ziffern)', 'hint' => '2026', 'codes' => ['{JJJJ}']],
                    ['label' => 'Jahr (2 Ziffern)', 'hint' => '26', 'codes' => ['{JJ}']],
                    ['label' => 'Monat (2 Ziffern)', 'hint' => '03', 'codes' => ['{MM}']],
                    ['label' => 'Monat (Kurzname)', 'hint' => 'Mrz', 'codes' => ['{MMK}']],
                    ['label' => 'Monat (vollständig)', 'hint' => 'März', 'codes' => ['{MMV}']],
                    ['label' => 'Datum (JJJJMMTT)', 'hint' => '20260324', 'codes' => ['{JJJJMMTT}']],
                    ['label' => 'Datum (JJMMTT)', 'hint' => '260324', 'codes' => ['{JJMMTT}']],
                    ['label' => 'Datum (TTMMJJ)', 'hint' => '240326', 'codes' => ['{TTMMJJ}']],
                    ['label' => 'Datum (TTMMJJJJ)', 'hint' => '24032026', 'codes' => ['{TTMMJJJJ}']],
                    ['label' => 'Kalenderwoche (2 Ziffern)', 'hint' => '12', 'codes' => ['{KW}']],
                    ['label' => 'Kalenderwoche (mit KW)', 'hint' => 'KW12', 'codes' => ['{KWKW}']],
                    ['label' => 'Quartal (Ziffer)', 'hint' => '1', 'codes' => ['{Q}']],
                    ['label' => 'Quartal (mit Q)', 'hint' => 'Q1', 'codes' => ['{QQ}']],
                ],
            ],
            'sonstiges' => [
                'title' => 'Sonstiges',
                'items' => [
                    ['label' => 'Laufende Nummer', 'codes' => ['{NR}']],
                    ['label' => 'Länderkürzel', 'codes' => ['{LAND}']],
                    ['label' => 'Timestamp', 'codes' => ['{TS}']],
                    ['label' => 'Kundennummer', 'codes' => ['{KUNDE}']],
                    ['label' => 'Firmen-ID', 'codes' => ['{FIRMA}']],
                ],
            ],
        ];
    }

    /** @return array<string, string> */
    public static function numberBases(): array
    {
        return [
            'decimal' => 'Dezimal (10)',
            'hex' => 'Hexadezimal (16)',
            'octal' => 'Oktal (8)',
        ];
    }

    /**
     * @param array<string, mixed> $context
     */
    public static function resolveString(string $template, array $context, int $sequence = 0): string
    {
        if ($template === '') {
            return '';
        }

        $base = (string) ($context['number_display'] ?? 'decimal');
        $pad = max(0, (int) ($context['number_pad'] ?? 0));
        $formattedNr = InvoiceNumberBuilder::formatSequenceValue($sequence, $base, $pad);

        return (string) preg_replace_callback(
            '/\{([^{}]+)\}/u',
            static function (array $matches) use ($context, $formattedNr): string {
                $key = self::normalizePlaceholderKey($matches[1]);

                return self::resolveKey($key, $context, $formattedNr);
            },
            $template
        );
    }

    /**
     * @param array<string, mixed> $document
     */
    public static function usesCountryPlaceholder(array $document): bool
    {
        foreach (['prefix', 'number_pattern', 'suffix'] as $field) {
            $text = (string) ($document[$field] ?? '');
            if (preg_match('/\{LAND\}/iu', $text) === 1) {
                return true;
            }
        }

        return false;
    }

    /**
     * Alte Checkbox-Token in Platzhalter-Text überführen.
     *
     * @param array<string, mixed> $document
     * @return array<string, mixed>
     */
    public static function migrateLegacyDocument(array $document): array
    {
        $map = [
            'year_yyyy' => '{JJJJ}',
            'year_yy' => '{JJ}',
            'month_mm' => '{MM}',
            'month_mrz' => '{MMK}',
            'month_maerz' => '{MMV}',
            'date_ymd' => '{JJJJMMTT}',
            'date_yymmdd' => '{JJMMTT}',
            'date_ddmmyy' => '{TTMMJJ}',
            'date_ddmmyyyy' => '{TTMMJJJJ}',
            'timestamp' => '{TS}',
            'country' => '{LAND}',
            'week_2' => '{KW}',
            'week_kw' => '{KWKW}',
            'quarter_1' => '{Q}',
            'quarter_q' => '{QQ}',
            'customer_id' => '{KUNDE}',
            'company_id' => '{FIRMA}',
        ];

        foreach (['prefix', 'suffix'] as $part) {
            $tokens = is_array($document[$part . '_tokens'] ?? null) ? $document[$part . '_tokens'] : [];
            if ($tokens === []) {
                continue;
            }
            $extra = '';
            foreach ($tokens as $token) {
                $token = strtolower(trim((string) $token));
                $extra .= $map[$token] ?? '';
            }
            $document[$part] = (string) ($document[$part] ?? '') . $extra;
            $document[$part . '_tokens'] = [];
        }

        $numberTokens = is_array($document['number_tokens'] ?? null) ? $document['number_tokens'] : [];
        if ($numberTokens !== [] || !isset($document['number_pattern'])) {
            $pattern = '';
            foreach ($numberTokens as $token) {
                $token = strtolower(trim((string) $token));
                $pattern .= $map[$token] ?? '';
            }
            if ($pattern === '') {
                $pattern = '{NR}';
            }
            $document['number_pattern'] = $pattern;
            $document['number_tokens'] = [];
        }

        if (!isset($document['number_pattern']) || trim((string) $document['number_pattern']) === '') {
            $document['number_pattern'] = '{NR}';
        }

        if (!isset($document['counter']) && isset($document['number']) && is_numeric($document['number'])) {
            $document['counter'] = (string) $document['number'];
        }

        return $document;
    }

    private static function normalizePlaceholderKey(string $raw): string
    {
        $key = strtoupper(trim($raw));
        $key = str_replace(['Ä', 'ä'], 'AE', $key);
        $key = str_replace(['Ö', 'ö'], 'OE', $key);
        $key = str_replace(['Ü', 'ü'], 'UE', $key);
        $key = str_replace('ß', 'SS', $key);

        return $key;
    }

    /**
     * @param array<string, mixed> $context
     */
    private static function resolveKey(string $key, array $context, string $formattedNr): string
    {
        $ts = isset($context['timestamp']) ? (int) $context['timestamp'] : time();
        $tz = new DateTimeZone((string) App::config('timezone', 'Europe/Berlin'));
        $dt = (new DateTimeImmutable('@' . $ts))->setTimezone($tz);

        $country = strtoupper((string) ($context['country_code'] ?? 'DE'));
        if (strlen($country) !== 2) {
            $country = 'DE';
        }

        $monthNum = (int) $dt->format('n');
        $monthsShort = ['', 'Jan', 'Feb', 'Mrz', 'Apr', 'Mai', 'Jun', 'Jul', 'Aug', 'Sep', 'Okt', 'Nov', 'Dez'];
        $monthsFull = ['', 'Januar', 'Februar', 'März', 'April', 'Mai', 'Juni', 'Juli', 'August', 'September', 'Oktober', 'November', 'Dezember'];
        $quarter = (string) (int) ceil($monthNum / 3);
        $week = $dt->format('W');

        return match ($key) {
            'JJJJ', 'YYYY' => $dt->format('Y'),
            'JJ', 'YY' => $dt->format('y'),
            'MM' => $dt->format('m'),
            'MMK', 'MRZ' => $monthsShort[$monthNum] ?? 'Mrz',
            'MMV', 'MAERZ' => $monthsFull[$monthNum] ?? 'März',
            'JJJJMMTT', 'YYYYMMDD' => $dt->format('Ymd'),
            'JJMMTT', 'YYMMDD' => $dt->format('ymd'),
            'TTMMJJ', 'DDMMYY' => $dt->format('dmy'),
            'TTMMJJJJ', 'DDMMYYYY' => $dt->format('dmY'),
            'TS', 'TIMESTAMP' => (string) $ts,
            'LAND', 'COUNTRY' => $country,
            'KW' => $week,
            'KWKW' => 'KW' . $week,
            'Q' => $quarter,
            'QQ' => 'Q' . $quarter,
            'NR', 'NUMMER', 'NUM' => $formattedNr,
            'KUNDE', 'KUNDENNUMMER' => !empty($context['preview'])
                ? '{KUNDE}'
                : (string) ($context['customer_id'] ?? ''),
            'FIRMA', 'FIRMENID' => !empty($context['preview'])
                ? '{FIRMA}'
                : (string) ($context['company_id'] ?? ''),
            default => '{' . $key . '}',
        };
    }
}
