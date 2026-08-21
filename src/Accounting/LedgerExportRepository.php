<?php
declare(strict_types=1);

/** Gemeinsame Journal-Abfragen für DATEV, Agenda, Addison. */
final class LedgerExportRepository
{
    /**
     * @return list<array<string, mixed>>
     */
    public static function postingsForExport(int $year, ?string $fromDate = null, ?string $toDate = null): array
    {
        if (!Database::isConfigured()) {
            return [];
        }
        MigrationRunner::runPending();

        $start = $fromDate !== null && $fromDate !== '' ? $fromDate : sprintf('%04d-01-01', $year);
        $end = $toDate !== null && $toDate !== '' ? $toDate : sprintf('%04d-12-31', $year);

        $stmt = Database::pdo()->prepare(
            "SELECT p.*, v.invoice_number, v.supplier_name
             FROM dg_ledger_postings p
             LEFT JOIN dg_vouchers v ON v.id = p.voucher_id
             WHERE p.fiscal_year = :y
               AND p.source IN ('voucher', 'manual', 'closing')
               AND p.posting_date BETWEEN :start AND :end
             ORDER BY p.posting_date ASC, p.id ASC"
        );
        $stmt->execute(['y' => $year, 'start' => $start, 'end' => $end]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}
