<?php
declare(strict_types=1);

/**
 * Selftest K14a: TSE-Ausfall-Flag + Audit-Log.
 * php bin/cash-tse-outage-selftest.php
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

expect_true(class_exists('CashTseOutageService'), 'CashTseOutageService');
expect_true(method_exists('CashTseOutageService', 'recordOutage'), 'recordOutage');
expect_true(method_exists('CashTseOutageService', 'isOutageActive'), 'isOutageActive');
expect_true(method_exists('CashTseOutageService', 'clearOutage'), 'clearOutage');
expect_true(method_exists('CashTseOutageService', 'listActiveOutages'), 'listActiveOutages');
expect_true(CashTseOutageService::EVENT_OUTAGE === 'tse.outage', 'event tse.outage');
expect_true(method_exists('CashAuditLogRepository', 'listRecent'), 'listRecent');
expect_true(is_readable(dirname(__DIR__) . '/views/partials/cash-tse-outage-banner.php'), 'Banner-Partial');

$noReg = false;
try {
    CashTseOutageService::recordOutage(['register_id' => 0]);
} catch (RuntimeException $e) {
    $noReg = str_contains($e->getMessage(), 'fehlt');
}
expect_true($noReg, 'registerId 0 abgewiesen');

if (!Database::isConfigured()) {
    echo "SKIP DB — Guard-Tests ok.\n";
    if ($fail > 0) {
        fwrite(STDERR, "cash-tse-outage-selftest: {$fail} Fehler\n");
        exit(1);
    }
    echo "cash-tse-outage-selftest OK (ohne DB)\n";
    exit(0);
}

MigrationRunner::runPending();
if (
    !CashLocationRepository::tableExists()
    || !CashRegisterRepository::tableExists()
    || !CashTseAssignmentRepository::tableExists()
    || !CashAuditLogRepository::tableExists()
    || !CashReceiptRepository::tableExists()
    || !CashSessionRepository::tableExists()
) {
    echo "FAIL Registerkasse-Tabellen fehlen\n";
    exit(1);
}

$pdo = Database::pdo();
$colA = $pdo->query("SHOW COLUMNS FROM dg_cash_tse_assignments LIKE 'outage_active'");
$colR = $pdo->query("SHOW COLUMNS FROM dg_cash_receipts LIKE 'tse_outage'");
expect_true($colA !== false && $colA->fetch() !== false, 'Spalte outage_active');
expect_true($colR !== false && $colR->fetch() !== false, 'Spalte tse_outage');

$codeLoc = 'ST-K14A-' . substr(bin2hex(random_bytes(3)), 0, 6);
$codeReg = 'KR-K14A-' . substr(bin2hex(random_bytes(3)), 0, 6);
$locId = CashLocationRepository::insert([
    'code' => $codeLoc,
    'name' => 'Selftest Filiale K14a',
    'is_active' => 1,
]);
$regId = CashRegisterRepository::insert([
    'location_id' => $locId,
    'code' => $codeReg,
    'name' => 'Selftest Kasse K14a',
    'register_type' => 'store',
    'next_receipt_number' => 1,
    'is_active' => 1,
]);
CashTseAssignmentRepository::insert([
    'register_id' => $regId,
    'provider' => 'mock',
    'environment' => 'test',
    'tss_id' => '00000000-0000-4000-8000-0000000000aa',
    'client_id' => '00000000-0000-4000-8000-0000000000bb',
    'is_active' => 1,
]);

expect_true(CashTseOutageService::isOutageActive($regId) === false, 'kein Ausfall anfangs');

CashSessionService::open($regId, 1, 0);
$receiptId = CashReceiptService::startDraft($regId, 1);

$auditId = CashTseOutageService::recordOutage([
    'register_id' => $regId,
    'receipt_id' => $receiptId,
    'user_id' => 7,
    'message' => 'TSS nicht erreichbar (Selftest)',
    'details' => ['phase' => 'startTransaction'],
]);
expect_true($auditId > 0, 'Audit-ID');
expect_true(CashTseOutageService::isOutageActive($regId) === true, 'Ausfall aktiv');
$activeList = CashTseOutageService::listActiveOutages();
$foundInList = false;
foreach ($activeList as $row) {
    if ((int) ($row['register_id'] ?? 0) === $regId) {
        $foundInList = true;
        break;
    }
}
expect_true($foundInList, 'listActiveOutages enthält Kasse');

$assignment = CashTseAssignmentRepository::findByRegisterId($regId);
expect_true(!empty($assignment['outage_active']), 'assignment.outage_active');
expect_true(trim((string) ($assignment['outage_since'] ?? '')) !== '', 'outage_since');
expect_true(($assignment['last_state'] ?? '') === 'OUTAGE', 'last_state OUTAGE');
expect_true(str_contains((string) ($assignment['last_error'] ?? ''), 'Selftest'), 'last_error');

$receipt = CashReceiptRepository::findById($receiptId);
expect_true(!empty($receipt['tse_outage']), 'receipt.tse_outage');

$audit = CashAuditLogRepository::findById($auditId);
expect_true($audit !== null, 'Audit geladen');
expect_true(($audit['event_type'] ?? '') === 'tse.outage', 'event_type');
expect_true(($audit['severity'] ?? '') === 'error', 'severity error');
expect_true((int) ($audit['register_id'] ?? 0) === $regId, 'audit register');
expect_true((int) ($audit['receipt_id'] ?? 0) === $receiptId, 'audit receipt');
expect_true((int) ($audit['user_id'] ?? 0) === 7, 'audit user');

$listed = CashAuditLogRepository::listRecent(5, CashTseOutageService::EVENT_OUTAGE, $regId);
expect_true($listed !== [] && (int) ($listed[0]['id'] ?? 0) === $auditId, 'listRecent outage');

$clearId = CashTseOutageService::clearOutage($regId, 7);
expect_true($clearId > 0, 'clear audit id');
expect_true(CashTseOutageService::isOutageActive($regId) === false, 'Ausfall beendet');
$assignment = CashTseAssignmentRepository::findByRegisterId($regId);
expect_true(empty($assignment['outage_active']), 'outage_active 0');
$receipt = CashReceiptRepository::findById($receiptId);
expect_true(!empty($receipt['tse_outage']), 'Bon-Flag bleibt historisch');

$clearAudit = CashAuditLogRepository::findById($clearId);
expect_true(($clearAudit['event_type'] ?? '') === 'tse.outage.cleared', 'event cleared');

$pdo->prepare('DELETE FROM dg_cash_audit_log WHERE register_id = :rid')->execute(['rid' => $regId]);
$pdo->prepare('DELETE FROM dg_cash_receipts WHERE register_id = :rid')->execute(['rid' => $regId]);
$pdo->prepare('DELETE FROM dg_cash_sessions WHERE register_id = :rid')->execute(['rid' => $regId]);
$pdo->prepare('DELETE FROM dg_cash_tse_assignments WHERE register_id = :rid')->execute(['rid' => $regId]);
$pdo->prepare('DELETE FROM dg_cash_registers WHERE id = :id')->execute(['id' => $regId]);
$pdo->prepare('DELETE FROM dg_cash_locations WHERE id = :id')->execute(['id' => $locId]);
echo "OK  Selftest-Daten entfernt\n";

if ($fail > 0) {
    fwrite(STDERR, "cash-tse-outage-selftest: {$fail} Fehler\n");
    exit(1);
}

echo "cash-tse-outage-selftest OK\n";
exit(0);
