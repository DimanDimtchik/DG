<?php
declare(strict_types=1);

/**
 * Selftest K3b: FiskalyTseProvider mit injiziertem Transport (kein Netz).
 * php bin/fiskaly-tse-provider-selftest.php
 */
require dirname(__DIR__) . '/bootstrap.php';

$fail = 0;
$expect = static function (bool $cond, string $msg) use (&$fail): void {
    if ($cond) {
        echo "OK  {$msg}\n";
        return;
    }
    echo "FAIL {$msg}\n";
    $fail++;
};

$expect(class_exists('FiskalyTseProvider'), 'Klasse FiskalyTseProvider');
$expect(FiskalyTseProvider::NAME === 'fiskaly', 'NAME=fiskaly');
$expect(FiskalyTseProvider::mapReceiptType('storno') === 'CANCELLATION', 'storno→CANCELLATION');
$expect(FiskalyTseProvider::mapReceiptType('sale') === 'RECEIPT', 'sale→RECEIPT');

$schema = FiskalyTseProvider::buildSchemaFromInput([
    'receipt_type' => 'sale',
    'amount_gross' => 11.9,
    'vat_amounts' => [['vat_rate_code' => 'NORMAL', 'amount' => 11.9]],
    'payment_types' => [['payment_method' => 'cash', 'amount' => 11.9]],
]);
$expect(isset($schema['standard_v1']['receipt']['receipt_type']), 'Schema receipt_type');
$expect($schema['standard_v1']['receipt']['receipt_type'] === 'RECEIPT', 'Schema RECEIPT');

$calls = [];
$transport = static function (string $method, string $path, ?array $body, array $headers) use (&$calls): array {
    $calls[] = ['method' => $method, 'path' => $path, 'body' => $body];
    if (str_contains($path, '/auth')) {
        return ['status' => 200, 'json' => ['access_token' => 'test-token']];
    }
    if (str_contains($path, '/client/')) {
        return ['status' => 200, 'json' => [
            'state' => 'REGISTERED',
            'serial_number' => 'CLIENT-1',
        ]];
    }
    if (preg_match('#/tss/[^/]+$#', $path) === 1 && $method === 'GET') {
        return ['status' => 200, 'json' => [
            'state' => 'INITIALIZED',
            'serial_number' => 'TSS-1',
            'certificate_expiry_date' => '2099-01-01',
        ]];
    }
    // Transaction upsert
    $rev = 1;
    if (preg_match('/tx_revision=(\d+)/', $path, $m)) {
        $rev = (int) $m[1];
    }
    $state = (string) ($body['state'] ?? 'ACTIVE');
    $json = [
        '_id' => 'aaaaaaaa-bbbb-4ccc-8ddd-eeeeeeeeeeee',
        'state' => $state,
        'number' => 42,
        'revision' => $rev,
        'time_start' => 1700000000,
        'tss_serial_number' => 'TSS-1',
        'client_serial_number' => 'KASSE-1',
    ];
    if ($state === 'FINISHED') {
        $json['time_end'] = 1700000005;
        $json['qr_code_data'] = 'V0;TSS-1;aaaaaaaa-bbbb-4ccc-8ddd-eeeeeeeeeeee;7;11.90';
        $json['signature'] = [
            'value' => 'SIG-TEST',
            'algorithm' => 'ecdsa-plain-SHA256',
            'counter' => '7',
        ];
    }

    return ['status' => 200, 'json' => $json];
};

$cfg = [
    'api_key' => 'k',
    'api_secret' => 's',
    'tss_id' => '11111111-1111-4111-8111-111111111111',
    'client_id' => '22222222-2222-4222-8222-222222222222',
    'environment' => 'test',
    'base_url' => 'https://example.test',
];
$provider = new FiskalyTseProvider($cfg, $transport);
$expect($provider->getName() === 'fiskaly', 'getName');
$expect($provider->supportsDeferredSignature() === false, 'keine Nachsignatur');

$status = $provider->getStatus();
$expect(!empty($status['ok']), 'getStatus ok');
$expect(($status['state'] ?? '') === 'INITIALIZED', 'TSS INITIALIZED');

$txId = 'aaaaaaaa-bbbb-4ccc-8ddd-eeeeeeeeeeee';
$start = $provider->startTransaction($txId, ['amount_gross' => 11.9, 'client_serial_number' => 'KASSE-1']);
$expect(($start['state'] ?? '') === 'ACTIVE', 'start ACTIVE');
$upd = $provider->updateTransaction($txId, (int) $start['revision'], ['amount_gross' => 11.9]);
$fin = $provider->finishTransaction($txId, (int) $upd['revision'], [
    'receipt_type' => 'sale',
    'amount_gross' => 11.9,
    'vat_amounts' => [['vat_rate_code' => 'NORMAL', 'amount' => 11.9]],
    'payment_types' => [['payment_method' => 'cash', 'amount' => 11.9]],
    'client_serial_number' => 'KASSE-1',
]);
$expect(($fin['state'] ?? '') === 'FINISHED', 'finish FINISHED');
$expect(trim((string) ($fin['qr_code_data'] ?? '')) !== '', 'QR vorhanden');
$expect(trim((string) ($fin['signature_value'] ?? '')) === 'SIG-TEST', 'Signatur');

$fromFactory = TseProviderFactory::forProvider('fiskaly', [
    'provider' => 'fiskaly',
    'tss_id' => $cfg['tss_id'],
    'client_id' => $cfg['client_id'],
    'environment' => 'test',
]);
$expect($fromFactory instanceof FiskalyTseProvider, 'Factory → FiskalyTseProvider');

if ($fail > 0) {
    fwrite(STDERR, "fiskaly-tse-provider-selftest: {$fail} Fehler\n");
    exit(1);
}
echo "fiskaly-tse-provider-selftest OK\n";
exit(0);
