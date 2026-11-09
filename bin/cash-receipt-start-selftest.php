<?php
declare(strict_types=1);

/**
 * Selftest K7a + K11b: Draft-Bon nur mit offener Sitzung.
 * php bin/cash-receipt-start-selftest.php
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

expect_true(class_exists('CashReceiptService'), 'Klasse CashReceiptService');
expect_true(!method_exists('CashSessionRepository', 'ensureOpenForRegister'), 'kein Auto-Open mehr');

$noRegThrows = false;
try {
    CashReceiptService::startDraft(0, 1);
} catch (RuntimeException $e) {
    $noRegThrows = str_contains($e->getMessage(), 'fehlt');
}
expect_true($noRegThrows, 'registerId 0 abgewiesen');

if (!Database::isConfigured()) {
    echo "SKIP DB — Guard-Tests ok.\n";
    if ($fail > 0) {
        fwrite(STDERR, "cash-receipt-start-selftest: {$fail} Fehler\n");
        exit(1);
    }
    echo "cash-receipt-start-selftest OK (ohne DB)\n";
    exit(0);
}

MigrationRunner::runPending();
if (
    !CashLocationRepository::tableExists()
    || !CashRegisterRepository::tableExists()
    || !CashSessionRepository::tableExists()
    || !CashReceiptRepository::tableExists()
) {
    echo "FAIL Registerkasse-Tabellen fehlen\n";
    exit(1);
}

$codeLoc = 'ST-K7A-' . substr(bin2hex(random_bytes(3)), 0, 6);
$codeReg = 'KR-K7A-' . substr(bin2hex(random_bytes(3)), 0, 6);
$locId = CashLocationRepository::insert([
    'code' => $codeLoc,
    'name' => 'Selftest Filiale K7a',
    'is_active' => 1,
]);
$regId = CashRegisterRepository::insert([
    'location_id' => $locId,
    'code' => $codeReg,
    'name' => 'Selftest Kasse K7a',
    'register_type' => 'store',
    'next_receipt_number' => 1,
    'receipt_number_prefix' => 'K7-',
    'is_active' => 1,
]);

$operatorId = 42;

$noSessionThrows = false;
try {
    CashReceiptService::startDraft($regId, $operatorId);
} catch (RuntimeException $e) {
    $noSessionThrows = str_contains($e->getMessage(), 'offene Kassensitzung');
}
expect_true($noSessionThrows, 'K11b: ohne Sitzung kein Bon');

$sessionId = CashSessionService::open($regId, $operatorId, '25,00');
expect_true($sessionId > 0, 'Sitzung geöffnet');

$receiptId = CashReceiptService::startDraft($regId, $operatorId);
expect_true($receiptId > 0, 'Draft-ID > 0');

$receipt = CashReceiptRepository::findById($receiptId);
expect_true($receipt !== null, 'Bon geladen');
expect_true(($receipt['status'] ?? '') === 'draft', 'status=draft');
expect_true((int) ($receipt['register_id'] ?? 0) === $regId, 'register_id');
expect_true((int) ($receipt['operator_id'] ?? 0) === $operatorId, 'operator_id');
expect_true((int) ($receipt['receipt_number'] ?? 0) === 1, 'erste Bonnummer = 1');
expect_true(($receipt['receipt_number_display'] ?? '') === 'K7-1', 'Anzeige K7-1');
expect_true((int) ($receipt['session_id'] ?? 0) === $sessionId, 'session_id gesetzt');

$session = CashSessionRepository::findOpenByRegister($regId);
expect_true($session !== null, 'offene Sitzung');
expect_true((int) ($session['id'] ?? 0) === (int) $receipt['session_id'], 'Bon an offene Sitzung');

$receiptId2 = CashReceiptService::startDraft($regId, $operatorId);
$receipt2 = CashReceiptRepository::findById($receiptId2);
expect_true((int) ($receipt2['receipt_number'] ?? 0) === 2, 'zweite Bonnummer = 2');
expect_true((int) ($receipt2['session_id'] ?? 0) === $sessionId, 'gleiche Sitzung');

$pdo = Database::pdo();
$pdo->prepare('DELETE FROM dg_cash_receipts WHERE register_id = :rid')->execute(['rid' => $regId]);
$pdo->prepare('DELETE FROM dg_cash_sessions WHERE register_id = :rid')->execute(['rid' => $regId]);
$pdo->prepare('DELETE FROM dg_cash_registers WHERE id = :id')->execute(['id' => $regId]);
$pdo->prepare('DELETE FROM dg_cash_locations WHERE id = :id')->execute(['id' => $locId]);
echo "OK  Selftest-Daten entfernt\n";

if ($fail > 0) {
    fwrite(STDERR, "cash-receipt-start-selftest: {$fail} Fehler\n");
    exit(1);
}

echo "cash-receipt-start-selftest OK\n";
exit(0);
