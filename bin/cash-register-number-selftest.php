<?php
declare(strict_types=1);

/**
 * Selftest K5d: Bon-Nummernkreis (Format + atomare Vergabe).
 * php bin/cash-register-number-selftest.php
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

expect_true(class_exists('CashRegisterRepository'), 'Klasse CashRegisterRepository');
expect_true(CashRegisterRepository::formatReceiptNumber('', 7) === '7', 'Format ohne Präfix');
expect_true(CashRegisterRepository::formatReceiptNumber('A-', 7) === 'A-7', 'Format mit Präfix');
expect_true(CashRegisterRepository::formatReceiptNumber('  B  ', 12) === 'B12', 'Format trimmt Präfix');

if (!Database::isConfigured()) {
    echo "SKIP DB — Format-Tests ok, Vergabe braucht Instanz-DB.\n";
    if ($fail > 0) {
        fwrite(STDERR, "cash-register-number-selftest: {$fail} Fehler\n");
        exit(1);
    }
    echo "cash-register-number-selftest OK (ohne DB)\n";
    exit(0);
}

MigrationRunner::runPending();
if (!CashLocationRepository::tableExists() || !CashRegisterRepository::tableExists()) {
    echo "FAIL Tabellen dg_cash_locations / dg_cash_registers fehlen\n";
    exit(1);
}

$codeLoc = 'ST-K5D-' . substr(bin2hex(random_bytes(3)), 0, 6);
$codeReg = 'KR-K5D-' . substr(bin2hex(random_bytes(3)), 0, 6);
$locId = CashLocationRepository::insert([
    'code' => $codeLoc,
    'name' => 'Selftest Filiale K5d',
    'is_active' => 1,
]);
$regId = CashRegisterRepository::insert([
    'location_id' => $locId,
    'code' => $codeReg,
    'name' => 'Selftest Kasse K5d',
    'register_type' => 'store',
    'next_receipt_number' => 100,
    'receipt_number_prefix' => 'T-',
    'is_active' => 1,
]);

$a = CashRegisterRepository::allocateNextReceiptNumber($regId);
expect_true($a['number'] === 100, 'erste Vergabe = 100');
expect_true($a['display'] === 'T-100', 'Anzeige T-100');

$b = CashRegisterRepository::allocateNextReceiptNumber($regId);
expect_true($b['number'] === 101, 'zweite Vergabe = 101');
expect_true($b['display'] === 'T-101', 'Anzeige T-101');

$after = CashRegisterRepository::findById($regId);
expect_true((int) ($after['next_receipt_number'] ?? 0) === 102, 'Zähler steht auf 102');

$threw = false;
try {
    CashRegisterRepository::allocateNextReceiptNumber(0);
} catch (RuntimeException $e) {
    $threw = true;
}
expect_true($threw, 'ungültige Kassen-ID abgewiesen');

// Aufräumen (Selftest-Daten)
Database::pdo()->prepare('DELETE FROM dg_cash_registers WHERE id = :id')->execute(['id' => $regId]);
Database::pdo()->prepare('DELETE FROM dg_cash_locations WHERE id = :id')->execute(['id' => $locId]);
echo "OK  Selftest-Daten entfernt\n";

if ($fail > 0) {
    fwrite(STDERR, "cash-register-number-selftest: {$fail} Fehler\n");
    exit(1);
}

echo "cash-register-number-selftest OK\n";
exit(0);
