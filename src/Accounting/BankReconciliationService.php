<?php
declare(strict_types=1);

/** Automatischer Bankabgleich: CAMT-Umsätze → Belege/OPOS. */
final class BankReconciliationService
{
    public static function autoMatchBatch(string $batch): int
    {
        if (!Database::isConfigured() || $batch === '') {
            return 0;
        }
        MigrationRunner::runPending();

        $stmt = Database::pdo()->prepare(
            "SELECT * FROM dg_bank_transactions WHERE import_batch = :batch AND match_status = 'open'"
        );
        $stmt->execute(['batch' => $batch]);
        $matched = 0;

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            if (!is_array($row)) {
                continue;
            }
            $voucherId = self::findVoucherForTransaction($row);
            if ($voucherId > 0) {
                self::applyMatch((int) $row['id'], $voucherId, $row);
                $matched++;
            }
        }

        return $matched;
    }

    public static function matchManually(int $transactionId, int $voucherId): void
    {
        $tx = BankTransactionRepository::findById($transactionId);
        if ($tx === null) {
            throw new RuntimeException('Bankumsatz nicht gefunden.');
        }
        $voucher = VoucherRepository::findById($voucherId);
        if ($voucher === null) {
            throw new RuntimeException('Beleg nicht gefunden.');
        }
        self::applyMatch($transactionId, $voucherId, $tx);
    }

    /**
     * @param array<string, mixed> $tx
     */
    private static function applyMatch(int $transactionId, int $voucherId, array $tx): void
    {
        $voucher = VoucherRepository::findById($voucherId);
        if ($voucher === null) {
            throw new RuntimeException('Beleg nicht gefunden.');
        }
        $amount = round(abs((float) ($tx['amount'] ?? 0)), 2);
        $gross = round((float) ($voucher['gross_amount'] ?? 0), 2);
        $discount = round((float) ($voucher['discount_amount'] ?? 0), 2);
        $expected = round(max(0, $gross - $discount), 2);
        VoucherSettlementService::markPaid(
            $voucherId,
            VoucherPaymentStatus::BANK,
            $amount > 0.0 ? $amount : $expected,
            (string) ($tx['transaction_date'] ?? date('Y-m-d'))
        );
        BankTransactionRepository::markMatched($transactionId, $voucherId);
    }

    /**
     * @param array<string, mixed> $tx
     */
    private static function findVoucherForTransaction(array $tx): int
    {
        $amount = round(abs((float) ($tx['amount'] ?? 0)), 2);
        if ($amount <= 0.0) {
            return 0;
        }

        $reference = mb_strtolower((string) ($tx['reference_text'] ?? '') . ' ' . (string) ($tx['end_to_end_id'] ?? ''));
        $pdo = Database::pdo();

        $stmt = $pdo->query(
            "SELECT id, gross_amount, paid_amount, invoice_number, payment_status
             FROM dg_vouchers
             WHERE is_draft = 0 AND payment_status IN ('open', 'direct_debit')
             ORDER BY voucher_date DESC, id DESC"
        );

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            if (!is_array($row)) {
                continue;
            }
            $gross = round((float) ($row['gross_amount'] ?? 0), 2);
            $open = $gross - round((float) ($row['paid_amount'] ?? 0), 2);
            if ($open <= 0.0) {
                $open = $gross;
            }
            if (abs($open - $amount) > 0.02 && abs($gross - $amount) > 0.02) {
                continue;
            }
            $invoice = mb_strtolower(trim((string) ($row['invoice_number'] ?? '')));
            if ($invoice !== '' && str_contains($reference, $invoice)) {
                return (int) $row['id'];
            }
        }

        $stmt2 = $pdo->prepare(
            "SELECT id FROM dg_vouchers
             WHERE is_draft = 0 AND payment_status IN ('open', 'direct_debit')
               AND ABS(gross_amount - :amount) < 0.02
             ORDER BY voucher_date DESC LIMIT 1"
        );
        $stmt2->execute(['amount' => $amount]);
        $id = (int) $stmt2->fetchColumn();

        return $id > 0 ? $id : 0;
    }
}
