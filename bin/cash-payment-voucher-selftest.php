<?php
declare(strict_types=1);

/**
 * K8c — Selftest Gutschein-Zahlung an Gift-Voucher-Stamm.
 * php bin/cash-payment-voucher-selftest.php
 */
require dirname(__DIR__) . '/bootstrap.php';

$fail = 0;
$expect_true = static function (bool $cond, string $label) use (&$fail): void {
    if ($cond) {
        echo "OK  {$label}\n";
        return;
    }
    echo "FAIL {$label}\n";
    $fail++;
};

$expect_true(CashPaymentRepository::methodLabels()['voucher'] === 'Gutschein', 'Label Gutschein');
$expect_true(str_contains(CashPaymentRepository::methodHints()['voucher'], 'Code'), 'Hint Code');
$expect_true(method_exists('CashReceiptService', 'addPayment'), 'addPayment');
$expect_true(class_exists('GiftVoucherRepository'), 'GiftVoucherRepository');
$expect_true(method_exists('GiftVoucherRepository', 'findByCode'), 'findByCode');
$expect_true(method_exists('GiftVoucherRepository', 'adjustRemaining'), 'adjustRemaining');

$view = (string) file_get_contents(DG_ROOT . '/views/modules/registerkasse-bon.php');
$expect_true(str_contains($view, 'name="gift_code"'), 'UI Gutschein-Code');

$src = (string) file_get_contents(DG_ROOT . '/src/CashRegister/CashReceiptService.php');
$expect_true(str_contains($src, 'resolveGiftVoucherForPayment'), 'Resolver im Service');
$expect_true(str_contains($src, 'adjustRemaining'), 'Restwert-Anpassung');

if (!Database::isConfigured()) {
    echo "SKIP DB — UI/Methoden geprüft.\n";
    if ($fail > 0) {
        fwrite(STDERR, "cash-payment-voucher-selftest FAILED ({$fail})\n");
        exit(1);
    }
    echo "cash-payment-voucher-selftest OK (ohne DB)\n";
    exit(0);
}

MigrationRunner::runPending();
GiftVoucherRepository::ensureTables();
if (!CashReceiptRepository::tableExists() || !CashPaymentRepository::tableExists()) {
    echo "FAIL Cash-Tabellen fehlen\n";
    exit(1);
}

$sfx = substr(bin2hex(random_bytes(3)), 0, 6);
$pdo = Database::pdo();
$locId = 0;
$regId = 0;
$receiptId = 0;
$giftId = 0;
$code = 'K8C-' . strtoupper($sfx);
$cleanup = static function () use ($pdo, &$receiptId, &$regId, &$locId, &$giftId): void {
    if ($receiptId > 0) {
        $pdo->prepare('DELETE FROM dg_cash_payments WHERE receipt_id = :id')->execute(['id' => $receiptId]);
        $pdo->prepare('DELETE FROM dg_cash_receipt_lines WHERE receipt_id = :id')->execute(['id' => $receiptId]);
        $pdo->prepare('DELETE FROM dg_cash_receipts WHERE id = :id')->execute(['id' => $receiptId]);
    }
    if ($regId > 0) {
        $pdo->prepare('DELETE FROM dg_cash_sessions WHERE register_id = :rid')->execute(['rid' => $regId]);
        $pdo->prepare('DELETE FROM dg_cash_registers WHERE id = :id')->execute(['id' => $regId]);
    }
    if ($locId > 0) {
        $pdo->prepare('DELETE FROM dg_cash_locations WHERE id = :id')->execute(['id' => $locId]);
    }
    if ($giftId > 0) {
        $pdo->prepare('DELETE FROM dg_gift_vouchers WHERE id = :id')->execute(['id' => $giftId]);
    }
};

try {
    $giftId = GiftVoucherRepository::create([
        'code' => $code,
        'amount' => 25.0,
        'title' => 'Selftest K8c',
        'notes' => 'cash-payment-voucher-selftest',
    ], 1);
    $gift = GiftVoucherRepository::findById($giftId);
    $expect_true($gift !== null, 'Gutschein angelegt');
    $expect_true(round((float) ($gift['remaining_gross'] ?? 0), 2) === 25.0, 'Rest 25');

    $locId = CashLocationRepository::insert([
        'code' => 'ST-K8C-' . $sfx,
        'name' => 'Selftest Filiale K8c',
        'is_active' => 1,
    ]);
    $regId = CashRegisterRepository::insert([
        'location_id' => $locId,
        'code' => 'KR-K8C-' . $sfx,
        'name' => 'Selftest Kasse K8c',
        'next_receipt_number' => 1,
        'is_active' => 1,
    ]);
    CashSessionService::open($regId, 4, '0');
    $receiptId = CashReceiptService::startDraft($regId, 4);
    CashReceiptService::addLine($receiptId, [
        'title' => 'Ware',
        'quantity' => 1,
        'unit_price_gross' => '30,00',
        'tax_rate' => 19,
    ]);

    $noCode = false;
    try {
        CashReceiptService::addPayment($receiptId, [
            'payment_method' => 'voucher',
            'amount' => '10,00',
        ]);
    } catch (RuntimeException $e) {
        $noCode = str_contains($e->getMessage(), 'nicht gefunden') || str_contains($e->getMessage(), 'Code');
    }
    $expect_true($noCode, 'ohne Code abgewiesen');

    $overRest = false;
    try {
        CashReceiptService::addPayment($receiptId, [
            'payment_method' => 'voucher',
            'gift_code' => $code,
            'amount' => '26,00',
        ]);
    } catch (RuntimeException $e) {
        $overRest = str_contains($e->getMessage(), 'Restwert');
    }
    $expect_true($overRest, 'über Restwert abgewiesen');

    $payId = CashReceiptService::addPayment($receiptId, [
        'payment_method' => 'voucher',
        'gift_code' => $code,
        'amount' => '10,00',
    ]);
    $expect_true($payId > 0, 'Gutschein-Zahlung');
    $pay = CashPaymentRepository::findById($payId);
    $expect_true((int) ($pay['gift_voucher_id'] ?? 0) === $giftId, 'gift_voucher_id gesetzt');
    $expect_true(($pay['external_ref'] ?? '') === $code, 'Code als Referenz');

    $gift = GiftVoucherRepository::findById($giftId);
    $expect_true(round((float) ($gift['remaining_gross'] ?? 0), 2) === 15.0, 'Rest nach Einlösung 15');

    CashReceiptService::removePayment($receiptId, $payId);
    $gift = GiftVoucherRepository::findById($giftId);
    $expect_true(round((float) ($gift['remaining_gross'] ?? 0), 2) === 25.0, 'Rest nach Entfernen 25');

    // erneut einlösen + bar Rest + finalize + storno stellt Rest wieder her
    CashReceiptService::addPayment($receiptId, [
        'payment_method' => 'voucher',
        'gift_code' => $code,
        'amount' => '10,00',
    ]);
    CashReceiptService::addPayment($receiptId, [
        'payment_method' => 'cash',
        'amount' => '20,00',
    ]);
    CashReceiptService::finalizeWithoutTse($receiptId);
    $gift = GiftVoucherRepository::findById($giftId);
    $expect_true(round((float) ($gift['remaining_gross'] ?? 0), 2) === 15.0, 'Rest vor Storno 15');

    $stornoId = CashReceiptService::stornoFromFinalized($receiptId, 4, 'K8c');
    $expect_true($stornoId > 0, 'Storno');
    $gift = GiftVoucherRepository::findById($giftId);
    $expect_true(round((float) ($gift['remaining_gross'] ?? 0), 2) === 25.0, 'Rest nach Storno 25');

    $pdo->prepare('DELETE FROM dg_cash_payments WHERE receipt_id = :id')->execute(['id' => $stornoId]);
    $pdo->prepare('DELETE FROM dg_cash_receipt_lines WHERE receipt_id = :id')->execute(['id' => $stornoId]);
    $pdo->prepare('DELETE FROM dg_cash_receipts WHERE id = :id')->execute(['id' => $stornoId]);
} catch (Throwable $e) {
    echo 'FAIL Ausnahme: ' . $e->getMessage() . "\n";
    $fail++;
} finally {
    $cleanup();
    echo "OK  Selftest-Daten entfernt\n";
}

if ($fail > 0) {
    fwrite(STDERR, "cash-payment-voucher-selftest FAILED ({$fail})\n");
    exit(1);
}
echo "cash-payment-voucher-selftest OK (K8c)\n";
