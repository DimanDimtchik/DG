<?php
declare(strict_types=1);

/**
 * Selftest K10a: Kassen-Typ mobile im Modell.
 * php bin/cash-register-type-selftest.php
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

$labels = CashRegisterRepository::typeLabels();
expect_true(isset($labels['mobile']), 'typeLabels enthält mobile');
expect_true(($labels['mobile'] ?? '') === 'Mobile Kasse', 'Label Mobile Kasse');
expect_true(CashRegisterRepository::sanitizeType('mobile') === 'mobile', 'sanitize mobile');
expect_true(CashRegisterRepository::sanitizeType('MOBILE') === 'mobile', 'sanitize MOBILE');
expect_true(CashRegisterRepository::sanitizeType('xyz') === 'store', 'unbekannt → store');
expect_true(CashRegisterRepository::sanitizeType('') === 'store', 'leer → store');

if ($fail > 0) {
    fwrite(STDERR, "cash-register-type-selftest: {$fail} Fehler\n");
    exit(1);
}

echo "cash-register-type-selftest OK\n";
exit(0);
