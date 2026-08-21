<?php
declare(strict_types=1);

/** Zahlungsausgleich: Überweisung, Skonto, Kassenbuch-Sync. */
final class VoucherSettlementService
{
    /**
     * Überweisung als ausgeführt markieren und Beleg ausgleichen.
     */
    public static function settleFromTransfer(int $transferId): void
    {
        if (!Database::isConfigured() || $transferId < 1) {
            return;
        }
        MigrationRunner::runPending();

        $transfer = BankTransferRepository::findById($transferId);
        if ($transfer === null) {
            return;
        }

        $voucherId = (int) ($transfer['voucher_id'] ?? 0);
        if ($voucherId < 1) {
            BankTransferRepository::markExecuted($transferId);

            return;
        }

        $amount = round((float) ($transfer['amount'] ?? 0), 2);
        self::markPaid($voucherId, VoucherPaymentStatus::BANK, $amount, date('Y-m-d'));
        BankTransferRepository::markExecuted($transferId);
    }

    /**
     * @param numeric-string|float $amount
     */
    public static function markPaid(int $voucherId, string $paymentStatus, float $amount, string $paidAt): void
    {
        if (!Database::isConfigured() || $voucherId < 1) {
            return;
        }
        MigrationRunner::runPending();

        $pdo = Database::pdo();
        $stmt = $pdo->prepare(
            'UPDATE dg_vouchers
             SET payment_status = :status, paid_amount = :paid_amount, paid_at = :paid_at
             WHERE id = :id'
        );
        $stmt->execute([
            'status' => VoucherPaymentStatus::sanitize($paymentStatus),
            'paid_amount' => round(max(0, $amount), 2),
            'paid_at' => $paidAt !== '' ? $paidAt : date('Y-m-d'),
            'id' => $voucherId,
        ]);

        LedgerPostingService::rebuildForVoucher($voucherId);
        CashJournalRepository::syncForVoucher($voucherId);
    }
}
