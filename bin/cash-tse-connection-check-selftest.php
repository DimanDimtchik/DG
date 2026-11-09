<?php
declare(strict_types=1);

/**
 * Selftest K6c: CashTseConnectionCheck Guard-Logik (ohne DB/Netz).
 * php bin/cash-tse-connection-check-selftest.php
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

expect_true(class_exists('CashTseConnectionCheck'), 'Klasse CashTseConnectionCheck');
expect_true(class_exists('TseProviderFactory'), 'Factory vorhanden');

$mock = TseProviderFactory::forProvider('mock');
$status = $mock->getStatus();
expect_true(!empty($status['ok']), 'Mock getStatus ok');
expect_true(($status['state'] ?? '') === 'INITIALIZED', 'Mock state INITIALIZED');

$invalidIdThrows = false;
try {
    CashTseConnectionCheck::runForRegister(0);
} catch (RuntimeException $e) {
    $invalidIdThrows = str_contains($e->getMessage(), 'fehlt');
}
expect_true($invalidIdThrows, 'registerId 0 abgewiesen');

if ($fail > 0) {
    fwrite(STDERR, "cash-tse-connection-check-selftest: {$fail} Fehler\n");
    exit(1);
}

echo "cash-tse-connection-check-selftest OK\n";
exit(0);
