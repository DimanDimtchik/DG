<?php
declare(strict_types=1);

/**
 * Selftest: TSE-Schema, VAT-Aufteilung, HTTP-Client (Mock), QR-SVG.
 * php bin/tse-sign-selftest.php
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

expect_true(TseSignService::vatRateCode(19) === 'NORMAL', '19 % → NORMAL');
expect_true(TseSignService::vatRateCode(7) === 'REDUCED_1', '7 % → REDUCED_1');
expect_true(TseSignService::vatRateCode(0) === 'NULL', '0 % → NULL');
expect_true(TseSignService::money(10) === '10.00', 'money 10');
expect_true(TseSettings::sanitizeUuid('2E03F52A-0000-4000-8000-000000000001') === '2e03f52a-0000-4000-8000-000000000001', 'UUID normalisiert');
expect_true(TseSettings::sanitizeUuid('not-a-uuid') === '', 'ungültige UUID leer');
expect_true(TseSettings::sanitizeEnvironment('LIVE') === TseSettings::ENV_LIVE, 'Umgebung live');
expect_true(TseSettings::sanitizeEnvironment('x') === TseSettings::ENV_TEST, 'Umgebung fallback test');

$uuid = TseSignService::uuidV4();
expect_true((bool) preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', $uuid), 'UUIDv4');

$voucher = [
    'tax_rate' => 19,
    'gross_amount' => '29,70',
    'items' => [
        ['title' => 'Artikel A', 'gross_amount' => '19,00', 'tax_rate' => 19],
        ['title' => 'Artikel B', 'gross_amount' => '10,70', 'tax_rate' => 7],
    ],
];
$parts = TseSignService::allocateVatAmounts($voucher, 10.00);
$sum = 0.0;
foreach ($parts as $p) {
    $sum = round($sum + (float) $p['amount'], 2);
}
expect_true($sum === 10.00, 'VAT-Anteile summieren auf Zahlbetrag');
expect_true(count($parts) === 2, 'zwei Steuersätze');

$schema = TseSignService::buildReceiptSchema($voucher, 10.00);
$receipt = $schema['standard_v1']['receipt'] ?? [];
expect_true(($receipt['receipt_type'] ?? '') === 'RECEIPT', 'receipt_type RECEIPT');
$pay = $receipt['amounts_per_payment_type'][0] ?? [];
expect_true(($pay['payment_type'] ?? '') === 'CASH', 'payment CASH');
expect_true(($pay['amount'] ?? '') === '10.00', 'Bar 10.00');
expect_true(($pay['currency_code'] ?? '') === 'EUR', 'EUR');

$offer = ['voucher_type' => 'income', 'document_kind' => VoucherDocumentKind::OFFER];
$invoice = ['voucher_type' => 'income', 'document_kind' => VoucherDocumentKind::INVOICE];
$expense = ['voucher_type' => 'expense', 'document_kind' => ''];
expect_true(TseSignService::shouldSign($invoice, 'cash') === TseSettings::isEnabled(), 'shouldSign folgt enabled');
expect_true(TseSignService::shouldSign($offer, 'cash') === false || !TseSettings::isEnabled(), 'Angebot nicht signieren');
expect_true(TseSignService::shouldSign($invoice, 'bank') === false, 'Überweisung nicht signieren');
expect_true(TseSignService::shouldSign($expense, 'cash') === false, 'Ausgabe nicht signieren');

$calls = [];
$transport = static function (string $method, string $path, ?array $body, array $headers) use (&$calls): array {
    $calls[] = ['method' => $method, 'path' => $path, 'body' => $body, 'has_auth' => isset($headers['Authorization'])];
    if ($path === '/auth') {
        return ['status' => 200, 'json' => ['access_token' => 'tok', 'access_token_claims' => ['env' => 'TEST']]];
    }
    if (str_contains($path, 'tx_revision=1')) {
        return ['status' => 200, 'json' => ['state' => 'ACTIVE', 'time_start' => 1700000000, 'number' => 1]];
    }
    if (str_contains($path, 'tx_revision=2')) {
        return ['status' => 200, 'json' => [
            '_id' => 'aaaaaaaa-bbbb-4ccc-8ddd-eeeeeeeeeeee',
            'state' => 'FINISHED',
            'number' => 1,
            'revision' => 2,
            'qr_code_data' => 'V0;TEST;Kassenbeleg-V1;Beleg^10.00_0.00_0.00_0.00_0.00^10.00:Bar;1;1;2026-11-06T09:48:20.000Z;2026-11-06T09:48:20.000Z;ecdsa-plain-SHA256;unixTime;sig;pk',
            'signature' => ['value' => 'abc', 'algorithm' => 'ecdsa-plain-SHA256'],
            'client_serial_number' => 'DGCRM-TEST-KASSE-01',
            'time_start' => 1700000000,
            'time_end' => 1700000001,
        ]];
    }
    if (str_contains($path, '/tss/') && !str_contains($path, '/tx/')) {
        return ['status' => 200, 'json' => ['state' => 'INITIALIZED', '_type' => 'TSS']];
    }

    return ['status' => 400, 'json' => ['message' => 'unexpected ' . $path]];
};

$client = new TseSignDeClient([
    'api_key' => 'k',
    'api_secret' => 's',
    'tss_id' => '00000000-0000-4000-8000-000000000001',
    'client_id' => '00000000-0000-4000-8000-000000000002',
    'base_url' => 'https://example.test/api/v2',
    'environment' => 'test',
    'enabled' => true,
], $transport);

$started = $client->upsertTransaction('bbbbbbbb-cccc-4ddd-8eee-ffffffffffff', 1, [
    'state' => 'ACTIVE',
    'client_id' => $client->clientId(),
]);
$finished = $client->upsertTransaction('bbbbbbbb-cccc-4ddd-8eee-ffffffffffff', 2, [
    'state' => 'FINISHED',
    'client_id' => $client->clientId(),
    'schema' => $schema,
]);
expect_true(($started['state'] ?? '') === 'ACTIVE', 'Mock START ACTIVE');
expect_true(($finished['state'] ?? '') === 'FINISHED', 'Mock FINISH FINISHED');
expect_true(($calls[0]['path'] ?? '') === '/auth', 'Auth vor TX');
expect_true(!empty($calls[1]['has_auth']), 'Bearer nach Auth');
expect_true(str_contains((string) ($finished['qr_code_data'] ?? ''), 'Kassenbeleg-V1'), 'QR-Payload');

$block = TseSignService::printBlock([
    'state' => TseTransactionRepository::STATE_FINISHED,
    'qr_code_data' => $finished['qr_code_data'],
    'client_serial_number' => 'DGCRM-TEST-KASSE-01',
    'signature_counter' => '1',
    'tx_number' => '1',
    'signature_algorithm' => 'ecdsa-plain-SHA256',
    'time_start' => 1700000000,
    'time_end' => 1700000001,
]);
expect_true(is_array($block), 'printBlock gesetzt');
expect_true(is_array($block) && str_starts_with((string) ($block['qr_svg'] ?? ''), '<svg'), 'QR-SVG');
expect_true(is_array($block) && str_contains((string) ($block['qr_svg'] ?? ''), '<rect'), 'QR-Module');

$err = TseSignDeClient::errorMessage(400, ['message' => 'PIN blocked']);
expect_true(str_contains($err, 'PIN blocked'), 'Fehlertext durchgereicht');

$tabs = SettingsRegistry::allTabs();
expect_true(isset($tabs['tse']), 'Settings-Tab TSE');
expect_true(($tabs['tse']['template'] ?? '') === 'tse', 'TSE-Template');

if (TseSettings::isReady()) {
    try {
        $live = new TseSignDeClient();
        $tss = $live->retrieveTss();
        $cli = $live->retrieveClient();
        expect_true(($tss['state'] ?? '') === 'INITIALIZED', 'Live TSS INITIALIZED');
        expect_true(($cli['state'] ?? '') === 'REGISTERED', 'Live Client REGISTERED');
    } catch (Throwable $e) {
        echo 'FAIL Live-Ping: ' . $e->getMessage() . "\n";
        $fail++;
    }
} else {
    echo "SKIP Live-Ping (keine Zugangsdaten)\n";
}

exit($fail > 0 ? 1 : 0);
