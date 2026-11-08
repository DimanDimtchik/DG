<?php
declare(strict_types=1);

/**
 * Selftest K9d: Doppel-Signatur-Guard (ohne Live-TSE).
 * php bin/cash-dual-signature-selftest.php
 */
require dirname(__DIR__) . '/bootstrap.php';

$fail = 0;
$expect = static function (bool $cond, string $msg) use (&$fail): void {
    if ($cond) {
        echo "OK  {$msg}\n";
        return;
    }
    echo "FAIL {$msg}\n";
    $fail++;
};

$expect(class_exists('CashDualSignatureGuard'), 'CashDualSignatureGuard');
$expect(class_exists('CashVoucherToReceiptService'), 'CashVoucherToReceiptService');

// Ohne voucher_id: kein Fehler
try {
    CashDualSignatureGuard::assertCashReceiptMaySign(['voucher_id' => 0]);
    $expect(true, 'ohne voucher_id ok');
} catch (Throwable $e) {
    $expect(false, 'ohne voucher_id ok: ' . $e->getMessage());
}

$view = (string) file_get_contents(DG_ROOT . '/views/modules/registerkasse-bons.php');
$expect(str_contains($view, 'voucher_id'), 'UI Beleg-ID am Bon-Start');
$expect(str_contains(
    (string) file_get_contents(DG_ROOT . '/views/modules/registerkasse-bon.php'),
    'cash_receipt_storno'
), 'UI Storno am Bon');

if ($fail > 0) {
    fwrite(STDERR, "cash-dual-signature-selftest: {$fail} Fehler\n");
    exit(1);
}
echo "cash-dual-signature-selftest OK\n";
exit(0);
