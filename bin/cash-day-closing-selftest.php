<?php
declare(strict_types=1);

/**
 * Selftest K12a/b/c: Tagesabschluss, Sperre, Filialsumme.
 * php bin/cash-day-closing-selftest.php
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

expect_true(class_exists('CashRegisterDayClosingService'), 'CashRegisterDayClosingService');
expect_true(method_exists('CashRegisterDayClosingService', 'preview'), 'preview');
expect_true(method_exists('CashRegisterDayClosingService', 'close'), 'close');
expect_true(method_exists('CashRegisterDayClosingService', 'isClosed'), 'isClosed');
expect_true(method_exists('CashRegisterDayClosingService', 'locationSummary'), 'locationSummary');
expect_true(method_exists('CashReceiptRepository', 'dayTotalsByRegisterDate'), 'dayTotalsByRegisterDate');

$noRegThrows = false;
try {
    CashRegisterDayClosingService::preview(0, date('Y-m-d'));
} catch (RuntimeException $e) {
    $noRegThrows = str_contains($e->getMessage(), 'fehlt');
}
expect_true($noRegThrows, 'registerId 0 abgewiesen');

$noLocThrows = false;
try {
    CashRegisterDayClosingService::locationSummary(0, date('Y-m-d'));
} catch (RuntimeException $e) {
    $noLocThrows = str_contains($e->getMessage(), 'fehlt');
}
expect_true($noLocThrows, 'locationId 0 abgewiesen');

if (!Database::isConfigured()) {
    echo "SKIP DB — Guard-Tests ok.\n";
    if ($fail > 0) {
        fwrite(STDERR, "cash-day-closing-selftest: {$fail} Fehler\n");
        exit(1);
    }
    echo "cash-day-closing-selftest OK (ohne DB)\n";
    exit(0);
}

MigrationRunner::runPending();
if (
    !CashLocationRepository::tableExists()
    || !CashRegisterRepository::tableExists()
    || !CashSessionRepository::tableExists()
    || !CashReceiptRepository::tableExists()
    || !CashPaymentRepository::tableExists()
    || !CashRegisterDayClosingRepository::tableExists()
) {
    echo "FAIL Registerkasse-Tabellen fehlen\n";
    exit(1);
}

$codeLoc = 'ST-K12A-' . substr(bin2hex(random_bytes(3)), 0, 6);
$codeReg = 'KR-K12A-' . substr(bin2hex(random_bytes(3)), 0, 6);
$day = date('Y-m-d');
$locId = CashLocationRepository::insert([
    'code' => $codeLoc,
    'name' => 'Selftest Filiale K12a',
    'is_active' => 1,
]);
$regId = CashRegisterRepository::insert([
    'location_id' => $locId,
    'code' => $codeReg,
    'name' => 'Selftest Kasse K12a',
    'register_type' => 'store',
    'next_receipt_number' => 1,
    'receipt_number_prefix' => 'D-',
    'is_active' => 1,
]);

$operatorId = 33;
$sessionId = CashSessionService::open($regId, $operatorId, '100,00', $day);
$receiptId = CashReceiptService::startDraft($regId, $operatorId);
CashReceiptService::addLine($receiptId, [
    'title' => 'Artikel',
    'quantity' => 1,
    'unit_price_gross' => '20,00',
    'tax_rate' => 19,
]);
CashReceiptService::addPayment($receiptId, ['payment_method' => 'cash', 'amount' => '10,00']);
CashReceiptService::addPayment($receiptId, ['payment_method' => 'ec', 'amount' => '5,00']);
CashReceiptService::addPayment($receiptId, ['payment_method' => 'card', 'amount' => '3,00']);
CashReceiptService::addPayment($receiptId, ['payment_method' => 'voucher', 'amount' => '2,00']);

$pdo = Database::pdo();
$pdo->prepare("UPDATE dg_cash_receipts SET status = 'finalized', finished_at = NOW() WHERE id = :id")
    ->execute(['id' => $receiptId]);

$blockedOpen = false;
try {
    CashRegisterDayClosingService::close($regId, $day, $operatorId);
} catch (RuntimeException $e) {
    $blockedOpen = str_contains($e->getMessage(), 'Sitzung');
}
expect_true($blockedOpen, 'offene Sitzung blockiert Abschluss');

CashSessionService::close($regId, $operatorId);

$preview = CashRegisterDayClosingService::preview($regId, $day);
expect_true($preview['receipt_count'] === 1, '1 Bon');
expect_true(abs($preview['total_gross'] - 20.0) < 0.001, 'Brutto 20');
expect_true(abs($preview['total_cash'] - 10.0) < 0.001, 'Bar 10');
expect_true(abs($preview['total_ec'] - 5.0) < 0.001, 'EC 5');
expect_true(abs($preview['total_card'] - 3.0) < 0.001, 'Karte 3');
expect_true(abs($preview['total_voucher'] - 2.0) < 0.001, 'Gutschein 2');
expect_true(abs($preview['opening_balance'] - 100.0) < 0.001, 'Anfang 100');
expect_true(abs($preview['expected_balance'] - 110.0) < 0.001, 'Soll 110');

$closingId = CashRegisterDayClosingService::close($regId, $day, $operatorId, '109,50', 'Zählung');
expect_true($closingId > 0, 'closing id');

$row = CashRegisterDayClosingRepository::findById($closingId);
expect_true($row !== null, 'Abschluss geladen');
expect_true(abs((float) ($row['difference'] ?? 0) - (-0.5)) < 0.001, 'Differenz -0,50');
expect_true(($row['notes'] ?? '') === 'Zählung', 'Notiz');

$dup = false;
try {
    CashRegisterDayClosingService::close($regId, $day, $operatorId);
} catch (RuntimeException $e) {
    $dup = str_contains($e->getMessage(), 'bereits');
}
expect_true($dup, 'Doppelabschluss abgewiesen');
expect_true(CashRegisterDayClosingService::isClosed($regId, $day), 'isClosed true');

$noSession = false;
try {
    CashSessionService::open($regId, $operatorId, 0, $day);
} catch (RuntimeException $e) {
    $noSession = str_contains($e->getMessage(), 'abgeschlossen');
}
expect_true($noSession, 'K12b: keine neue Sitzung am abgeschlossenen Tag');

// Offene Sitzung auf abgeschlossenem Tag (Randfall) → Bon sperren
$forcedSessionId = CashSessionRepository::insert([
    'register_id' => $regId,
    'business_date' => $day,
    'status' => 'open',
    'opening_balance' => 0,
    'opened_by' => $operatorId,
    'opened_at' => date('Y-m-d H:i:s'),
]);
$noBon = false;
try {
    CashReceiptService::startDraft($regId, $operatorId);
} catch (RuntimeException $e) {
    $noBon = str_contains($e->getMessage(), 'abgeschlossen');
}
expect_true($noBon, 'K12b: kein neuer Bon am abgeschlossenen Tag');
$pdo->prepare("UPDATE dg_cash_sessions SET status = 'closed', closed_at = NOW() WHERE id = :id")
    ->execute(['id' => $forcedSessionId]);

// K12c: zweite Kasse ohne Abschluss → Filiale unvollständig; Summe = erste Kasse
$codeReg2 = 'KR-K12C-' . substr(bin2hex(random_bytes(3)), 0, 6);
$regId2 = CashRegisterRepository::insert([
    'location_id' => $locId,
    'code' => $codeReg2,
    'name' => 'Selftest Kasse K12c B',
    'register_type' => 'store',
    'next_receipt_number' => 1,
    'is_active' => 1,
]);
$filial = CashRegisterDayClosingService::locationSummary($locId, $day);
expect_true($filial['register_count'] === 2, '2 aktive Kassen');
expect_true($filial['closed_count'] === 1, '1 abgeschlossen');
expect_true($filial['complete'] === false, 'Filiale unvollständig');
expect_true(abs($filial['total_cash'] - 10.0) < 0.001, 'Filial-Bar = Summe abgeschlossener');
expect_true(abs($filial['total_ec'] - 5.0) < 0.001, 'Filial-EC');

$pdo->prepare('DELETE FROM dg_cash_register_day_closings WHERE id = :id')->execute(['id' => $closingId]);
$pdo->prepare('DELETE FROM dg_cash_payments WHERE receipt_id = :id')->execute(['id' => $receiptId]);
$pdo->prepare('DELETE FROM dg_cash_receipt_lines WHERE receipt_id = :id')->execute(['id' => $receiptId]);
$pdo->prepare('DELETE FROM dg_cash_receipts WHERE register_id = :rid')->execute(['rid' => $regId]);
$pdo->prepare('DELETE FROM dg_cash_sessions WHERE register_id = :rid')->execute(['rid' => $regId]);
$pdo->prepare('DELETE FROM dg_cash_registers WHERE id = :id')->execute(['id' => $regId2]);
$pdo->prepare('DELETE FROM dg_cash_registers WHERE id = :id')->execute(['id' => $regId]);
$pdo->prepare('DELETE FROM dg_cash_locations WHERE id = :id')->execute(['id' => $locId]);
echo "OK  Selftest-Daten entfernt\n";

if ($fail > 0) {
    fwrite(STDERR, "cash-day-closing-selftest: {$fail} Fehler\n");
    exit(1);
}

echo "cash-day-closing-selftest OK\n";
exit(0);
