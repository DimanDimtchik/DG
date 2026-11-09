<?php
declare(strict_types=1);

/**
 * Selftest K11c: Sitzung schließen (ohne Zählung; Guard offene Bons).
 * php bin/cash-session-close-selftest.php
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

expect_true(method_exists('CashSessionService', 'close'), 'CashSessionService::close');
expect_true(method_exists('CashReceiptRepository', 'countUnfinishedBySession'), 'countUnfinishedBySession');

$noRegThrows = false;
try {
    CashSessionService::close(0, 1);
} catch (RuntimeException $e) {
    $noRegThrows = str_contains($e->getMessage(), 'fehlt');
}
expect_true($noRegThrows, 'registerId 0 abgewiesen');

$noOpThrows = false;
try {
    CashSessionService::close(1, 0);
} catch (RuntimeException $e) {
    $noOpThrows = str_contains($e->getMessage(), 'Bediener');
}
expect_true($noOpThrows, 'closedBy 0 abgewiesen');

if (!Database::isConfigured()) {
    echo "SKIP DB — Guard-Tests ok.\n";
    if ($fail > 0) {
        fwrite(STDERR, "cash-session-close-selftest: {$fail} Fehler\n");
        exit(1);
    }
    echo "cash-session-close-selftest OK (ohne DB)\n";
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

$codeLoc = 'ST-K11C-' . substr(bin2hex(random_bytes(3)), 0, 6);
$codeReg = 'KR-K11C-' . substr(bin2hex(random_bytes(3)), 0, 6);
$locId = CashLocationRepository::insert([
    'code' => $codeLoc,
    'name' => 'Selftest Filiale K11c',
    'is_active' => 1,
]);
$regId = CashRegisterRepository::insert([
    'location_id' => $locId,
    'code' => $codeReg,
    'name' => 'Selftest Kasse K11c',
    'register_type' => 'store',
    'next_receipt_number' => 1,
    'receipt_number_prefix' => 'C-',
    'is_active' => 1,
]);

$operatorId = 21;

$noOpenThrows = false;
try {
    CashSessionService::close($regId, $operatorId);
} catch (RuntimeException $e) {
    $noOpenThrows = str_contains($e->getMessage(), 'Keine offene Sitzung');
}
expect_true($noOpenThrows, 'ohne Sitzung abgewiesen');

$sessionId = CashSessionService::open($regId, $operatorId, '10,00');
$receiptId = CashReceiptService::startDraft($regId, $operatorId);
expect_true(CashReceiptRepository::countUnfinishedBySession($sessionId) === 1, '1 offener Bon');

$blocked = false;
try {
    CashSessionService::close($regId, $operatorId);
} catch (RuntimeException $e) {
    $blocked = str_contains($e->getMessage(), 'offene Bons');
}
expect_true($blocked, 'Schließen mit Entwurf abgewiesen');

$pdo = Database::pdo();
$pdo->prepare("UPDATE dg_cash_receipts SET status = 'finalized', finished_at = NOW() WHERE id = :id")
    ->execute(['id' => $receiptId]);
expect_true(CashReceiptRepository::countUnfinishedBySession($sessionId) === 0, 'kein offener Bon');

$closedId = CashSessionService::close($regId, $operatorId, 'Ende Schicht');
expect_true($closedId === $sessionId, 'gleiche Session-ID');

$session = CashSessionRepository::findById($sessionId);
expect_true(($session['status'] ?? '') === 'closed', 'status=closed');
expect_true((int) ($session['closed_by'] ?? 0) === $operatorId, 'closed_by');
expect_true(trim((string) ($session['closed_at'] ?? '')) !== '', 'closed_at gesetzt');
expect_true(str_contains((string) ($session['notes'] ?? ''), 'Ende Schicht'), 'Notiz übernommen');
expect_true(CashSessionRepository::findOpenByRegister($regId) === null, 'keine offene Sitzung');
expect_true($session['closing_counted_balance'] === null, 'Zählung noch leer');

$sessionId2 = CashSessionService::open($regId, $operatorId, 0);
expect_true($sessionId2 > $sessionId, 'neu öffnen nach Schließen');

$pdo->prepare('DELETE FROM dg_cash_receipts WHERE register_id = :rid')->execute(['rid' => $regId]);
$pdo->prepare('DELETE FROM dg_cash_sessions WHERE register_id = :rid')->execute(['rid' => $regId]);
$pdo->prepare('DELETE FROM dg_cash_registers WHERE id = :id')->execute(['id' => $regId]);
$pdo->prepare('DELETE FROM dg_cash_locations WHERE id = :id')->execute(['id' => $locId]);
echo "OK  Selftest-Daten entfernt\n";

if ($fail > 0) {
    fwrite(STDERR, "cash-session-close-selftest: {$fail} Fehler\n");
    exit(1);
}

echo "cash-session-close-selftest OK\n";
exit(0);
