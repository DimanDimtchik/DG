<?php
declare(strict_types=1);

/**
 * DATEV EXTF Buchungsstapel (Format 700/21) aus dem Buchungsjournal exportieren.
 */
final class DatevExtfExporter
{
    /**
     * @return array{filename: string, content: string, count: int}
     */
    public static function export(int $year, ?string $fromDate = null, ?string $toDate = null, bool $includeManual = true): array
    {
        if (!Database::isConfigured()) {
            throw new RuntimeException('Datenbank nicht verbunden.');
        }
        MigrationRunner::runPending();

        $cfg = DatevExportSettings::config();
        if ($cfg['consultant_number'] === '' || $cfg['client_number'] === '') {
            throw new RuntimeException('DATEV Berater- und Mandantennummer fehlen — bitte unter Einstellungen → Kontenrahmen pflegen.');
        }

        $start = $fromDate !== null && $fromDate !== '' ? $fromDate : sprintf('%04d-01-01', $year);
        $end = $toDate !== null && $toDate !== '' ? $toDate : sprintf('%04d-12-31', $year);

        $rows = $includeManual
            ? LedgerExportRepository::postingsForExport($year, $start, $end)
            : self::voucherOnlyPostings($year, $start, $end);

        $created = date('YmdHis');
        $startDatev = self::datevDate($start);
        $endDatev = self::datevDate($end);
        $company = CompanySettings::displayName();

        $headerMeta = [
            'EXTF',
            '700',
            '21',
            'Buchungsstapel',
            '13',
            $created,
            $startDatev,
            $endDatev,
            $cfg['consultant_number'],
            $cfg['client_number'],
            '',
            $company !== '' ? $company : 'Rechnungswesen',
            '',
            'EUR',
        ];

        $columns = [
            'Umsatz (ohne Soll/Haben-Kz)',
            'Soll/Haben-Kennzeichen',
            'WKZ Umsatz',
            'Kurs',
            'Basis-Umsatz',
            'WKZ Basis-Umsatz',
            'Konto',
            'Gegenkonto (ohne BU-Schlüssel)',
            'BU-Schlüssel',
            'Belegdatum',
            'Belegfeld 1',
            'Belegfeld 2',
            'Buchungstext',
        ];

        $lines = [self::csvLine($headerMeta), self::csvLine($columns)];

        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $amount = round((float) ($row['amount'] ?? 0), 2);
            if ($amount == 0.0) {
                continue;
            }
            $side = (string) ($row['side'] ?? 'debit') === 'credit' ? 'H' : 'S';
            $doc1 = trim((string) ($row['document_field1'] ?? ''));
            if ($doc1 === '') {
                $doc1 = trim((string) ($row['invoice_number'] ?? ''));
            }
            $doc2 = trim((string) ($row['document_field2'] ?? ''));
            $contra = trim((string) ($row['contra_account'] ?? ''));
            if ($contra === 'Sammel') {
                $contra = trim((string) ($row['person_account'] ?? ''));
            }

            $lines[] = self::csvLine([
                self::amount($amount),
                $side,
                'EUR',
                '1',
                '',
                '',
                (string) ($row['account_number'] ?? ''),
                $contra,
                (string) ($row['tax_key'] ?? ''),
                self::datevShortDate((string) ($row['posting_date'] ?? '')),
                mb_substr($doc1, 0, 36),
                mb_substr($doc2, 0, 36),
                mb_substr((string) ($row['description'] ?? ''), 0, 60),
            ]);
        }

        $count = max(0, count($lines) - 2);
        $filename = sprintf('EXTF_Buchungsstapel_%d_%s.csv', $year, date('Ymd'));

        return [
            'filename' => $filename,
            'content' => implode("\r\n", $lines) . "\r\n",
            'count' => $count,
        ];
    }

    private static function amount(float $value): string
    {
        return number_format($value, 2, ',', '');
    }

    private static function datevDate(string $isoDate): string
    {
        $ts = strtotime($isoDate);

        return $ts !== false ? date('Ymd', $ts) : date('Ymd');
    }

    private static function datevShortDate(string $isoDate): string
    {
        $ts = strtotime($isoDate);

        return $ts !== false ? date('dm', $ts) : '';
    }

    /**
     * @param list<string|int|float> $fields
     */
    private static function csvLine(array $fields): string
    {
        $parts = [];
        foreach ($fields as $field) {
            $value = (string) $field;
            if (str_contains($value, '"') || str_contains($value, ';') || str_contains($value, "\n")) {
                $value = '"' . str_replace('"', '""', $value) . '"';
            }
            $parts[] = $value;
        }

        return implode(';', $parts);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function voucherOnlyPostings(int $year, string $start, string $end): array
    {
        $stmt = Database::pdo()->prepare(
            "SELECT p.*, v.invoice_number, v.supplier_name
             FROM dg_ledger_postings p
             LEFT JOIN dg_vouchers v ON v.id = p.voucher_id
             WHERE p.fiscal_year = :y
               AND p.source = 'voucher'
               AND p.posting_date BETWEEN :start AND :end
             ORDER BY p.posting_date ASC, p.id ASC"
        );
        $stmt->execute(['y' => $year, 'start' => $start, 'end' => $end]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}
