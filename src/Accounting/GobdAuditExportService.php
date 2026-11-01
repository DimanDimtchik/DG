<?php
declare(strict_types=1);

/**
 * GoBD-/Prüferexport: Journal, Belegindex, Stammdaten, Regime-Hinweis als ZIP mit Manifest-Hash.
 */
final class GobdAuditExportService
{
    /**
     * @return array{path: string, filename: string, sha256: string, files: int}
     */
    public static function buildZip(int $year, ?string $fromDate = null, ?string $toDate = null): array
    {
        if (!Database::isConfigured()) {
            throw new RuntimeException('Datenbank nicht verbunden.');
        }
        MigrationRunner::runPending();
        $year = max(2000, $year);
        $fromDate = self::sanitizeDate($fromDate) ?? sprintf('%04d-01-01', $year);
        $toDate = self::sanitizeDate($toDate) ?? sprintf('%04d-12-31', $year);

        if (!class_exists('ZipArchive')) {
            throw new RuntimeException('PHP-ZipArchive fehlt — Prüferexport nicht möglich.');
        }

        $tmp = tempnam(sys_get_temp_dir(), 'dg_gobd_');
        if ($tmp === false) {
            throw new RuntimeException('Temporäre Datei konnte nicht angelegt werden.');
        }
        $zipPath = $tmp . '.zip';
        @unlink($tmp);

        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('ZIP konnte nicht erstellt werden.');
        }

        $manifest = [
            'product' => 'DG CRM',
            'export_type' => 'gobd_audit',
            'generated_at' => date('c'),
            'fiscal_year' => $year,
            'from_date' => $fromDate,
            'to_date' => $toDate,
            'company' => CompanySettings::displayName(),
            'vat_taxation' => VatTaxationSettings::summaryForPeriod($year, null),
            'files' => [],
        ];

        $journal = self::journalCsv($year, $fromDate, $toDate);
        $zip->addFromString('journal.csv', $journal['content']);
        $manifest['files'][] = ['name' => 'journal.csv', 'sha256' => hash('sha256', $journal['content']), 'rows' => $journal['rows']];

        $vouchers = self::voucherIndexCsv($year, $fromDate, $toDate);
        $zip->addFromString('belegindex.csv', $vouchers['content']);
        $manifest['files'][] = ['name' => 'belegindex.csv', 'sha256' => hash('sha256', $vouchers['content']), 'rows' => $vouchers['rows']];

        $accounts = self::accountsCsv();
        $zip->addFromString('konten.csv', $accounts['content']);
        $manifest['files'][] = ['name' => 'konten.csv', 'sha256' => hash('sha256', $accounts['content']), 'rows' => $accounts['rows']];

        $regimes = self::vatRegimesCsv();
        $zip->addFromString('ust_versteuerung.csv', $regimes['content']);
        $manifest['files'][] = ['name' => 'ust_versteuerung.csv', 'sha256' => hash('sha256', $regimes['content']), 'rows' => $regimes['rows']];

        $readme = self::readmeText($year, $fromDate, $toDate);
        $zip->addFromString('README.txt', $readme);
        $manifest['files'][] = ['name' => 'README.txt', 'sha256' => hash('sha256', $readme), 'rows' => 0];

        $manifestJson = json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (!is_string($manifestJson)) {
            $manifestJson = '{}';
        }
        $zip->addFromString('manifest.json', $manifestJson);
        $zip->close();

        $sha = hash_file('sha256', $zipPath);
        if (!is_string($sha)) {
            $sha = '';
        }
        AuditLog::record('gobd_audit_export', null, json_encode([
            'year' => $year,
            'from' => $fromDate,
            'to' => $toDate,
            'sha256' => $sha,
        ], JSON_UNESCAPED_UNICODE));

        return [
            'path' => $zipPath,
            'filename' => sprintf('GoBD_Pruefexport_%d_%s.zip', $year, date('Ymd_His')),
            'sha256' => $sha,
            'files' => count($manifest['files']) + 1,
        ];
    }

    /**
     * @return array{content: string, rows: int}
     */
    private static function journalCsv(int $year, string $from, string $to): array
    {
        $sql = "SELECT p.id, p.posting_date, p.fiscal_year, p.voucher_id, p.manual_batch_id,
                       p.account_number, p.contra_account, p.person_account, p.side, p.amount,
                       p.tax_rate, p.tax_key, p.description, p.document_field1, p.document_field2, p.source
                FROM dg_ledger_postings p
                WHERE p.fiscal_year = :y
                  AND p.posting_date BETWEEN :from AND :to
                ORDER BY p.posting_date ASC, p.id ASC";
        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute(['y' => $year, 'from' => $from, 'to' => $to]);
        $lines = [];
        $lines[] = self::csvLine([
            'id', 'posting_date', 'fiscal_year', 'voucher_id', 'manual_batch_id',
            'account_number', 'contra_account', 'person_account', 'side', 'amount',
            'tax_rate', 'tax_key', 'description', 'document_field1', 'document_field2', 'source',
        ]);
        $rows = 0;
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            if (!is_array($row)) {
                continue;
            }
            $lines[] = self::csvLine([
                (string) ($row['id'] ?? ''),
                (string) ($row['posting_date'] ?? ''),
                (string) ($row['fiscal_year'] ?? ''),
                (string) ($row['voucher_id'] ?? ''),
                (string) ($row['manual_batch_id'] ?? ''),
                (string) ($row['account_number'] ?? ''),
                (string) ($row['contra_account'] ?? ''),
                (string) ($row['person_account'] ?? ''),
                (string) ($row['side'] ?? ''),
                number_format((float) ($row['amount'] ?? 0), 2, '.', ''),
                (string) ($row['tax_rate'] ?? ''),
                (string) ($row['tax_key'] ?? ''),
                (string) ($row['description'] ?? ''),
                (string) ($row['document_field1'] ?? ''),
                (string) ($row['document_field2'] ?? ''),
                (string) ($row['source'] ?? ''),
            ]);
            $rows++;
        }

        return ['content' => implode("\r\n", $lines) . "\r\n", 'rows' => $rows];
    }

    /**
     * @return array{content: string, rows: int}
     */
    private static function voucherIndexCsv(int $year, string $from, string $to): array
    {
        $sql = "SELECT id, voucher_type, document_kind, document_status, is_draft, voided_at,
                       voucher_date, invoice_number, supplier_name, gross_amount, net_amount, tax_amount,
                       tax_rate, payment_status, paid_amount, paid_at
                FROM dg_vouchers
                WHERE voucher_date BETWEEN :from AND :to
                   OR (paid_at IS NOT NULL AND paid_at BETWEEN :from2 AND :to2)
                ORDER BY voucher_date ASC, id ASC";
        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute(['from' => $from, 'to' => $to, 'from2' => $from, 'to2' => $to]);
        $lines = [];
        $lines[] = self::csvLine([
            'id', 'voucher_type', 'document_kind', 'document_status', 'is_draft', 'voided_at',
            'voucher_date', 'invoice_number', 'supplier_name', 'gross_amount', 'net_amount', 'tax_amount',
            'tax_rate', 'payment_status', 'paid_amount', 'paid_at',
        ]);
        $rows = 0;
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            if (!is_array($row)) {
                continue;
            }
            // Jahr-Filter grob: Belegjahr oder Zahlungsjahr
            $vd = (string) ($row['voucher_date'] ?? '');
            $pa = (string) ($row['paid_at'] ?? '');
            if ((int) substr($vd, 0, 4) !== $year && (int) substr($pa, 0, 4) !== $year) {
                continue;
            }
            $lines[] = self::csvLine([
                (string) ($row['id'] ?? ''),
                (string) ($row['voucher_type'] ?? ''),
                (string) ($row['document_kind'] ?? ''),
                (string) ($row['document_status'] ?? ''),
                !empty($row['is_draft']) ? '1' : '0',
                (string) ($row['voided_at'] ?? ''),
                $vd,
                (string) ($row['invoice_number'] ?? ''),
                (string) ($row['supplier_name'] ?? ''),
                number_format((float) ($row['gross_amount'] ?? 0), 2, '.', ''),
                number_format((float) ($row['net_amount'] ?? 0), 2, '.', ''),
                number_format((float) ($row['tax_amount'] ?? 0), 2, '.', ''),
                (string) ($row['tax_rate'] ?? ''),
                (string) ($row['payment_status'] ?? ''),
                number_format((float) ($row['paid_amount'] ?? 0), 2, '.', ''),
                $pa,
            ]);
            $rows++;
        }

        return ['content' => implode("\r\n", $lines) . "\r\n", 'rows' => $rows];
    }

    /**
     * @return array{content: string, rows: int}
     */
    private static function accountsCsv(): array
    {
        $skr = ChartOfAccountsSettings::activeSkrType();
        $stmt = Database::pdo()->prepare(
            'SELECT account_number, name, section FROM dg_chart_accounts
             WHERE skr_type = :skr ORDER BY account_number ASC'
        );
        $stmt->execute(['skr' => $skr]);
        $lines = [];
        $lines[] = self::csvLine(['account_number', 'name', 'skr_type', 'section']);
        $rows = 0;
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            if (!is_array($row)) {
                continue;
            }
            $lines[] = self::csvLine([
                (string) ($row['account_number'] ?? ''),
                (string) ($row['name'] ?? ''),
                $skr,
                (string) ($row['section'] ?? ''),
            ]);
            $rows++;
        }

        return ['content' => implode("\r\n", $lines) . "\r\n", 'rows' => $rows];
    }

    /**
     * @return array{content: string, rows: int}
     */
    private static function vatRegimesCsv(): array
    {
        $lines = [];
        $lines[] = self::csvLine([
            'id', 'method', 'valid_from', 'bescheid_date', 'aktenzeichen', 'finanzamt',
            'file_original_name', 'created_at', 'superseded_at',
        ]);
        $rows = 0;
        foreach (VatTaxationSettings::history() as $r) {
            $lines[] = self::csvLine([
                (string) ($r['id'] ?? ''),
                (string) ($r['method'] ?? ''),
                (string) ($r['valid_from'] ?? ''),
                (string) ($r['bescheid_date'] ?? ''),
                (string) ($r['aktenzeichen'] ?? ''),
                (string) ($r['finanzamt'] ?? ''),
                (string) ($r['file_original_name'] ?? ''),
                (string) ($r['created_at'] ?? ''),
                (string) ($r['superseded_at'] ?? ''),
            ]);
            $rows++;
        }

        return ['content' => implode("\r\n", $lines) . "\r\n", 'rows' => $rows];
    }

    private static function readmeText(int $year, string $from, string $to): string
    {
        return "DG CRM — GoBD-Prüferexport\r\n"
            . "Geschäftsjahr: {$year}\r\n"
            . "Zeitraum: {$from} bis {$to}\r\n"
            . "Inhalt: journal.csv, belegindex.csv, konten.csv, ust_versteuerung.csv, manifest.json\r\n"
            . "Hinweis: Keine Finanzamt-Softwarezulassung; Export zur Nachvollziehbarkeit der Buchführung.\r\n"
            . "Verfahrensdokumentation: docs/VERFAHRENSDOKUMENTATION-VORLAGE.md (im Produktumfang).\r\n";
    }

    /**
     * @param list<string> $cols
     */
    private static function csvLine(array $cols): string
    {
        $out = [];
        foreach ($cols as $c) {
            $c = str_replace('"', '""', $c);
            $out[] = '"' . $c . '"';
        }

        return implode(';', $out);
    }

    private static function sanitizeDate(?string $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }
        $ts = strtotime($value);

        return $ts !== false ? date('Y-m-d', $ts) : null;
    }
}
