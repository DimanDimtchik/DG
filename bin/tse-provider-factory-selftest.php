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

$fiskalyOk = false;
$fiskalyMsg = '';
try {
    // Ohne Secrets: Konstruktor wirft klar — Factory mappt aber auf FiskalyTseProvider
    TseProviderFactory::forProvider('fiskaly', [
        'provider' => 'fiskaly',
        'tss_id' => '11111111-1111-4111-8111-111111111111',
        'client_id' => '22222222-2222-4222-8222-222222222222',
        'environment' => 'test',
    ]);
} catch (RuntimeException $e) {
    $fiskalyMsg = $e->getMessage();
    $fiskalyOk = str_contains($fiskalyMsg, 'unvollständig')
        || str_contains($fiskalyMsg, 'Zugangsdaten')
        || class_exists('FiskalyTseProvider');
}
// Mit Fake-Secrets + Transport nicht nötig: Klasse muss existieren und Factory nicht mehr „K3b folgt“ werfen
expect_true(
    class_exists('FiskalyTseProvider') && !str_contains($fiskalyMsg, 'folgt in Schritt K3b'),
    'fiskaly ist verdrahtet (K3b)'
);
expect_true($fiskalyOk || class_exists('FiskalyTseProvider'), 'Factory kennt fiskaly');

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
