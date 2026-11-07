<?php
declare(strict_types=1);

/**
 * Selftest K10b: eigene Kassen-ID + getrennte Nummernkreise.
 * php bin/cash-register-identity-selftest.php
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

expect_true(method_exists('CashRegisterRepository', 'requireUniqueCode'), 'requireUniqueCode');

$emptyThrows = false;
try {
    CashRegisterRepository::requireUniqueCode('');
} catch (RuntimeException $e) {
    $emptyThrows = str_contains($e->getMessage(), 'Pflicht');
}
expect_true($emptyThrows, 'leere Kassen-ID abgewiesen');

if (!Database::isConfigured()) {
    echo "SKIP DB — Guard-Tests ok.\n";
    if ($fail > 0) {
        fwrite(STDERR, "cash-register-identity-selftest: {$fail} Fehler\n");
        exit(1);
    }
    echo "cash-register-identity-selftest OK (ohne DB)\n";
    exit(0);
}

MigrationRunner::runPending();
if (!CashLocationRepository::tableExists() || !CashRegisterRepository::tableExists()) {
    echo "FAIL Tabellen fehlen\n";
    exit(1);
}

$sfx = substr(bin2hex(random_bytes(3)), 0, 6);
$locId = CashLocationRepository::insert([
    'code' => 'ST-K10B-' . $sfx,
    'name' => 'Selftest Filiale K10b',
    'is_active' => 1,
]);
$codeA = 'KA-K10B-' . $sfx;
$codeB = 'KB-K10B-' . $sfx;
$regA = CashRegisterRepository::insert([
    'location_id' => $locId,
    'code' => $codeA,
    'name' => 'Kasse A',
    'register_type' => 'mobile',
    'next_receipt_number' => 10,
    'receipt_number_prefix' => 'A-',
    'is_active' => 1,
]);
$regB = CashRegisterRepository::insert([
    'location_id' => $locId,
    'code' => $codeB,
    'name' => 'Kasse B',
    'register_type' => 'store',
    'next_receipt_number' => 50,
    'receipt_number_prefix' => 'B-',
    'is_active' => 1,
]);

$dupThrows = false;
try {
    CashRegisterRepository::insert([
        'location_id' => $locId,
        'code' => $codeA,
        'name' => 'Duplikat',
        'is_active' => 1,
    ]);
} catch (RuntimeException $e) {
    $dupThrows = str_contains($e->getMessage(), 'bereits');
}
expect_true($dupThrows, 'Doppelte Kassen-ID abgewiesen');

$nA1 = CashRegisterRepository::allocateNextReceiptNumber($regA);
$nB1 = CashRegisterRepository::allocateNextReceiptNumber($regB);
$nA2 = CashRegisterRepository::allocateNextReceiptNumber($regA);
expect_true($nA1['number'] === 10 && $nA1['display'] === 'A-10', 'A erste Nummer 10');
expect_true($nB1['number'] === 50 && $nB1['display'] === 'B-50', 'B erste Nummer 50 (eigen)');
expect_true($nA2['number'] === 11, 'A zweite Nummer 11');
expect_true(
    (int) (CashRegisterRepository::findById($regB)['next_receipt_number'] ?? 0) === 51,
    'B-Zähler unabhängig auf 51'
);

CashRegisterRepository::update($regA, ['code' => 'HACKED', 'name' => 'Kasse A2']);
$after = CashRegisterRepository::findById($regA);
expect_true(($after['code'] ?? '') === $codeA, 'code unveränderbar bei update');
expect_true(($after['name'] ?? '') === 'Kasse A2', 'name änderbar');

$pdo = Database::pdo();
$pdo->prepare('DELETE FROM dg_cash_registers WHERE id = :id')->execute(['id' => $regA]);
$pdo->prepare('DELETE FROM dg_cash_registers WHERE id = :id')->execute(['id' => $regB]);
$pdo->prepare('DELETE FROM dg_cash_locations WHERE id = :id')->execute(['id' => $locId]);
echo "OK  Selftest-Daten entfernt\n";

if ($fail > 0) {
    fwrite(STDERR, "cash-register-identity-selftest: {$fail} Fehler\n");
    exit(1);
}

echo "cash-register-identity-selftest OK\n";
exit(0);
