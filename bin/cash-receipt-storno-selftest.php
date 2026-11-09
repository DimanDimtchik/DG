<?php
declare(strict_types=1);

/**
 * Selftest K13c (+ dünnes K13a ohne TSE): Original unverändert, Storno verknüpft.
 * php bin/cash-receipt-storno-selftest.php
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

expect_true(method_exists('CashReceiptService', 'stornoFromFinalized'), 'stornoFromFinalized');
expect_true(method_exists('CashReceiptService', 'finalizeWithoutTse'), 'finalizeWithoutTse');
expect_true(method_exists('CashReceiptRepository', 'findStornoOf'), 'findStornoOf');
expect_true(CashReceiptService::TYPE_STORNO === 'storno', 'TYPE_STORNO');

$noId = false;
try {
    CashReceiptService::stornoFromFinalized(0, 1);
} catch (RuntimeException $e) {
    $noId = str_contains($e->getMessage(), 'fehlt') || str_contains($e->getMessage(), 'nicht gefunden');
}
expect_true($noId, 'receiptId 0 abgewiesen');

if (!Database::isConfigured()) {
    echo "SKIP DB — Guard-Tests ok.\n";
    if ($fail > 0) {
        fwrite(STDERR, "cash-receipt-storno-selftest: {$fail} Fehler\n");
        exit(1);
    }
    echo "cash-receipt-storno-selftest OK (ohne DB)\n";
    exit(0);
}

MigrationRunner::runPending();
if (
    !CashLocationRepository::tableExists()
    || !CashRegisterRepository::tableExists()
    || !CashSessionRepository::tableExists()
    || !CashReceiptRepository::tableExists()
    || !CashPaymentRepository::tableExists()
) {
    echo "FAIL Registerkasse-Tabellen fehlen\n";
    exit(1);
}

$codeLoc = 'ST-K13C-' . substr(bin2hex(random_bytes(3)), 0, 6);
$codeReg = 'KR-K13C-' . substr(bin2hex(random_bytes(3)), 0, 6);
$locId = CashLocationRepository::insert([
    'code' => $codeLoc,
    'name' => 'Selftest Filiale K13c',
    'is_active' => 1,
]);
$regId = CashRegisterRepository::insert([
    'location_id' => $locId,
    'code' => $codeReg,
    'name' => 'Selftest Kasse K13c',
    'register_type' => 'store',
    'next_receipt_number' => 1,
    'receipt_number_prefix' => 'S-',
    'is_active' => 1,
]);

$operatorId = 55;
CashSessionService::open($regId, $operatorId, '20,00');
$originalId = CashReceiptService::startDraft($regId, $operatorId);
CashReceiptService::addLine($originalId, [
    'title' => 'Kaffee',
    'quantity' => 2,
    'unit_price_gross' => '2,50',
    'tax_rate' => 19,
]);
CashReceiptService::addPayment($originalId, [
    'payment_method' => 'cash',
    'amount' => '5,00',
]);
CashReceiptService::finalizeWithoutTse($originalId);

$before = CashReceiptService::integritySnapshot($originalId);
expect_true(($before['receipt']['status'] ?? '') === 'finalized', 'Original finalized');
expect_true((int) ($before['line_count'] ?? 0) === 1, '1 Position');
expect_true((int) ($before['payment_count'] ?? 0) === 1, '1 Zahlung');
expect_true(abs((float) ($before['receipt']['total_gross'] ?? 0) - 5.0) < 0.001, 'Brutto 5');

$stornoId = CashReceiptService::stornoFromFinalized($originalId, $operatorId, 'Teststorno');
expect_true($stornoId > 0 && $stornoId !== $originalId, 'Storno-ID neu');

$storno = CashReceiptRepository::findById($stornoId);
expect_true(($storno['receipt_type'] ?? '') === 'storno', 'receipt_type=storno');
expect_true(($storno['status'] ?? '') === 'finalized', 'Storno finalized');
expect_true((int) ($storno['storno_of_receipt_id'] ?? 0) === $originalId, 'storno_of_receipt_id');
expect_true(abs((float) ($storno['total_gross'] ?? 0) - (-5.0)) < 0.001, 'Storno-Brutto -5');
expect_true(str_contains((string) ($storno['notes'] ?? ''), 'Teststorno'), 'Notiz');

$stornoLines = CashReceiptRepository::listLines($stornoId);
expect_true(count($stornoLines) === 1, 'Storno 1 Position');
expect_true(abs((float) ($stornoLines[0]['quantity'] ?? 0) - (-2.0)) < 0.001, 'Menge negativ');
expect_true(abs((float) ($stornoLines[0]['gross_amount'] ?? 0) - (-5.0)) < 0.001, 'Positions-Brutto negativ');

$stornoPays = CashPaymentRepository::listByReceipt($stornoId);
expect_true(count($stornoPays) === 1, 'Storno 1 Zahlung');
expect_true(abs((float) ($stornoPays[0]['amount'] ?? 0) - (-5.0)) < 0.001, 'Zahlung negativ');
expect_true(($stornoPays[0]['payment_method'] ?? '') === 'cash', 'Zahlart übernommen');

$linked = CashReceiptRepository::findStornoOf($originalId);
expect_true($linked !== null && (int) ($linked['id'] ?? 0) === $stornoId, 'findStornoOf');

$after = CashReceiptService::integritySnapshot($originalId);
expect_true(($after['receipt']['status'] ?? '') === ($before['receipt']['status'] ?? ''), 'Status unverändert');
expect_true(($after['receipt']['receipt_type'] ?? '') === 'sale', 'Typ weiter sale');
expect_true((int) ($after['receipt']['receipt_number'] ?? 0) === (int) ($before['receipt']['receipt_number'] ?? -1), 'Bonnummer');
expect_true(abs((float) ($after['receipt']['total_gross'] ?? 0) - (float) ($before['receipt']['total_gross'] ?? -1)) < 0.001, 'Brutto Original');
expect_true(abs((float) ($after['receipt']['total_net'] ?? 0) - (float) ($before['receipt']['total_net'] ?? -1)) < 0.001, 'Netto Original');
expect_true((string) ($after['receipt']['vat_summary_json'] ?? '') === (string) ($before['receipt']['vat_summary_json'] ?? ''), 'USt-JSON');
expect_true((string) ($after['receipt']['payment_summary_json'] ?? '') === (string) ($before['receipt']['payment_summary_json'] ?? ''), 'Zahl-JSON');
expect_true((int) ($after['line_count'] ?? 0) === (int) ($before['line_count'] ?? -1), 'Positionsanzahl');
expect_true((int) ($after['payment_count'] ?? 0) === (int) ($before['payment_count'] ?? -1), 'Zahlungsanzahl');
expect_true(
    abs((float) ($after['lines'][0]['gross_amount'] ?? 0) - (float) ($before['lines'][0]['gross_amount'] ?? -1)) < 0.001,
    'Positionsbetrag Original'
);
expect_true(
    abs((float) ($after['payments'][0]['amount'] ?? 0) - (float) ($before['payments'][0]['amount'] ?? -1)) < 0.001,
    'Zahlungsbetrag Original'
);

$dup = false;
try {
    CashReceiptService::stornoFromFinalized($originalId, $operatorId);
} catch (RuntimeException $e) {
    $dup = str_contains($e->getMessage(), 'bereits');
}
expect_true($dup, 'Doppel-Storno abgewiesen');

$pdo = Database::pdo();
$pdo->prepare('DELETE FROM dg_cash_payments WHERE receipt_id IN (:a, :b)')
    ->execute(['a' => $originalId, 'b' => $stornoId]);
$pdo->prepare('DELETE FROM dg_cash_receipt_lines WHERE receipt_id IN (:a, :b)')
    ->execute(['a' => $originalId, 'b' => $stornoId]);
$pdo->prepare('DELETE FROM dg_cash_receipts WHERE id IN (:a, :b)')
    ->execute(['a' => $originalId, 'b' => $stornoId]);
$pdo->prepare('DELETE FROM dg_cash_sessions WHERE register_id = :rid')->execute(['rid' => $regId]);
$pdo->prepare('DELETE FROM dg_cash_registers WHERE id = :id')->execute(['id' => $regId]);
$pdo->prepare('DELETE FROM dg_cash_locations WHERE id = :id')->execute(['id' => $locId]);
echo "OK  Selftest-Daten entfernt\n";

if ($fail > 0) {
    fwrite(STDERR, "cash-receipt-storno-selftest: {$fail} Fehler\n");
    exit(1);
}

echo "cash-receipt-storno-selftest OK\n";
exit(0);
