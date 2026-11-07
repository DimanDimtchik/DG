<?php
declare(strict_types=1);

/**
 * Selftest K3d: TseProviderFactory + Mock-Pfad (kein Netz, kein Fiskaly).
 * php bin/tse-provider-factory-selftest.php
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

expect_true(class_exists('TseProviderFactory'), 'Klasse TseProviderFactory');
expect_true(TseProviderFactory::defaultProviderName() === 'mock', 'Default = mock');
expect_true(in_array('mock', TseProviderFactory::knownProviders(), true), 'known enthält mock');
expect_true(in_array('fiskaly', TseProviderFactory::knownProviders(), true), 'known enthält fiskaly');

$byName = TseProviderFactory::forProvider('mock');
expect_true($byName instanceof TseProviderInterface, 'forProvider(mock) → Interface');
expect_true($byName instanceof MockTseProvider, 'forProvider(mock) → Mock');
expect_true($byName->getName() === 'mock', 'Provider-Name mock');

$status = $byName->getStatus();
expect_true(!empty($status['ok']), 'Mock-Pfad getStatus ok');

$fromNull = TseProviderFactory::fromAssignment(null);
expect_true($fromNull instanceof MockTseProvider, 'fromAssignment(null) → Mock');

$fromRow = TseProviderFactory::fromAssignment([
    'provider' => 'mock',
    'tss_id' => MockTseProvider::TSS_ID,
    'client_id' => MockTseProvider::CLIENT_ID,
]);
expect_true($fromRow instanceof MockTseProvider, 'fromAssignment(provider=mock) → Mock');

$txId = '11111111-2222-4333-8444-555555555555';
$start = $fromRow->startTransaction($txId, ['amount_gross' => 1.0]);
$fin = $fromRow->finishTransaction($txId, (int) $start['revision'], ['amount_gross' => 1.0]);
expect_true(($fin['state'] ?? '') === 'FINISHED', 'Factory-Mock Bon finish');
expect_true(is_string($fin['qr_code_data'] ?? null) && $fin['qr_code_data'] !== '', 'Factory-Mock QR');

$fiskalyThrows = false;
try {
    TseProviderFactory::forProvider('fiskaly');
} catch (RuntimeException $e) {
    $fiskalyThrows = str_contains($e->getMessage(), 'K3b');
}
expect_true($fiskalyThrows, 'fiskaly wirft bis K3b');

$unknownThrows = false;
try {
    TseProviderFactory::forProvider('swissbit');
} catch (RuntimeException $e) {
    $unknownThrows = str_contains($e->getMessage(), 'Unbekannt');
}
expect_true($unknownThrows, 'unbekannter Provider abgewiesen');

$emptyProvider = TseProviderFactory::fromAssignment(['provider' => '']);
expect_true($emptyProvider instanceof MockTseProvider, 'leerer provider → Default mock');

if ($fail > 0) {
    fwrite(STDERR, "tse-provider-factory-selftest: {$fail} Fehler\n");
    exit(1);
}

echo "tse-provider-factory-selftest OK\n";
exit(0);
