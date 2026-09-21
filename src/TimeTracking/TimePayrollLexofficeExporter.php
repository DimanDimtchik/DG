<?php
declare(strict_types=1);

/**
 * Zeiterfassung Z6d: Lexoffice Lohn — Zeiten-Übergabe als CSV.
 *
 * Gleicher Monatsdatensatz wie CSV/DATEV. Kein undokumentiertes Binärformat.
 * Stunden als Dezimal (Komma), Feldliste mit Lexoffice-/Steuerberater-Import abstimmen.
 */
final class TimePayrollLexofficeExporter
{
    /**
     * @param array<string, mixed> $dataset from TimePayrollExportService::monthDataset
     * @return array{filename: string, content: string, count: int}
     */
    public static function build(array $dataset): array
    {
        $yearMonth = TimeMonthReportService::normalizeYearMonth(
            (string) ($dataset['year_month'] ?? date('Y-m'))
        );
        $period = str_replace('-', '', $yearMonth); // YYYYMM
        $company = class_exists('CompanySettings') ? CompanySettings::displayName() : '';
        $created = date('YmdHis');

        $meta = [
            'Lexoffice',
            'LohnZeiten',
            'CRM',
            '1',
            $created,
            $period,
            $company !== '' ? $company : 'Lohnzeiten',
            'Mit Steuerberater/Lexoffice abstimmen — Zeitenuebergabe, keine Netto-Lohnberechnung',
        ];

        $columns = [
            'MitarbeiterNr',
            'Name',
            'AbrMonat',
            'Soll_Stunden',
            'Ist_Stunden',
            'Pause_Stunden',
            'Ueberstunden_Stunden',
            'Urlaub_Tage',
            'Krank_Tage',
            'Korrektur_Stunden',
        ];

        $lines = [self::csvLine($meta), self::csvLine($columns)];
        $count = 0;
        foreach ($dataset['rows'] ?? [] as $row) {
            if (!is_array($row)) {
                continue;
            }
            $lines[] = self::csvLine([
                (string) ($row['personal_number'] ?? ''),
                (string) ($row['name'] ?? ''),
                $period,
                self::minutesToHours((int) ($row['soll_minutes'] ?? 0)),
                self::minutesToHours((int) ($row['ist_minutes'] ?? 0)),
                self::minutesToHours((int) ($row['pause_minutes'] ?? 0)),
                self::minutesToHours((int) ($row['ueberstunden_minutes'] ?? 0)),
                self::numDays((float) ($row['urlaub_tage'] ?? 0)),
                self::numDays((float) ($row['krank_tage'] ?? 0)),
                self::minutesToHours((int) ($row['korrektur_minutes'] ?? 0)),
            ]);
            $count++;
        }

        $lines[] = self::csvLine([
            '#',
            'Hinweis',
            'Lexoffice Lohn Zeitenuebergabe Z6d — Import laut Kanzlei; PDF-Abrechnung separat als payroll_slip in Kontaktakte ablegen.',
        ]);

        $host = (string) ($_SERVER['HTTP_HOST'] ?? 'crm');
        if (class_exists('FirmSwitcherService')) {
            $host = FirmSwitcherService::normalizeHost($host);
        }
        $host = preg_replace('/[^a-z0-9._-]/', '', strtolower($host)) ?? 'crm';
        if ($host === '') {
            $host = 'crm';
        }

        return [
            'filename' => sprintf('Lexoffice_LohnZeiten_%s_%s.csv', $yearMonth, $host),
            'content' => "\xEF\xBB\xBF" . implode("\r\n", $lines) . "\r\n",
            'count' => $count,
        ];
    }

    private static function minutesToHours(int $minutes): string
    {
        return number_format($minutes / 60.0, 2, ',', '');
    }

    private static function numDays(float $v): string
    {
        return number_format($v, 1, ',', '');
    }

    /**
     * @param list<string> $cols
     */
    private static function csvLine(array $cols): string
    {
        $out = [];
        foreach ($cols as $c) {
            $c = str_replace('"', '""', (string) $c);
            $out[] = '"' . $c . '"';
        }

        return implode(';', $out);
    }
}
