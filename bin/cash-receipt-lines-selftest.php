<?php
declare(strict_types=1);

/**
 * Selftest K7b: Positionen nur im Draft + Summen.
 * php bin/cash-receipt-lines-selftest.php
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

expect_true(method_exists('CashReceiptService', 'addLine'), 'addLine');
expect_true(method_exists('CashReceiptService', 'updateLine'), 'updateLine');
expect_true(method_exists('CashReceiptService', 'removeLine'), 'removeLine');

$noReceiptThrows = false;
try {
    CashReceiptService::addLine(0, ['title' => 'X', 'quantity' => 1, 'unit_price_gross' => 1]);
} catch (RuntimeException $e) {
    $noReceiptThrows = str_contains($e->getMessage(), 'fehlt') || str_contains($e->getMessage(), 'gefunden');
}
expect_true($noReceiptThrows, 'receiptId 0 abgewiesen');

if (!Database::isConfigured()) {
    echo "SKIP DB — Guard-Tests ok.\n";
    if ($fail > 0) {
        fwrite(STDERR, "cash-receipt-lines-selftest: {$fail} Fehler\n");
        exit(1);
    }
    echo "cash-receipt-lines-selftest OK (ohne DB)\n";
    exit(0);
}

MigrationRunner::runPending();
if (!CashReceiptRepository::tableExists()) {
    echo "FAIL Bon-Tabellen fehlen\n";
    exit(1);
}

$codeLoc = 'ST-K7B-' . substr(bin2hex(random_bytes(3)), 0, 6);
$codeReg = 'KR-K7B-' . substr(bin2hex(random_bytes(3)), 0, 6);
$locId = CashLocationRepository::insert([
    'code' => $codeLoc,
    'name' => 'Selftest Filiale K7b',
    'is_active' => 1,
]);
$regId = CashRegisterRepository::insert([
    'location_id' => $locId,
    'code' => $codeReg,
    'name' => 'Selftest Kasse K7b',
    'next_receipt_number' => 1,
    'receipt_number_prefix' => 'L-',
    'is_active' => 1,
]);

$receiptId = CashReceiptService::startDraft($regId, 7);
$lineId = CashReceiptService::addLine($receiptId, [
    'title' => 'Kaffee',
    'quantity' => '2',
    'unit_price_gross' => '1,50',
    'tax_rate' => 19,
]);
expect_true($lineId > 0, 'Line-ID');

$receipt = CashReceiptRepository::findById($receiptId);
expect_true(abs((float) ($receipt['total_gross'] ?? 0) - 3.0) < 0.001, 'Brutto 3,00');
expect_true(abs((float) ($receipt['total_net'] ?? 0) - round(3 / 1.19, 2)) < 0.001, 'Netto aus 19%');

CashReceiptService::updateLine($receiptId, $lineId, [
    'title' => 'Kaffee groß',
    'quantity' => 1,
    'unit_price_gross' => 2.5,
    'tax_rate' => 7,
]);
$receipt = CashReceiptRepository::findById($receiptId);
expect_true(($receipt['total_gross'] ?? '') == 2.5 || abs((float) $receipt['total_gross'] - 2.5) < 0.001, 'Brutto nach Update 2,50');
$line = CashReceiptRepository::findLineById($lineId);
expect_true(($line['title'] ?? '') === 'Kaffee groß', 'Titel geändert');

CashReceiptRepository::updateReceipt($receiptId, ['status' => 'finalized']);
$blocked = false;
try {
    CashReceiptService::addLine($receiptId, [
        'title' => 'Zu spät',
        'quantity' => 1,
        'unit_price_gross' => 1,
        'tax_rate' => 19,
    ]);
} catch (RuntimeException $e) {
    $blocked = str_contains($e->getMessage(), 'Entwurf');
}
expect_true($blocked, 'finalized blockiert addLine');

CashReceiptRepository::updateReceipt($receiptId, ['status' => 'draft']);
CashReceiptService::removeLine($receiptId, $lineId);
$receipt = CashReceiptRepository::findById($receiptId);
expect_true(abs((float) ($receipt['total_gross'] ?? 0)) < 0.001, 'Brutto 0 nach remove');
expect_true(CashReceiptRepository::listLines($receiptId) === [], 'keine Lines');

$pdo = Database::pdo();
$pdo->prepare('DELETE FROM dg_cash_receipts WHERE id = :id')->execute(['id' => $receiptId]);
$pdo->prepare('DELETE FROM dg_cash_sessions WHERE register_id = :rid')->execute(['rid' => $regId]);
$pdo->prepare('DELETE FROM dg_cash_registers WHERE id = :id')->execute(['id' => $regId]);
$pdo->prepare('DELETE FROM dg_cash_locations WHERE id = :id')->execute(['id' => $locId]);
echo "OK  Selftest-Daten entfernt\n";

if ($fail > 0) {
    fwrite(STDERR, "cash-receipt-lines-selftest: {$fail} Fehler\n");
    exit(1);
}

echo "cash-receipt-lines-selftest OK\n";
exit(0);
