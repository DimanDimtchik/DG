<?php
declare(strict_types=1);

/**
 * Selftest K11a: Kasse öffnen (Bediener + Anfangsbestand).
 * php bin/cash-session-open-selftest.php
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

expect_true(class_exists('CashSessionService'), 'Klasse CashSessionService');
expect_true(method_exists('CashSessionService', 'open'), 'CashSessionService::open');
expect_true(method_exists('CashSessionRepository', 'mapOpenByRegisterIds'), 'mapOpenByRegisterIds');

$noRegThrows = false;
try {
    CashSessionService::open(0, 1, 10);
} catch (RuntimeException $e) {
    $noRegThrows = str_contains($e->getMessage(), 'fehlt');
}
expect_true($noRegThrows, 'registerId 0 abgewiesen');

$noOpThrows = false;
try {
    CashSessionService::open(1, 0, 10);
} catch (RuntimeException $e) {
    $noOpThrows = str_contains($e->getMessage(), 'Bediener');
}
expect_true($noOpThrows, 'openedBy 0 abgewiesen');

if (!Database::isConfigured()) {
    echo "SKIP DB — Guard-Tests ok.\n";
    if ($fail > 0) {
        fwrite(STDERR, "cash-session-open-selftest: {$fail} Fehler\n");
        exit(1);
    }
    echo "cash-session-open-selftest OK (ohne DB)\n";
    exit(0);
}

MigrationRunner::runPending();
if (
    !CashLocationRepository::tableExists()
    || !CashRegisterRepository::tableExists()
    || !CashSessionRepository::tableExists()
) {
    echo "FAIL Registerkasse-Tabellen fehlen\n";
    exit(1);
}

$codeLoc = 'ST-K11A-' . substr(bin2hex(random_bytes(3)), 0, 6);
$codeReg = 'KR-K11A-' . substr(bin2hex(random_bytes(3)), 0, 6);
$locId = CashLocationRepository::insert([
    'code' => $codeLoc,
    'name' => 'Selftest Filiale K11a',
    'is_active' => 1,
]);
$regId = CashRegisterRepository::insert([
    'location_id' => $locId,
    'code' => $codeReg,
    'name' => 'Selftest Kasse K11a',
    'register_type' => 'store',
    'next_receipt_number' => 1,
    'receipt_number_prefix' => 'K11-',
    'is_active' => 1,
]);

$operatorId = 17;
$sessionId = CashSessionService::open($regId, $operatorId, '50,50', date('Y-m-d'), 'Wechselgeld');
expect_true($sessionId > 0, 'session id > 0');

$session = CashSessionRepository::findById($sessionId);
expect_true($session !== null, 'Sitzung geladen');
expect_true(($session['status'] ?? '') === 'open', 'status=open');
expect_true((int) ($session['register_id'] ?? 0) === $regId, 'register_id');
expect_true((int) ($session['opened_by'] ?? 0) === $operatorId, 'opened_by');
expect_true(abs((float) ($session['opening_balance'] ?? 0) - 50.5) < 0.001, 'opening_balance 50,50');
expect_true(($session['business_date'] ?? '') === date('Y-m-d'), 'business_date');
expect_true(($session['notes'] ?? '') === 'Wechselgeld', 'notes');

$open = CashSessionRepository::findOpenByRegister($regId);
expect_true($open !== null && (int) ($open['id'] ?? 0) === $sessionId, 'findOpenByRegister');

$map = CashSessionRepository::mapOpenByRegisterIds([$regId, 0, $regId + 999]);
expect_true(isset($map[$regId]) && (int) $map[$regId]['id'] === $sessionId, 'mapOpenByRegisterIds');

$dupThrows = false;
try {
    CashSessionService::open($regId, $operatorId, 10);
} catch (RuntimeException $e) {
    $dupThrows = str_contains($e->getMessage(), 'bereits');
}
expect_true($dupThrows, 'zweites Öffnen abgewiesen');

$negThrows = false;
$pdo = Database::pdo();
$pdo->prepare("UPDATE dg_cash_sessions SET status = 'closed', closed_at = NOW() WHERE id = :id")
    ->execute(['id' => $sessionId]);
try {
    CashSessionService::open($regId, $operatorId, '-1');
} catch (RuntimeException $e) {
    $negThrows = str_contains($e->getMessage(), 'negativ');
}
expect_true($negThrows, 'negativer Anfangsbestand abgewiesen');

$sessionId2 = CashSessionService::open($regId, $operatorId, 0);
expect_true($sessionId2 > $sessionId, 'neue Sitzung nach Schließen');

$pdo->prepare('DELETE FROM dg_cash_sessions WHERE register_id = :rid')->execute(['rid' => $regId]);
$pdo->prepare('DELETE FROM dg_cash_registers WHERE id = :id')->execute(['id' => $regId]);
$pdo->prepare('DELETE FROM dg_cash_locations WHERE id = :id')->execute(['id' => $locId]);
echo "OK  Selftest-Daten entfernt\n";

if ($fail > 0) {
    fwrite(STDERR, "cash-session-open-selftest: {$fail} Fehler\n");
    exit(1);
}

echo "cash-session-open-selftest OK\n";
exit(0);
