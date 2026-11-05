<?php
declare(strict_types=1);

/**
 * Datumsabhängige Mindestlohn- und Minijob-Grenzen (SGB IV § 8 / MiLoG).
 * Werte aus docs/ARBEITSVERTRAG-VORLAGEN-HINWEIS.md — bei amtlicher Änderung hier pflegen.
 */
final class EmploymentContractRates
{
    /**
     * @return list<array{from: string, min_wage: float, geringfuegig_month: float}>
     */
    private static function table(): array
    {
        return [
            ['from' => '2028-01-01', 'min_wage' => 0.0, 'geringfuegig_month' => 0.0], // Platzhalter: amtlich nachziehen
            ['from' => '2027-01-01', 'min_wage' => 14.60, 'geringfuegig_month' => 633.0],
            ['from' => '2026-01-01', 'min_wage' => 13.90, 'geringfuegig_month' => 603.0],
            ['from' => '2025-01-01', 'min_wage' => 12.82, 'geringfuegig_month' => 556.0],
        ];
    }

    /**
     * @return array{min_wage: float, geringfuegig_month: float, from: string, known: bool}
     */
    public static function forDate(?string $ymd = null): array
    {
        $day = self::normalizeDate($ymd) ?? date('Y-m-d');
        foreach (self::table() as $row) {
            if ($day >= $row['from']) {
                $known = $row['min_wage'] > 0 && $row['geringfuegig_month'] > 0;

                return [
                    'min_wage' => $row['min_wage'],
                    'geringfuegig_month' => $row['geringfuegig_month'],
                    'from' => $row['from'],
                    'known' => $known,
                ];
            }
        }

        $fallback = self::table()[count(self::table()) - 1];

        return [
            'min_wage' => $fallback['min_wage'],
            'geringfuegig_month' => $fallback['geringfuegig_month'],
            'from' => $fallback['from'],
            'known' => $fallback['min_wage'] > 0,
        ];
    }

    public static function formatEuro(float $amount): string
    {
        return number_format($amount, 2, ',', '.') . ' EUR';
    }

    private static function normalizeDate(?string $ymd): ?string
    {
        $ymd = trim((string) $ymd);
        if ($ymd === '') {
            return null;
        }
        $dt = date_create($ymd);

        return $dt instanceof DateTimeInterface ? $dt->format('Y-m-d') : null;
    }
}
