<?php
declare(strict_types=1);

/**
 * Selftest K2c: MockTseProvider ohne Netz.
 * php bin/mock-tse-selftest.php
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

expect_true(interface_exists('TseProviderInterface'), 'Interface TseProviderInterface');
expect_true(class_exists('MockTseProvider'), 'Klasse MockTseProvider');

$mock = new MockTseProvider();
expect_true($mock instanceof TseProviderInterface, 'Mock implementiert TseProviderInterface');
expect_true($mock->getName() === 'mock', 'getName = mock');
expect_true(method_exists($mock, 'supportsDeferredSignature'), 'K14c Capability-Methode');
expect_true($mock->supportsDeferredSignature() === true, 'Mock erlaubt Nachsignatur-Pfad (Tests)');

$status = $mock->getStatus();
expect_true(!empty($status['ok']), 'getStatus ok');
expect_true(($status['state'] ?? '') === 'INITIALIZED', 'getStatus INITIALIZED');
expect_true(($status['tss_id'] ?? '') === MockTseProvider::TSS_ID, 'feste TSS-ID');

$txId = 'aaaaaaaa-bbbb-4ccc-8ddd-eeeeeeeeeeee';
$start = $mock->startTransaction($txId, ['amount_gross' => 0]);
expect_true(($start['state'] ?? '') === 'ACTIVE', 'start → ACTIVE');
expect_true(($start['revision'] ?? 0) === 1, 'start revision 1');
expect_true(($start['tse_tx_id'] ?? '') === $txId, 'start tx id');
expect_true($start['signature_value'] === null, 'start ohne Signatur');

$upd = $mock->updateTransaction($txId, 1, [
    'amount_gross' => 11.90,
    'vat_amounts' => [['vat_rate_code' => 'NORMAL', 'amount' => 11.90]],
    'client_serial_number' => 'KASSE-1',
]);
expect_true(($upd['state'] ?? '') === 'ACTIVE', 'update → ACTIVE');
expect_true(($upd['revision'] ?? 0) === 2, 'update revision 2');
expect_true(($upd['client_serial_number'] ?? '') === 'KASSE-1', 'client serial übernommen');

$fin = $mock->finishTransaction($txId, 2, [
    'amount_gross' => 11.90,
]);
expect_true(($fin['state'] ?? '') === 'FINISHED', 'finish → FINISHED');
expect_true(($fin['revision'] ?? 0) === 3, 'finish revision 3');
expect_true(is_string($fin['signature_value'] ?? null) && str_starts_with((string) $fin['signature_value'], 'MOCK-'), 'Mock-Signatur');
expect_true(is_string($fin['qr_code_data'] ?? null) && $fin['qr_code_data'] !== '', 'QR-Daten gesetzt');
expect_true(($fin['time_end'] ?? null) !== null, 'time_end gesetzt');

$threw = false;
try {
    $mock->updateTransaction($txId, 3, []);
} catch (RuntimeException $e) {
    $threw = true;
    expect_true(str_contains($e->getMessage(), 'ACTIVE'), 'Update nach Finish blockiert');
}
expect_true($threw, 'Update nach Finish wirft');

$txId2 = 'ffffffff-eeee-4ddd-8ccc-bbbbbbbbbbbb';
$mock->startTransaction($txId2, []);
$cancel = $mock->cancelTransaction($txId2, 1);
expect_true(($cancel['state'] ?? '') === 'CANCELLED', 'cancel → CANCELLED');
expect_true($cancel['qr_code_data'] === null, 'cancel ohne QR');

$export = $mock->exportData(['date_from' => '2026-01-01', 'date_to' => '2026-01-31']);
expect_true(!empty($export['ok']), 'export ok');
expect_true(is_string($export['file_bytes'] ?? null) && str_contains((string) $export['file_bytes'], 'MOCK-TSE-EXPORT'), 'export bytes');
expect_true(($export['checksum'] ?? '') !== '', 'export checksum');

$bad = false;
try {
    $mock->startTransaction('not-a-uuid', []);
} catch (RuntimeException $e) {
    $bad = true;
}
expect_true($bad, 'ungültige UUID abgewiesen');

if ($fail > 0) {
    fwrite(STDERR, "mock-tse-selftest: {$fail} Fehler\n");
    exit(1);
}

echo "mock-tse-selftest OK\n";
exit(0);
