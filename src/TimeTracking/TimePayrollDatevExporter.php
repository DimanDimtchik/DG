<?php
declare(strict_types=1);

/**
 * Zeiterfassung Z6c: DATEV Lohn & Gehalt — Zeiten-Übergabe als CSV.
 *
 * Kein LODAS-/undokumentiertes Binärformat. Encoding/BOM/Semikolon analog
 * DatevExtfExporter. Feldliste mit Steuerberater / DATEV-Import-Assistent abstimmen.
 * Datenbasis = TimePayrollExportService::monthDataset (wie CSV Z6b).
 */
final class TimePayrollDatevExporter
{
    /**
     * @param array<string, mixed> $dataset from TimePayrollExportService::monthDataset
     * @return array{filename: string, content: string, count: int}
     */
    public static function build(array $dataset): array
    {
        $cfg = DatevExportSettings::config();
        if ($cfg['consultant_number'] === '' || $cfg['client_number'] === '') {
            throw new RuntimeException(
                'DATEV Berater- und Mandantennummer fehlen — bitte unter Einstellungen → Kontenrahmen pflegen.'
            );
        }

        $yearMonth = TimeMonthReportService::normalizeYearMonth(
            (string) ($dataset['year_month'] ?? date('Y-m'))
        );
        $period = str_replace('-', '', $yearMonth); // YYYYMM
        $company = class_exists('CompanySettings') ? CompanySettings::displayName() : '';
        $created = date('YmdHis');

        // Metakopf (prüfbar, kein EXTF-Buchungsstapel 700/21 — Lohn-Zeitenübergabe)
        $meta = [
            'DATEV',
            'LohnZeiten',
            'CRM',
            '1',
            $created,
            $period,
            $cfg['consultant_number'],
            $cfg['client_number'],
            $company !== '' ? $company : 'Lohnzeiten',
            'Mit Steuerberater abstimmen — kein LODAS-Binaerformat',
        ];

        $columns = [
            'BeraterNr',
            'MandantNr',
            'AbrMonat',
            'Personalnummer',
            'Name',
            'Soll_Minuten',
            'Ist_Minuten',
            'Pause_Minuten',
            'Ueberstunden_Minuten',
            'Urlaub_Tage',
            'Krank_Tage',
            'Korrektur_Minuten',
        ];

        $lines = [self::csvLine($meta), self::csvLine($columns)];
        $count = 0;
        foreach ($dataset['rows'] ?? [] as $row) {
            if (!is_array($row)) {
                continue;
            }
            $lines[] = self::csvLine([
                $cfg['consultant_number'],
                $cfg['client_number'],
                $period,
                (string) ($row['personal_number'] ?? ''),
                (string) ($row['name'] ?? ''),
                (string) (int) ($row['soll_minutes'] ?? 0),
                (string) (int) ($row['ist_minutes'] ?? 0),
                (string) (int) ($row['pause_minutes'] ?? 0),
                (string) (int) ($row['ueberstunden_minutes'] ?? 0),
                self::numDays((float) ($row['urlaub_tage'] ?? 0)),
                self::numDays((float) ($row['krank_tage'] ?? 0)),
                (string) (int) ($row['korrektur_minutes'] ?? 0),
            ]);
            $count++;
        }

        $lines[] = self::csvLine([
            '#',
            'Hinweis',
            'DATEV Lohn Zeitenuebergabe Z6c — Import laut Kanzlei/DATEV-Assistent; keine Netto-Lohnberechnung im CRM.',
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
            'filename' => sprintf('DATEV_LohnZeiten_%s_%s.csv', $yearMonth, $host),
            'content' => "\xEF\xBB\xBF" . implode("\r\n", $lines) . "\r\n",
            'count' => $count,
        ];
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
