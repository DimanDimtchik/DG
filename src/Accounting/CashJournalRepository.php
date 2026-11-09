<?php
declare(strict_types=1);

/**
 * Kassenbuch — Ein-/Ausgänge aus Barzahlungen
 * (Beleg: dg_voucher_payments method=cash · Registerkasse: K15b).
 */
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
     * K15b: Baranteil eines festgeschriebenen Registerkassen-Bons ins FiBu-Kassenbuch.
     * Idempotent (Löschen + Neuaufbau je cash_receipt_id). Kein Ersatz für Register-Tagesabschluss.
     */
    public static function syncForCashReceipt(int $receiptId): void
    {
        if (!Database::isConfigured() || $receiptId < 1) {
            return;
        }
        if (!CashReceiptRepository::tableExists() || !CashPaymentRepository::tableExists()) {
            return;
        }
        MigrationRunner::runPending();
        if (!self::hasCashReceiptColumn()) {
            return;
        }

        $pdo = Database::pdo();
        $pdo->prepare('DELETE FROM dg_cash_journal WHERE cash_receipt_id = :id')
            ->execute(['id' => $receiptId]);

        $receipt = CashReceiptRepository::findById($receiptId);
        if ($receipt === null) {
            return;
        }
        if (($receipt['status'] ?? '') !== CashReceiptService::STATUS_FINALIZED) {
            return;
        }
        if (($receipt['receipt_type'] ?? CashReceiptService::TYPE_SALE) !== CashReceiptService::TYPE_SALE) {
            return;
        }

        $cashAmount = 0.0;
        foreach (CashPaymentRepository::listByReceipt($receiptId) as $pay) {
            if (strtolower(trim((string) ($pay['payment_method'] ?? ''))) !== 'cash') {
                continue;
            }
            $cashAmount = round($cashAmount + (float) ($pay['amount'] ?? 0), 2);
        }
        if ($cashAmount <= 0) {
            return;
        }

        $skr = ChartOfAccountsSettings::activeSkrType();
        $cashAccount = CashRegisterFiBuSettings::accountForMethod('cash', $skr);
        $entryDate = self::receiptEntryDate($receipt);
        $display = trim((string) ($receipt['receipt_number_display'] ?? ''));
        if ($display === '') {
            $display = (string) (int) ($receipt['receipt_number'] ?? $receiptId);
        }
        $desc = 'Registerkasse Bon ' . $display . ' · Bar';

        $pdo->prepare(
            'INSERT INTO dg_cash_journal
                (entry_date, voucher_id, cash_receipt_id, account_number, side, amount, description)
             VALUES
                (:entry_date, NULL, :cash_receipt_id, :account_number, :side, :amount, :description)'
        )->execute([
            'entry_date' => $entryDate,
            'cash_receipt_id' => $receiptId,
            'account_number' => $cashAccount,
            'side' => 'in',
            'amount' => $cashAmount,
            'description' => mb_substr($desc, 0, 500),
        ]);
    }

    /** K15e — Bar-Storno als Kassenbuch-Ausgang. */
    public static function syncForCashStornoReceipt(int $receiptId): void
    {
        if (!Database::isConfigured() || $receiptId < 1) {
            return;
        }
        if (!CashReceiptRepository::tableExists() || !CashPaymentRepository::tableExists()) {
            return;
        }
        MigrationRunner::runPending();
        if (!self::hasCashReceiptColumn()) {
            return;
        }

        $pdo = Database::pdo();
        $pdo->prepare('DELETE FROM dg_cash_journal WHERE cash_receipt_id = :id')
            ->execute(['id' => $receiptId]);

        $receipt = CashReceiptRepository::findById($receiptId);
        if ($receipt === null) {
            return;
        }
        if (($receipt['status'] ?? '') !== CashReceiptService::STATUS_FINALIZED) {
            return;
        }
        if (($receipt['receipt_type'] ?? '') !== CashReceiptService::TYPE_STORNO) {
            return;
        }

        $cashAmount = 0.0;
        foreach (CashPaymentRepository::listByReceipt($receiptId) as $pay) {
            if (strtolower(trim((string) ($pay['payment_method'] ?? ''))) !== 'cash') {
                continue;
            }
            $cashAmount = round($cashAmount + abs((float) ($pay['amount'] ?? 0)), 2);
        }
        if ($cashAmount <= 0) {
            return;
        }

        $skr = ChartOfAccountsSettings::activeSkrType();
        $cashAccount = CashRegisterFiBuSettings::accountForMethod('cash', $skr);
        $entryDate = self::receiptEntryDate($receipt);
        $display = trim((string) ($receipt['receipt_number_display'] ?? ''));
        if ($display === '') {
            $display = (string) (int) ($receipt['receipt_number'] ?? $receiptId);
        }
        $desc = 'Registerkasse Storno ' . $display . ' · Bar';

        $pdo->prepare(
            'INSERT INTO dg_cash_journal
                (entry_date, voucher_id, cash_receipt_id, account_number, side, amount, description)
             VALUES
                (:entry_date, NULL, :cash_receipt_id, :account_number, :side, :amount, :description)'
        )->execute([
            'entry_date' => $entryDate,
            'cash_receipt_id' => $receiptId,
            'account_number' => $cashAccount,
            'side' => 'out',
            'amount' => $cashAmount,
            'description' => mb_substr($desc, 0, 500),
        ]);
    }

    public static function hasCashReceiptColumnPublic(): bool
    {
        return self::hasCashReceiptColumn();
    }

    private static function hasCashReceiptColumn(): bool
    {
        if (!Database::isConfigured()) {
            return false;
        }
        static $cached = null;
        if ($cached !== null) {
            return $cached;
        }
        try {
            $stmt = Database::pdo()->query("SHOW COLUMNS FROM dg_cash_journal LIKE 'cash_receipt_id'");
            $cached = $stmt !== false && $stmt->fetch(PDO::FETCH_ASSOC) !== false;
        } catch (Throwable) {
            $cached = false;
        }

        return $cached;
    }

    /**
     * @param array<string, mixed> $receipt
     */
    private static function receiptEntryDate(array $receipt): string
    {
        $finished = trim((string) ($receipt['finished_at'] ?? ''));
        if (preg_match('/^(\d{4}-\d{2}-\d{2})/', $finished, $m) === 1) {
            return $m[1];
        }
        $sessionId = (int) ($receipt['session_id'] ?? 0);
        if ($sessionId > 0 && CashSessionRepository::tableExists()) {
            $session = CashSessionRepository::findById($sessionId);
            $bd = trim((string) ($session['business_date'] ?? ''));
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $bd) === 1) {
                return $bd;
            }
        }

        return date('Y-m-d');
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
        $voucherId = (int) ($row['voucher_id'] ?? 0);
        $row['tse_signed'] = $voucherId > 0
            && class_exists('TseTransactionRepository')
            && TseTransactionRepository::hasFinishedForVoucher($voucherId);

        return $row;
    }
}
