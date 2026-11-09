<?php
declare(strict_types=1);

/**
 * K20a — Selftest: eigene Bon-Nummernkreise je Kasse (Mehrkassen, inkl. Draft-Pfad).
 * php bin/cash-multi-register-numbers-selftest.php
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

$expect_true(method_exists('CashRegisterRepository', 'allocateNextReceiptNumber'), 'allocateNextReceiptNumber');
$expect_true(method_exists('CashReceiptService', 'startDraft'), 'startDraft');

if (!Database::isConfigured()) {
    echo "SKIP DB — K20a braucht Instanz-DB für Mehrkassen-Vergabe.\n";
    if ($fail > 0) {
        fwrite(STDERR, "cash-multi-register-numbers-selftest FAILED ({$fail})\n");
        exit(1);
    }
    echo "cash-multi-register-numbers-selftest OK (ohne DB)\n";
    exit(0);
}

MigrationRunner::runPending();
foreach (
    [
        'CashLocationRepository',
        'CashRegisterRepository',
        'CashSessionRepository',
        'CashReceiptRepository',
    ] as $cls
) {
    $expect_true(class_exists($cls) && $cls::tableExists(), $cls . ' bereit');
}
if ($fail > 0) {
    fwrite(STDERR, "cash-multi-register-numbers-selftest FAILED ({$fail})\n");
    exit(1);
}

$sfx = substr(bin2hex(random_bytes(3)), 0, 6);
$pdo = Database::pdo();
$operatorId = 77;
$loc1 = 0;
$loc2 = 0;
$regA = 0;
$regB = 0;
$regC = 0;
$cleanup = static function () use ($pdo, &$regA, &$regB, &$regC, &$loc1, &$loc2): void {
    foreach ([$regA, $regB, $regC] as $rid) {
        if ($rid <= 0) {
            continue;
        }
        $pdo->prepare('DELETE FROM dg_cash_receipts WHERE register_id = :rid')->execute(['rid' => $rid]);
        $pdo->prepare('DELETE FROM dg_cash_sessions WHERE register_id = :rid')->execute(['rid' => $rid]);
        $pdo->prepare('DELETE FROM dg_cash_registers WHERE id = :id')->execute(['id' => $rid]);
    }
    foreach ([$loc1, $loc2] as $lid) {
        if ($lid > 0) {
            $pdo->prepare('DELETE FROM dg_cash_locations WHERE id = :id')->execute(['id' => $lid]);
        }
    }
};

try {
    $loc1 = CashLocationRepository::insert([
        'code' => 'ST-K20A1-' . $sfx,
        'name' => 'Selftest Filiale K20a-1',
        'is_active' => 1,
    ]);
    $loc2 = CashLocationRepository::insert([
        'code' => 'ST-K20A2-' . $sfx,
        'name' => 'Selftest Filiale K20a-2',
        'is_active' => 1,
    ]);

    // Beide starten bei Nummer 1 — Kreise müssen trotzdem getrennt bleiben.
    $regA = CashRegisterRepository::insert([
        'location_id' => $loc1,
        'code' => 'KA-K20A-' . $sfx,
        'name' => 'Kasse A',
        'register_type' => 'store',
        'next_receipt_number' => 1,
        'receipt_number_prefix' => 'A-',
        'is_active' => 1,
    ]);
    $regB = CashRegisterRepository::insert([
        'location_id' => $loc1,
        'code' => 'KB-K20A-' . $sfx,
        'name' => 'Kasse B',
        'register_type' => 'mobile',
        'next_receipt_number' => 1,
        'receipt_number_prefix' => 'B-',
        'is_active' => 1,
    ]);
    $regC = CashRegisterRepository::insert([
        'location_id' => $loc2,
        'code' => 'KC-K20A-' . $sfx,
        'name' => 'Kasse C andere Filiale',
        'register_type' => 'store',
        'next_receipt_number' => 1,
        'receipt_number_prefix' => 'A-', // gleicher Präfix wie A, andere Kasse
        'is_active' => 1,
    ]);

    CashSessionService::open($regA, $operatorId, '10,00');
    CashSessionService::open($regB, $operatorId, '20,00');
    CashSessionService::open($regC, $operatorId, '30,00');

    // Verschachtelt: A, B, A, C, B
    $idA1 = CashReceiptService::startDraft($regA, $operatorId);
    $idB1 = CashReceiptService::startDraft($regB, $operatorId);
    $idA2 = CashReceiptService::startDraft($regA, $operatorId);
    $idC1 = CashReceiptService::startDraft($regC, $operatorId);
    $idB2 = CashReceiptService::startDraft($regB, $operatorId);

    $rA1 = CashReceiptRepository::findById($idA1);
    $rB1 = CashReceiptRepository::findById($idB1);
    $rA2 = CashReceiptRepository::findById($idA2);
    $rC1 = CashReceiptRepository::findById($idC1);
    $rB2 = CashReceiptRepository::findById($idB2);

    $expect_true((int) ($rA1['receipt_number'] ?? 0) === 1, 'A erster Bon = 1');
    $expect_true((int) ($rB1['receipt_number'] ?? 0) === 1, 'B erster Bon = 1 (eigener Kreis)');
    $expect_true((int) ($rA2['receipt_number'] ?? 0) === 2, 'A zweiter Bon = 2');
    $expect_true((int) ($rC1['receipt_number'] ?? 0) === 1, 'C erster Bon = 1 (andere Filiale)');
    $expect_true((int) ($rB2['receipt_number'] ?? 0) === 2, 'B zweiter Bon = 2');

    $expect_true(($rA1['receipt_number_display'] ?? '') === 'A-1', 'Anzeige A-1');
    $expect_true(($rB1['receipt_number_display'] ?? '') === 'B-1', 'Anzeige B-1');
    $expect_true(($rC1['receipt_number_display'] ?? '') === 'A-1', 'C darf Display A-1 (andere Kassen-ID)');

    $expect_true(
        (int) ($rA1['register_id'] ?? 0) !== (int) ($rB1['register_id'] ?? 0),
        'A und B unterschiedliche register_id'
    );
    $expect_true(
        (int) ($rA1['receipt_number'] ?? 0) === (int) ($rB1['receipt_number'] ?? 0),
        'gleiche Nummernzahl auf zwei Kassen erlaubt'
    );

    // Zähler unabhängig
    $expect_true(
        (int) (CashRegisterRepository::findById($regA)['next_receipt_number'] ?? 0) === 3,
        'A-Zähler = 3'
    );
    $expect_true(
        (int) (CashRegisterRepository::findById($regB)['next_receipt_number'] ?? 0) === 3,
        'B-Zähler = 3'
    );
    $expect_true(
        (int) (CashRegisterRepository::findById($regC)['next_receipt_number'] ?? 0) === 2,
        'C-Zähler = 2'
    );

    // UNIQUE (register_id, receipt_number): Doppel auf derselben Kasse muss scheitern
    $dupThrows = false;
    try {
        CashReceiptRepository::insertReceipt([
            'location_id' => $loc1,
            'register_id' => $regA,
            'session_id' => (int) ($rA1['session_id'] ?? 0),
            'receipt_number' => 1,
            'receipt_number_display' => 'A-1-DUP',
            'status' => 'draft',
            'receipt_type' => 'sale',
            'operator_id' => $operatorId,
            'started_at' => date('Y-m-d H:i:s'),
        ]);
    } catch (Throwable $e) {
        $dupThrows = true;
    }
    $expect_true($dupThrows, 'Doppel-Bonnummer dieselbe Kasse abgewiesen');

    // Gleiche Nummer auf anderer Kasse bleibt erlaubt (bereits durch B-1 bewiesen)
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM dg_cash_receipts
         WHERE receipt_number = 1 AND register_id IN (:a, :b, :c)'
    );
    $stmt->execute(['a' => $regA, 'b' => $regB, 'c' => $regC]);
    $expect_true((int) $stmt->fetchColumn() === 3, 'drei Bons mit Nummer 1 auf drei Kassen');
} catch (Throwable $e) {
    echo 'FAIL Ausnahme: ' . $e->getMessage() . "\n";
    $fail++;
} finally {
    $cleanup();
    echo "OK  Selftest-Daten entfernt\n";
}

if ($fail > 0) {
    fwrite(STDERR, "cash-multi-register-numbers-selftest FAILED ({$fail})\n");
    exit(1);
}
echo "cash-multi-register-numbers-selftest OK (K20a)\n";
