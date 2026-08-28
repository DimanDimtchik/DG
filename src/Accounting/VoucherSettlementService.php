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
        self::recordPayment(
            $voucherId,
            VoucherPaymentStatus::BANK,
            $amount,
            date('Y-m-d'),
            null,
            null,
            (int) $transferId,
        );
        BankTransferRepository::markExecuted($transferId);
    }

    /**
     * @param numeric-string|float $amount
     */
    public static function markPaid(int $voucherId, string $paymentStatus, float $amount, string $paidAt): void
    {
        self::recordPayment($voucherId, $paymentStatus, $amount, $paidAt);
    }

    /**
     * @param numeric-string|float $amount
     */
    public static function recordPayment(
        int $voucherId,
        string $paymentStatus,
        float $amount,
        string $paidAt,
        ?int $bankTransactionId = null,
        ?string $reference = null,
        ?int $bankTransferId = null,
        ?int $createdBy = null,
    ): void {
        if (!Database::isConfigured() || $voucherId < 1) {
            return;
        }
        MigrationRunner::runPending();

        $voucher = VoucherRepository::findById($voucherId);
        if ($voucher === null) {
            return;
        }

        $amount = round(max(0, $amount), 2);
        if ($amount <= 0.0) {
            $open = VoucherPaymentRepository::openAmount($voucher);
            $amount = $open > 0.0 ? $open : VoucherPaymentRepository::amountDue($voucher);
        }

        $method = match (VoucherPaymentStatus::sanitize($paymentStatus)) {
            VoucherPaymentStatus::CASH, VoucherPaymentStatus::TIP => VoucherPaymentRepository::METHOD_CASH,
            VoucherPaymentStatus::PRIVATE => VoucherPaymentRepository::METHOD_PRIVATE,
            VoucherPaymentStatus::DIRECT_DEBIT => VoucherPaymentRepository::METHOD_DIRECT_DEBIT,
            default => VoucherPaymentRepository::METHOD_BANK,
        };

        if ($paidAt === '') {
            $paidAt = date('Y-m-d');
        }

        VoucherPaymentRepository::addPayment(
            $voucherId,
            $amount,
            $paidAt,
            $method,
            $reference,
            $bankTransactionId,
            $bankTransferId,
            $createdBy,
        );
    }
}
