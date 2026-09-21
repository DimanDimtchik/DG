<?php
declare(strict_types=1);

/** Kassenbuch — Ein-/Ausgänge aus Barzahlungen (dg_voucher_payments method=cash). */
final class CashJournalRepository
{
    /**
     * @return list<array<string, mixed>>
     */
    public static function listForYear(int $year): array
    {
        return self::listForPeriod(AccountingPeriodFilter::fromRequest(['year' => $year]));
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function listForPeriod(AccountingPeriodFilter $period): array
    {
        return self::listForDateRange($period->dateFrom, $period->dateTo);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function listForDateRange(string $dateFrom, string $dateTo): array
    {
        if (!Database::isConfigured()) {
            return [];
        }
        MigrationRunner::runPending();

        $stmt = Database::pdo()->prepare(
            'SELECT j.*, v.invoice_number, v.supplier_name, v.voucher_type
             FROM dg_cash_journal j
             LEFT JOIN dg_vouchers v ON v.id = j.voucher_id
             WHERE j.entry_date BETWEEN :from AND :to
             ORDER BY j.entry_date ASC, j.id ASC'
        );
        $stmt->execute(['from' => $dateFrom, 'to' => $dateTo]);
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
        return self::totalsForPeriod(AccountingPeriodFilter::fromRequest(['year' => $year]));
    }

    /**
     * @return array{in: float, out: float, balance: float}
     */
    public static function totalsForPeriod(AccountingPeriodFilter $period): array
    {
        $totals = ['in' => 0.0, 'out' => 0.0, 'balance' => 0.0];
        foreach (self::listForPeriod($period) as $row) {
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

    /**
     * Kassenbuch aus Bar-Zahlungen des Belegs aufbauen (auch bei Teilzahlung / payment_status=partial).
     */
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

        $isIncome = LedgerAccounts::isIncomeDirection((string) ($voucher['voucher_type'] ?? 'expense'));
        $side = $isIncome ? 'in' : 'out';
        $skr = ChartOfAccountsSettings::activeSkrType();
        $cashAccount = ChartOfAccountsSettings::sanitizeSkrType($skr) === 'skr04' ? '1600' : '1000';

        $descBase = trim((string) ($voucher['invoice_number'] ?? ''));
        if ($descBase !== '') {
            $descBase = 'RE ' . $descBase;
        }
        $supplier = trim((string) ($voucher['supplier_name'] ?? ''));
        if ($supplier !== '') {
            $descBase = $descBase !== '' ? $descBase . ' · ' . $supplier : $supplier;
        }
        $kindLabel = VoucherDocumentKind::label((string) ($voucher['document_kind'] ?? ''));
        if ($kindLabel !== '') {
            $descBase = $descBase !== '' ? $kindLabel . ' ' . $descBase : $kindLabel;
        }

        $cashPayments = [];
        foreach (VoucherPaymentRepository::listForVoucher($voucherId) as $pay) {
            if ((string) ($pay['payment_method'] ?? '') !== VoucherPaymentRepository::METHOD_CASH) {
                continue;
            }
            $cashPayments[] = [
                'amount' => (float) ($pay['amount'] ?? 0),
                'payment_date' => (string) ($pay['payment_date'] ?? ''),
                'reference_text' => (string) ($pay['reference_text'] ?? ''),
            ];
        }

        // Legacy: gesamter Beleg als Bar ohne Zahlungshistorie
        if ($cashPayments === []) {
            $status = VoucherPaymentStatus::sanitize((string) ($voucher['payment_status'] ?? ''));
            if (!in_array($status, [VoucherPaymentStatus::CASH, VoucherPaymentStatus::TIP], true)) {
                return;
            }
            $amount = round((float) ($voucher['paid_amount'] ?? 0), 2);
            if ($amount <= 0.0) {
                $amount = round((float) ($voucher['gross_amount'] ?? 0), 2);
            }
            if ($amount <= 0.0) {
                return;
            }
            $cashPayments[] = [
                'amount' => $amount,
                'payment_date' => (string) ($voucher['paid_at'] ?? $voucher['voucher_date'] ?? date('Y-m-d')),
                'reference_text' => '',
            ];
        }

        $insert = $pdo->prepare(
            'INSERT INTO dg_cash_journal (entry_date, voucher_id, account_number, side, amount, description)
             VALUES (:entry_date, :voucher_id, :account_number, :side, :amount, :description)'
        );

        foreach ($cashPayments as $pay) {
            $amount = round((float) ($pay['amount'] ?? 0), 2);
            if ($amount <= 0.0) {
                continue;
            }
            $entryDate = (string) ($pay['payment_date'] ?? '');
            if ($entryDate === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $entryDate)) {
                $entryDate = (string) ($voucher['voucher_date'] ?? date('Y-m-d'));
            }
            $ref = trim((string) ($pay['reference_text'] ?? ''));
            $desc = $descBase;
            if ($ref !== '') {
                $desc = $desc !== '' ? $desc . ' · ' . $ref : $ref;
            }
            if ($desc === '') {
                $desc = 'Bareinnahme';
            }

            $insert->execute([
                'entry_date' => $entryDate,
                'voucher_id' => $voucherId,
                'account_number' => $cashAccount,
                'side' => $side,
                'amount' => $amount,
                'description' => mb_substr($desc, 0, 500),
            ]);
        }
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
