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
        $iban = strtoupper(str_replace(' ', '', (string) ($tx['counterparty_iban'] ?? '')));
        $txDate = (string) ($tx['transaction_date'] ?? '');
        $pdo = Database::pdo();

        $dateClause = '';
        $dateParams = [];
        if ($txDate !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $txDate) === 1) {
            $dateClause = ' AND v.voucher_date BETWEEN :date_from AND :date_to';
            $dateParams = [
                'date_from' => date('Y-m-d', strtotime($txDate . ' -120 days')),
                'date_to' => date('Y-m-d', strtotime($txDate . ' +30 days')),
            ];
        }

        if ($iban !== '') {
            $ibanStmt = $pdo->prepare(
                "SELECT v.id, v.gross_amount, v.paid_amount, v.discount_amount, v.invoice_number
                 FROM dg_vouchers v
                 INNER JOIN dg_contacts c ON c.id = v.contact_id
                 WHERE v.is_draft = 0 AND v.payment_status IN ('open', 'direct_debit')
                   AND REPLACE(UPPER(c.bank_accounts), ' ', '') LIKE :iban
                   {$dateClause}
                 ORDER BY v.voucher_date DESC, v.id DESC"
            );
            $ibanStmt->execute(['iban' => '%' . $iban . '%'] + $dateParams);
            while ($row = $ibanStmt->fetch(PDO::FETCH_ASSOC)) {
                if (!is_array($row)) {
                    continue;
                }
                if (self::amountMatches($row, $amount)) {
                    return (int) $row['id'];
                }
            }
        }

        $stmt = $pdo->prepare(
            "SELECT v.id, v.gross_amount, v.paid_amount, v.discount_amount, v.invoice_number, v.payment_status
             FROM dg_vouchers v
             WHERE v.is_draft = 0 AND v.payment_status IN ('open', 'direct_debit')
               {$dateClause}
             ORDER BY v.voucher_date DESC, v.id DESC"
        );
        $stmt->execute($dateParams);

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            if (!is_array($row)) {
                continue;
            }
            if (!self::amountMatches($row, $amount)) {
                continue;
            }
            $invoice = mb_strtolower(trim((string) ($row['invoice_number'] ?? '')));
            if ($invoice !== '' && str_contains($reference, $invoice)) {
                return (int) $row['id'];
            }
        }

        $stmt2 = $pdo->prepare(
            "SELECT id, gross_amount, paid_amount, discount_amount FROM dg_vouchers
             WHERE is_draft = 0 AND payment_status IN ('open', 'direct_debit')
               {$dateClause}
             ORDER BY voucher_date DESC, id DESC"
        );
        $stmt2->execute($dateParams);
        while ($row = $stmt2->fetch(PDO::FETCH_ASSOC)) {
            if (is_array($row) && self::amountMatches($row, $amount)) {
                return (int) $row['id'];
            }
        }

        return 0;
    }

    /**
     * @param array<string, mixed> $voucher
     */
    private static function amountMatches(array $voucher, float $amount): bool
    {
        $gross = round((float) ($voucher['gross_amount'] ?? 0), 2);
        $paid = round((float) ($voucher['paid_amount'] ?? 0), 2);
        $discount = round((float) ($voucher['discount_amount'] ?? 0), 2);
        $open = $gross - $paid;
        if ($open <= 0.0) {
            $open = $gross;
        }
        $expected = round(max(0.0, $gross - $discount), 2);

        return abs($open - $amount) <= 0.02
            || abs($gross - $amount) <= 0.02
            || abs($expected - $amount) <= 0.02;
    }
}
