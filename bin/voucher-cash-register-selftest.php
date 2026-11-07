<?php
declare(strict_types=1);

/**
 * Selftest K9b: Barzahlung braucht Kasse wenn Kassen existieren (ohne DB-Signatur).
 * php bin/voucher-cash-register-selftest.php
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

expect_true(method_exists('VoucherPaymentRepository', 'normalizeCashRegisterId'), 'normalizeCashRegisterId');
expect_true(method_exists('TseSignService', 'clientConfigForPayment'), 'clientConfigForPayment');

$bankNull = VoucherPaymentRepository::normalizeCashRegisterId(5, VoucherPaymentRepository::METHOD_BANK);
expect_true($bankNull === null, 'Bank → keine Kasse');

$cfg = TseSignService::clientConfigForPayment([]);
expect_true(is_array($cfg) && isset($cfg['tss_id']), 'Config ohne Kasse = Settings');

if (!Database::isConfigured()) {
    echo "SKIP DB — Guard-Tests ok.\n";
    if ($fail > 0) {
        fwrite(STDERR, "voucher-cash-register-selftest: {$fail} Fehler\n");
        exit(1);
    }
    echo "voucher-cash-register-selftest OK (ohne DB)\n";
    exit(0);
}

MigrationRunner::runPending();
expect_true(
    VoucherPaymentRepository::hasCashRegisterColumn() || !CashRegisterRepository::tableExists(),
    'Spalte cash_register_id nach Migration (oder noch keine Kassen-Tabelle)'
);

if ($fail > 0) {
    fwrite(STDERR, "voucher-cash-register-selftest: {$fail} Fehler\n");
    exit(1);
}

echo "voucher-cash-register-selftest OK\n";
exit(0);
