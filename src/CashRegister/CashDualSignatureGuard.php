<?php
declare(strict_types=1);

/**
 * K9d — ein Zahlungsweg = eine TSE-Transaktion.
 * Verhindert parallele Signatur über Beleg-Barzahlung und Registerkassen-Bon.
 */
final class CashDualSignatureGuard
{
    /**
     * Bon darf nicht signiert werden, wenn der verknüpfte Beleg schon TSE-signiert ist.
     *
     * @param array<string, mixed> $receipt
     */
    public static function assertCashReceiptMaySign(array $receipt): void
    {
        $voucherId = (int) ($receipt['voucher_id'] ?? 0);
        if ($voucherId <= 0) {
            return;
        }
        if (!class_exists('TseTransactionRepository') || !TseTransactionRepository::tableExists()) {
            return;
        }
        if (TseTransactionRepository::hasFinishedForVoucher($voucherId)) {
            throw new RuntimeException(
                'Dieser Auftrag/Beleg ist bereits über die Beleg-Barzahlung TSE-signiert. '
                . 'Kein zweiter Signaturweg über die Registerkasse (Doppel-Signatur).'
            );
        }
    }

    /**
     * Beleg-Barzahlung darf nicht signiert werden, wenn schon ein festgeschriebener
     * Registerkassen-Bon mit TSE zu diesem Beleg existiert.
     */
    public static function assertVoucherCashPaymentMaySign(int $voucherId): void
    {
        if ($voucherId <= 0) {
            return;
        }
        if (!CashReceiptRepository::tableExists()) {
            return;
        }
        $stmt = Database::pdo()->prepare(
            "SELECT id, receipt_number_display, tse_tx_id
             FROM dg_cash_receipts
             WHERE voucher_id = :vid
               AND status = 'finalized'
               AND receipt_type = 'sale'
               AND tse_tx_id <> ''
             ORDER BY id DESC
             LIMIT 1"
        );
        $stmt->execute(['vid' => $voucherId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (is_array($row)) {
            $display = trim((string) ($row['receipt_number_display'] ?? ''));
            if ($display === '') {
                $display = '#' . (int) ($row['id'] ?? 0);
            }
            throw new RuntimeException(
                'Zum Beleg existiert bereits ein TSE-signierter Kassenbon (' . $display . '). '
                . 'Keine zweite Signatur über Beleg-Barzahlung.'
            );
        }
    }
}
