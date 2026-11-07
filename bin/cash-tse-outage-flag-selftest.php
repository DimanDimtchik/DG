<?php
declare(strict_types=1);

/**
 * K20d — Selftest TSE-Ausfall-Flag (aktuell an Assignment, historisch am Bon).
 * Ergänzt K14a um Mehrkassen-Isolation, Finalize mit Flag, Journal-CSV.
 * php bin/cash-tse-outage-flag-selftest.php
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

$expect_true(class_exists('CashTseOutageService'), 'CashTseOutageService');
$expect_true(method_exists('CashTseOutageService', 'recordOutage'), 'recordOutage');
$expect_true(method_exists('CashTseOutageService', 'clearOutage'), 'clearOutage');
$expect_true(method_exists('MockTseProvider', 'supportsDeferredSignature'), 'supportsDeferredSignature');
$mock = new MockTseProvider();
$expect_true($mock->supportsDeferredSignature() === true, 'Mock erlaubt Nachsignatur-Capability');

if (!Database::isConfigured()) {
    echo "SKIP DB — K20d braucht Instanz-DB.\n";
    if ($fail > 0) {
        fwrite(STDERR, "cash-tse-outage-flag-selftest FAILED ({$fail})\n");
        exit(1);
    }
    echo "cash-tse-outage-flag-selftest OK (ohne DB)\n";
    exit(0);
}

MigrationRunner::runPending();
$need = [
    'CashLocationRepository',
    'CashRegisterRepository',
    'CashTseAssignmentRepository',
    'CashSessionRepository',
    'CashReceiptRepository',
    'CashPaymentRepository',
    'CashAuditLogRepository',
];
foreach ($need as $cls) {
    if (!class_exists($cls) || !$cls::tableExists()) {
        echo "FAIL fehlt: {$cls}\n";
        exit(1);
    }
}

$sfx = substr(bin2hex(random_bytes(3)), 0, 6);
$pdo = Database::pdo();
$locId = 0;
$regA = 0;
$regB = 0;
$receiptIds = [];
$cleanup = static function () use ($pdo, &$receiptIds, &$regA, &$regB, &$locId): void {
    foreach ($receiptIds as $rid) {
        if ($rid <= 0) {
            continue;
        }
        $pdo->prepare('DELETE FROM dg_cash_payments WHERE receipt_id = :id')->execute(['id' => $rid]);
        $pdo->prepare('DELETE FROM dg_cash_receipt_lines WHERE receipt_id = :id')->execute(['id' => $rid]);
        $pdo->prepare('DELETE FROM dg_cash_receipts WHERE id = :id')->execute(['id' => $rid]);
    }
    foreach ([$regA, $regB] as $regId) {
        if ($regId <= 0) {
            continue;
        }
        $pdo->prepare('DELETE FROM dg_cash_audit_log WHERE register_id = :rid')->execute(['rid' => $regId]);
        $pdo->prepare('DELETE FROM dg_cash_sessions WHERE register_id = :rid')->execute(['rid' => $regId]);
        $pdo->prepare('DELETE FROM dg_cash_tse_assignments WHERE register_id = :rid')->execute(['rid' => $regId]);
        $pdo->prepare('DELETE FROM dg_cash_registers WHERE id = :id')->execute(['id' => $regId]);
    }
    if ($locId > 0) {
        $pdo->prepare('DELETE FROM dg_cash_locations WHERE id = :id')->execute(['id' => $locId]);
    }
};

try {
    $locId = CashLocationRepository::insert([
        'code' => 'ST-K20D-' . $sfx,
        'name' => 'Selftest Filiale K20d',
        'is_active' => 1,
    ]);
    $regA = CashRegisterRepository::insert([
        'location_id' => $locId,
        'code' => 'KA-K20D-' . $sfx,
        'name' => 'Kasse A Ausfall',
        'next_receipt_number' => 1,
        'receipt_number_prefix' => 'A-',
        'is_active' => 1,
    ]);
    $regB = CashRegisterRepository::insert([
        'location_id' => $locId,
        'code' => 'KB-K20D-' . $sfx,
        'name' => 'Kasse B ok',
        'next_receipt_number' => 1,
        'receipt_number_prefix' => 'B-',
        'is_active' => 1,
    ]);
    foreach ([$regA, $regB] as $rid) {
        CashTseAssignmentRepository::insert([
            'register_id' => $rid,
            'provider' => 'mock',
            'environment' => 'test',
            'tss_id' => '00000000-0000-4000-8000-0000000000' . ($rid === $regA ? 'a1' : 'b2'),
            'client_id' => '00000000-0000-4000-8000-0000000000' . ($rid === $regA ? 'c1' : 'c2'),
            'is_active' => 1,
        ]);
    }

    CashSessionService::open($regA, 2, '0');
    CashSessionService::open($regB, 2, '0');

    $receiptA = CashReceiptService::startDraft($regA, 2);
    $receiptIds[] = $receiptA;
    CashReceiptService::addLine($receiptA, [
        'title' => 'Mit Ausfall',
        'quantity' => 1,
        'unit_price_gross' => '9,00',
        'tax_rate' => 19,
    ]);
    CashReceiptService::addPayment($receiptA, ['payment_method' => 'cash', 'amount' => '9,00']);

    $receiptB = CashReceiptService::startDraft($regB, 2);
    $receiptIds[] = $receiptB;
    CashReceiptService::addLine($receiptB, [
        'title' => 'Ohne Ausfall',
        'quantity' => 1,
        'unit_price_gross' => '4,00',
        'tax_rate' => 7,
    ]);
    CashReceiptService::addPayment($receiptB, ['payment_method' => 'ec', 'amount' => '4,00']);

    CashTseOutageService::recordOutage([
        'register_id' => $regA,
        'receipt_id' => $receiptA,
        'user_id' => 2,
        'message' => 'K20d Ausfall Selftest',
    ]);

    $expect_true(CashTseOutageService::isOutageActive($regA) === true, 'A Ausfall aktiv');
    $expect_true(CashTseOutageService::isOutageActive($regB) === false, 'B ohne Ausfall (Isolation)');

    $rowA = CashReceiptRepository::findById($receiptA);
    $rowB = CashReceiptRepository::findById($receiptB);
    $expect_true(!empty($rowA['tse_outage']), 'Bon A tse_outage=1');
    $expect_true(empty($rowB['tse_outage']), 'Bon B tse_outage=0');

    // Finalize trotz Ausfall-Flag (ohne TSE-Signatur) — Flag bleibt
    CashReceiptService::finalizeWithoutTse($receiptA);
    CashReceiptService::finalizeWithoutTse($receiptB);
    $rowA = CashReceiptRepository::findById($receiptA);
    $expect_true(($rowA['status'] ?? '') === 'finalized', 'A finalized');
    $expect_true(!empty($rowA['tse_outage']), 'A Flag nach Finalize');

    CashTseOutageService::clearOutage($regA, 2, 'K20d Ende');
    $expect_true(CashTseOutageService::isOutageActive($regA) === false, 'A Ausfall beendet');
    $rowA = CashReceiptRepository::findById($receiptA);
    $expect_true(!empty($rowA['tse_outage']), 'Bon-Flag bleibt historisch nach clear');

    $asgA = CashTseAssignmentRepository::findByRegisterId($regA);
    $asgB = CashTseAssignmentRepository::findByRegisterId($regB);
    $expect_true(empty($asgA['outage_active']), 'Assignment A outage_active=0');
    $expect_true(empty($asgB['outage_active']), 'Assignment B unverändert ohne Ausfall');

    $date = date('Y-m-d');
    $journal = CashReceiptRepository::listJournalRange($regA, $date, $date, 20);
    $found = null;
    foreach ($journal as $j) {
        if ((int) ($j['id'] ?? 0) === $receiptA) {
            $found = $j;
            break;
        }
    }
    $expect_true($found !== null, 'Ausfall-Bon im Journal');
    $expect_true(!empty($found['tse_outage']), 'Journal tse_outage');

    $csv = CashReceiptJournalCsvExport::buildCsv([$found]);
    $expect_true(str_contains($csv, 'TSE-Ausfall'), 'CSV-Kopf TSE-Ausfall');
    $expect_true(preg_match('/;1;/', $csv) === 1 || str_contains($csv, ";1\r") || str_contains($csv, ";1\n"), 'CSV-Flag 1');
} catch (Throwable $e) {
    echo 'FAIL Ausnahme: ' . $e->getMessage() . "\n";
    $fail++;
} finally {
    $cleanup();
    echo "OK  Selftest-Daten entfernt\n";
}

if ($fail > 0) {
    fwrite(STDERR, "cash-tse-outage-flag-selftest FAILED ({$fail})\n");
    exit(1);
}
echo "cash-tse-outage-flag-selftest OK (K20d)\n";
