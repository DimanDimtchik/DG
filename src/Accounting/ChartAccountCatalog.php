<?php
declare(strict_types=1);

/** Vollständiger SKR-Katalog (GnuCash + german-accounting, MIT). */
final class ChartAccountCatalog
{
    /** Fehlende Standardkonten aus DATEV-PDF-Import (Lücken bei 312x/592x). */
    private const SUPPLEMENT_VERSION = '2026-07-06-13b';

    /**
     * @var array<string, list<array{account_number: string, name: string, account_class: string, section: string}>>
     */
    private const SUPPLEMENT_ACCOUNTS = [
        'skr03' => [
            [
                'account_number' => '3120',
                'name' => 'Bauleistungen eines im Inland ansässigen Unternehmers 19 % Vorsteuer und 19 % Umsatzsteuer',
                'section' => 'aufwand',
            ],
            [
                'account_number' => '3125',
                'name' => 'Leistungen eines im Ausland ansässigen Unternehmens 19 % Vorsteuer und 19 % Umsatzsteuer',
                'section' => 'aufwand',
            ],
            [
                'account_number' => '3140',
                'name' => 'Bauleistungen eines im Inland ansässigen Unternehmers ohne Vorsteuer und 19 % Umsatzsteuer',
                'section' => 'aufwand',
            ],
        ],
        'skr04' => [
            [
                'account_number' => '5920',
                'name' => 'Bauleistungen eines im Inland ansässigen Unternehmers 19 % Vorsteuer und 19 % Umsatzsteuer',
                'section' => 'aufwand',
            ],
            [
                'account_number' => '5925',
                'name' => 'Leistungen eines im Ausland ansässigen Unternehmens 19 % Vorsteuer und 19 % Umsatzsteuer',
                'section' => 'aufwand',
            ],
            [
                'account_number' => '5940',
                'name' => 'Bauleistungen eines im Inland ansässigen Unternehmers ohne Vorsteuer und 19 % Umsatzsteuer',
                'section' => 'aufwand',
            ],
        ],
    ];

    /** @var list<array{file: string, format: string}> */
    private const SOURCES = [
        'skr03' => [
            ['file' => '/assets/data/skr03-datev.json', 'format' => 'datev-pdf'],
        ],
        'skr04' => [
            ['file' => '/assets/data/skr04-datev.json', 'format' => 'datev-pdf'],
        ],
    ];

    /** @var array<string, list<array{account_number: string, name: string, account_class: string, section: string, hints: array<string, mixed>}>> */
    private static array $cache = [];

        /**
     * accountsForSkr
     * @param string $skrType Kontenrahmen (skr03/skr04)
     * @return list<array{account_number: string, name: string, account_class: string, section: string, hints: array<string, mixed>}>
     */
    public static function accountsForSkr(string $skrType): array
    {
        $skrType = ChartOfAccountsSettings::sanitizeSkrType($skrType);
        if (isset(self::$cache[$skrType])) {
            return self::$cache[$skrType];
        }

        $sources = self::SOURCES[$skrType] ?? [];
        $byNumber = [];

        foreach ($sources as $source) {
            $path = DG_ROOT . $source['file'];
            if (!is_readable($path)) {
                continue;
            }

            $raw = json_decode((string) file_get_contents($path), true);
            if (!is_array($raw)) {
                continue;
            }

            $rows = match ($source['format']) {
                'german-accounting', 'datev-pdf' => self::parseGermanAccounting($raw),
                default => self::parseGnuCash($raw),
            };

            foreach ($rows as $row) {
                $byNumber[$row['account_number']] = $row;
            }
        }

        foreach (self::SUPPLEMENT_ACCOUNTS[$skrType] ?? [] as $row) {
            if (isset($byNumber[$row['account_number']])) {
                continue;
            }
            $code = $row['account_number'];
            $byNumber[$code] = [
                'account_number' => $code,
                'name' => $row['name'],
                'account_class' => substr($code, 0, 1),
                'section' => $row['section'],
                'hints' => [
                    'summary' => $row['name'],
                    'classification' => self::classificationForSection($row['section']),
                    'catalog_source' => 'supplement',
                ],
            ];
        }

        foreach (ChartAccountCatalogNameCorrections::forSkr($skrType) as $accountNumber => $correctedName) {
            if (!isset($byNumber[$accountNumber])) {
                continue;
            }
            $previousName = (string) ($byNumber[$accountNumber]['name'] ?? '');
            $byNumber[$accountNumber]['name'] = $correctedName;
            $hints = $byNumber[$accountNumber]['hints'] ?? [];
            if (!is_array($hints)) {
                $hints = [];
            }
            $oldSummary = trim((string) ($hints['summary'] ?? ''));
            if ($oldSummary === '' || $oldSummary === $previousName || ($hints['catalog_source'] ?? '') === 'datev-pdf') {
                $hints['summary'] = $correctedName;
            }
            $hints['name_corrected'] = true;
            $byNumber[$accountNumber]['hints'] = $hints;
        }

        $accounts = array_values($byNumber);
        usort($accounts, static fn (array $a, array $b): int => strcmp($a['account_number'], $b['account_number']));
        self::$cache[$skrType] = $accounts;

        return $accounts;
    }

    /**
     * catalogCount
     * @param string $skrType Kontenrahmen (skr03/skr04)
     * @return int
     */
    public static function catalogCount(string $skrType): int
    {
        return count(self::accountsForSkr($skrType));
    }

    /**
     * catalogVersion
     * @param string $skrType Kontenrahmen (skr03/skr04)
     * @return string
     */
    public static function catalogVersion(string $skrType): string
    {
        $skrType = ChartOfAccountsSettings::sanitizeSkrType($skrType);
        $parts = [];
        foreach (self::SOURCES[$skrType] ?? [] as $source) {
            $path = DG_ROOT . $source['file'];
            if (is_readable($path)) {
                $parts[] = basename($source['file']) . ':' . filemtime($path);
            }
        }

        return implode('|', $parts)
            . '|supplement:' . self::SUPPLEMENT_VERSION
            . '|repair:' . ChartAccountNameRepair::VERSION
            . '|names:' . ChartAccountCatalogNameCorrections::VERSION;
    }

    /**
     * @param array<string, mixed> $raw
     * @return list<array{account_number: string, name: string, account_class: string, section: string, hints: array<string, mixed>}>
     */
    private static function parseGermanAccounting(array $raw): array
    {
        $accounts = [];
        $konten = is_array($raw['konten'] ?? null) ? $raw['konten'] : [];
        foreach ($konten as $row) {
            if (!is_array($row)) {
                continue;
            }

            $code = self::normalizeCode((string) ($row['konto'] ?? $row['account_number'] ?? ''));
            $name = ChartAccountNameRepair::repair(trim((string) ($row['name'] ?? '')));
            if ($code === '' || $name === '') {
                continue;
            }

            $typ = trim((string) ($row['typ'] ?? ''));
            $gruppe = trim((string) ($row['gruppe'] ?? ''));
            $section = isset($row['section']) && is_string($row['section'])
                ? (string) $row['section']
                : self::sectionFromGermanType($typ, $code);

            $accounts[] = [
                'account_number' => $code,
                'name' => $name,
                'account_class' => substr($code, 0, 1),
                'section' => $section,
                'hints' => [
                    'summary' => $gruppe !== '' ? $gruppe . ' — ' . $name : $name,
                    'classification' => self::classificationForSection($section),
                    'catalog_source' => isset($row['account_number']) ? 'datev-pdf' : 'german-accounting',
                    'gruppe' => $gruppe,
                ],
            ];
        }

        return $accounts;
    }

    /**
     * @param list<array<string, mixed>> $raw
     * @return list<array{account_number: string, name: string, account_class: string, section: string, hints: array<string, mixed>}>
     */
    private static function parseGnuCash(array $raw): array
    {
        $accounts = [];
        foreach ($raw as $row) {
            if (!is_array($row) || empty($row['leaf'])) {
                continue;
            }

            $code = self::normalizeCode((string) ($row['code'] ?? ''));
            $name = trim((string) ($row['name'] ?? ''));
            if ($code === '' || $name === '') {
                continue;
            }

            $type = trim((string) ($row['type'] ?? ''));
            $section = self::sectionFromGnuCashType($type, $code);
            $description = trim((string) ($row['description'] ?? ''));

            $accounts[] = [
                'account_number' => $code,
                'name' => $name,
                'account_class' => substr($code, 0, 1),
                'section' => $section,
                'hints' => [
                    'summary' => $description !== '' ? $description : $name,
                    'classification' => self::classificationForSection($section),
                    'catalog_source' => 'gnucash-skr-json',
                ],
            ];
        }

        return $accounts;
    }

    /**
     * normalizeCode
     * @param string $code
     * @return string
     */
    private static function normalizeCode(string $code): string
    {
        $digits = preg_replace('/\D/', '', $code) ?? '';
        if ($digits === '') {
            return '';
        }

        return str_pad($digits, 4, '0', STR_PAD_LEFT);
    }

    /**
     * sectionFromGermanType
     * @param string $typ
     * @param string $code
     * @return string
     */
    private static function sectionFromGermanType(string $typ, string $code): string
    {
        return match (mb_strtolower($typ)) {
            'aktiv', 'aktiva' => 'aktiva',
            'passiv', 'passiva' => 'passiva',
            'ertrag', 'erträge' => 'ertrag',
            'aufwand' => 'aufwand',
            default => self::sectionFromFirstDigit($code),
        };
    }

    /**
     * sectionFromGnuCashType
     * @param string $type
     * @param string $code
     * @return string
     */
    private static function sectionFromGnuCashType(string $type, string $code): string
    {
        $typeLower = mb_strtolower($type);
        if (str_contains($typeLower, 'aktiva')) {
            return 'aktiva';
        }
        if (str_contains($typeLower, 'passiva')) {
            return 'passiva';
        }
        if (str_contains($typeLower, 'ertrag') || str_contains($typeLower, 'einnahme')) {
            return 'ertrag';
        }
        if (str_contains($typeLower, 'aufwand') || str_contains($typeLower, 'expense')) {
            return 'aufwand';
        }

        return self::sectionFromFirstDigit($code);
    }

    /**
     * sectionFromFirstDigit
     * @param string $code
     * @return string
     */
    private static function sectionFromFirstDigit(string $code): string
    {
        return match ((int) substr($code, 0, 1)) {
            0, 1, 2 => 'aktiva',
            3 => 'passiva',
            4, 5, 6, 7 => 'aufwand',
            8, 9 => 'ertrag',
            default => 'aufwand',
        };
    }

        /**
     * classificationForSection
     * @param string $section Kontenabschnitt
     * @return array{balance_sheet: bool, guv: bool, eur: bool}
     */
    private static function classificationForSection(string $section): array
    {
        return match ($section) {
            'aktiva', 'passiva' => ['balance_sheet' => true, 'guv' => false, 'eur' => true],
            default => ['balance_sheet' => false, 'guv' => true, 'eur' => true],
        };
    }
}
