<?php
declare(strict_types=1);

/**
 * Selftest K7c: Zahlung → Status paying, Positionen gesperrt.
 * php bin/cash-receipt-payment-selftest.php
 */
require dirname(__DIR__) . '/bootstrap.php';

$fail = 0;

function expect_true(bool $cond, string $msg): void
{
    global $fail;
    if ($cond) {
        echo "OK  {$msg}\n";
    } else {
        echo "FAIL {$msg}\n";
        $fail++;
    }
}

expect_true(method_exists('CashReceiptService', 'addPayment'), 'addPayment');
expect_true(CashReceiptService::STATUS_PAYING === 'paying', 'STATUS_PAYING');

if (!Database::isConfigured()) {
    echo "SKIP DB — Klassen ok.\n";
    if ($fail > 0) {
        fwrite(STDERR, "cash-receipt-payment-selftest: {$fail} Fehler\n");
        exit(1);
    }
    echo "cash-receipt-payment-selftest OK (ohne DB)\n";
    exit(0);
}

MigrationRunner::runPending();
if (!CashReceiptRepository::tableExists() || !CashPaymentRepository::tableExists()) {
    echo "FAIL Bon-/Zahlungs-Tabellen fehlen\n";
    exit(1);
}

$codeLoc = 'ST-K7C-' . substr(bin2hex(random_bytes(3)), 0, 6);
$codeReg = 'KR-K7C-' . substr(bin2hex(random_bytes(3)), 0, 6);
$locId = CashLocationRepository::insert([
    'code' => $codeLoc,
    'name' => 'Selftest Filiale K7c',
    'is_active' => 1,
]);
$regId = CashRegisterRepository::insert([
    'location_id' => $locId,
    'code' => $codeReg,
    'name' => 'Selftest Kasse K7c',
    'next_receipt_number' => 1,
    'receipt_number_prefix' => 'P-',
    'is_active' => 1,
]);

CashSessionService::open($regId, 9, 0);
$receiptId = CashReceiptService::startDraft($regId, 9);
CashReceiptService::addLine($receiptId, [
    'title' => 'Brot',
    'quantity' => 1,
    'unit_price_gross' => '10,00',
    'tax_rate' => 7,
]);

$payId = CashReceiptService::addPayment($receiptId, [
    'payment_method' => 'cash',
    'amount' => '4,00',
]);
expect_true($payId > 0, 'Payment-ID');

$receipt = CashReceiptRepository::findById($receiptId);
expect_true(($receipt['status'] ?? '') === 'paying', 'Status paying');
expect_true(CashReceiptService::paymentTotal($receiptId) === 4.0, 'bezahlt 4');

$linesLocked = false;
try {
    CashReceiptService::addLine($receiptId, [
        'title' => 'Zu spät',
        'quantity' => 1,
        'unit_price_gross' => 1,
        'tax_rate' => 19,
    ]);
} catch (RuntimeException $e) {
    $linesLocked = str_contains($e->getMessage(), 'Entwurf');
}
expect_true($linesLocked, 'Positionen bei paying gesperrt');

$overpay = false;
try {
    CashReceiptService::addPayment($receiptId, [
        'payment_method' => 'ec',
        'amount' => 20,
    ]);
} catch (RuntimeException $e) {
    $overpay = str_contains($e->getMessage(), 'übersteigt');
}
expect_true($overpay, 'Überzahlung abgewiesen');

CashReceiptService::addPayment($receiptId, [
    'payment_method' => 'ec',
    'amount' => 6,
]);
expect_true(CashReceiptService::paymentTotal($receiptId) === 10.0, 'voll bezahlt');

CashReceiptService::removePayment($receiptId, $payId);
expect_true(CashReceiptService::paymentTotal($receiptId) === 6.0, 'nach remove 6');
$receipt = CashReceiptRepository::findById($receiptId);
expect_true(($receipt['status'] ?? '') === 'paying', 'weiter paying');

$payments = CashPaymentRepository::listByReceipt($receiptId);
$lastId = (int) ($payments[0]['id'] ?? 0);
CashReceiptService::removePayment($receiptId, $lastId);
$receipt = CashReceiptRepository::findById($receiptId);
expect_true(($receipt['status'] ?? '') === 'draft', 'zurück auf draft');

$pdo = Database::pdo();
$pdo->prepare('DELETE FROM dg_cash_receipts WHERE id = :id')->execute(['id' => $receiptId]);
$pdo->prepare('DELETE FROM dg_cash_sessions WHERE register_id = :rid')->execute(['rid' => $regId]);
$pdo->prepare('DELETE FROM dg_cash_registers WHERE id = :id')->execute(['id' => $regId]);
$pdo->prepare('DELETE FROM dg_cash_locations WHERE id = :id')->execute(['id' => $locId]);
echo "OK  Selftest-Daten entfernt\n";

if ($fail > 0) {
    fwrite(STDERR, "cash-receipt-payment-selftest: {$fail} Fehler\n");
    exit(1);
}

echo "cash-receipt-payment-selftest OK\n";
exit(0);
