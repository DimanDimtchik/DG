<?php
declare(strict_types=1);

/** Kassenbuch — Ein-/Ausgänge bei Barzahlung (payment_status = cash). */
final class CashJournalRepository
{
    /**
     * @return list<array<string, mixed>>
     */
    public static function listForYear(int $year): array
    {
        if (!Database::isConfigured()) {
            return [];
        }
        MigrationRunner::runPending();

        $stmt = Database::pdo()->prepare(
            'SELECT j.*, v.invoice_number, v.supplier_name, v.voucher_type
             FROM dg_cash_journal j
             LEFT JOIN dg_vouchers v ON v.id = j.voucher_id
             WHERE YEAR(j.entry_date) = :y
             ORDER BY j.entry_date ASC, j.id ASC'
        );
        $stmt->execute(['y' => $year]);
        $rows = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $rows[] = self::enrich($row);
        }

        return $rows;
    }

    /**
     * @return array{in: float, out: float, balance: float}
     */
    public static function totalsForYear(int $year): array
    {
        $totals = ['in' => 0.0, 'out' => 0.0, 'balance' => 0.0];
        foreach (self::listForYear($year) as $row) {
            $amount = round((float) ($row['amount'] ?? 0), 2);
            if (($row['side'] ?? '') === 'in') {
                $totals['in'] = round($totals['in'] + $amount, 2);
            } else {
                $totals['out'] = round($totals['out'] + $amount, 2);
            }
        }
        $totals['balance'] = round($totals['in'] - $totals['out'], 2);

        return $totals;
    }

    public static function syncForVoucher(int $voucherId): void
    {
        if (!Database::isConfigured() || $voucherId < 1) {
            return;
        }
        MigrationRunner::runPending();

        $pdo = Database::pdo();
        $pdo->prepare('DELETE FROM dg_cash_journal WHERE voucher_id = :id')->execute(['id' => $voucherId]);

        $stmt = $pdo->prepare('SELECT * FROM dg_vouchers WHERE id = :id AND is_draft = 0 LIMIT 1');
        $stmt->execute(['id' => $voucherId]);
        $voucher = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!is_array($voucher)) {
            return;
        }

        if (VoucherPaymentStatus::sanitize((string) ($voucher['payment_status'] ?? '')) !== VoucherPaymentStatus::CASH) {
            return;
        }

        $amount = round((float) ($voucher['paid_amount'] ?? 0), 2);
        if ($amount <= 0.0) {
            $amount = round((float) ($voucher['gross_amount'] ?? 0), 2);
        }
        if ($amount <= 0.0) {
            return;
        }

        $skr = ChartOfAccountsSettings::activeSkrType();
        $cashAccount = ChartOfAccountsSettings::sanitizeSkrType($skr) === 'skr04' ? '1600' : '1000';
        $isIncome = LedgerAccounts::isIncomeDirection((string) ($voucher['voucher_type'] ?? 'expense'));
        $side = $isIncome ? 'in' : 'out';

        $desc = trim((string) ($voucher['invoice_number'] ?? ''));
        if ($desc !== '') {
            $desc = 'RE ' . $desc;
        }
        $supplier = trim((string) ($voucher['supplier_name'] ?? ''));
        if ($supplier !== '') {
            $desc = $desc !== '' ? $desc . ' · ' . $supplier : $supplier;
        }

        $pdo->prepare(
            'INSERT INTO dg_cash_journal (entry_date, voucher_id, account_number, side, amount, description)
             VALUES (:entry_date, :voucher_id, :account_number, :side, :amount, :description)'
        )->execute([
            'entry_date' => (string) ($voucher['voucher_date'] ?? date('Y-m-d')),
            'voucher_id' => $voucherId,
            'account_number' => $cashAccount,
            'side' => $side,
            'amount' => $amount,
            'description' => mb_substr($desc, 0, 500),
        ]);
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private static function enrich(array $row): array
    {
        $amount = round((float) ($row['amount'] ?? 0), 2);
        $row['amount_display'] = number_format($amount, 2, ',', '.') . ' €';
        $row['side_label'] = ($row['side'] ?? '') === 'in' ? 'Ein' : 'Aus';

        return $row;
    }
}
