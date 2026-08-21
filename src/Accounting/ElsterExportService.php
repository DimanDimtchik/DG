<?php
declare(strict_types=1);

/** CSV-Exporte für manuelle ELSTER-Eingabe (UStVA, EÜR) — ohne ELSTER-API. */
final class ElsterExportService
{
    /**
     * @return array{filename: string, content: string}
     */
    public static function exportUstva(int $year, ?int $month = null): array
    {
        $report = UstvaReportService::report($year, $month);
        $company = CompanySettings::forForm();
        $taxNumber = TaxOffice::to_elster_format((string) ($company['tax_number'] ?? '')) ?? '';

        $lines = [
            'ELSTER-Export UStVA (manuell eintragen)',
            'Zeitraum;' . $report['period_label'],
            'Steuernummer;' . $taxNumber,
            'Erstellt;' . date('d.m.Y H:i'),
            '',
            'Kennziffer;Bezeichnung;Betrag',
        ];

        foreach ($report['positions'] as $pos) {
            $lines[] = sprintf(
                '%s;%s;%s',
                $pos['kz'],
                self::csvEscape((string) $pos['label']),
                number_format((float) $pos['amount'], 2, ',', '')
            );
        }

        if ($report['positions'] === []) {
            $lines[] = '—;Keine UStVA-Positionen im Zeitraum;0,00';
        }

        $lines[] = '';
        $lines[] = 'Hinweis;In ELSTER unter Mein ELSTER → Formularmanagement → Umsatzsteuer-Voranmeldung die Kennziffern übertragen.';

        $periodSlug = $month !== null ? sprintf('%04d-%02d', $year, $month) : (string) $year;

        return [
            'filename' => 'ustva-elster-' . $periodSlug . '.csv',
            'content' => "\xEF\xBB\xBF" . implode("\r\n", $lines),
        ];
    }

    /**
     * @return array{filename: string, content: string}
     */
    public static function exportEuer(int $year): array
    {
        $euer = EuerReportService::report($year);
        $company = CompanySettings::forForm();
        $taxNumber = TaxOffice::to_elster_format((string) ($company['tax_number'] ?? '')) ?? '';

        $lines = [
            'ELSTER-Export EÜR (Anlage EÜR — manuell eintragen)',
            'Geschäftsjahr;' . $year,
            'Steuernummer;' . $taxNumber,
            'Erstellt;' . date('d.m.Y H:i'),
            '',
            'Kategorie;Konto;Bezeichnung;Betrag',
        ];

        foreach ($euer['income'] as $row) {
            $lines[] = sprintf(
                'Betriebseinnahmen;%s;%s;%s',
                $row['account_number'],
                self::csvEscape((string) $row['label']),
                number_format((float) $row['amount'], 2, ',', '')
            );
        }
        foreach ($euer['expense'] as $row) {
            $lines[] = sprintf(
                'Betriebsausgaben;%s;%s;%s',
                $row['account_number'],
                self::csvEscape((string) $row['label']),
                number_format((float) $row['amount'], 2, ',', '')
            );
        }

        $lines[] = '';
        $lines[] = sprintf(
            'Summe Einnahmen;;;%s',
            number_format((float) $euer['totals']['income'], 2, ',', '')
        );
        $lines[] = sprintf(
            'Summe Ausgaben;;;%s',
            number_format((float) $euer['totals']['expense'], 2, ',', '')
        );
        $lines[] = sprintf(
            'Gewinn/Verlust;;;%s',
            number_format((float) $euer['totals']['result'], 2, ',', '')
        );
        $lines[] = '';
        $lines[] = 'Hinweis;In ELSTER unter Einkommensteuer → Anlage EÜR die Beträge übertragen.';

        return [
            'filename' => 'euer-elster-' . $year . '.csv',
            'content' => "\xEF\xBB\xBF" . implode("\r\n", $lines),
        ];
    }

    public static function elsterPortalUrl(): string
    {
        return 'https://www.elster.de/eportal/start';
    }

    private static function csvEscape(string $value): string
    {
        return str_replace([';', "\r", "\n"], [' ', ' ', ' '], $value);
    }
}
