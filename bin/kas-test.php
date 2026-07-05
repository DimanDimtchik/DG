<?php
declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap.php';

try {
    $accounts = KasMailProvisioner::class;
    $cfg = KasSettings::config();
    echo 'KAS login: ' . $cfg['kas_login'] . PHP_EOL;

    $client = new SoapClient('https://kasapi.kasserver.com/soap/wsdl/KasApi.wsdl', [
        'exceptions' => true,
        'connection_timeout' => 20,
    ]);
    $payload = json_encode([
        'kas_login' => $cfg['kas_login'],
        'kas_auth_type' => $cfg['kas_auth_type'],
        'kas_auth_data' => $cfg['kas_auth_data'],
        'kas_action' => 'get_mailaccounts',
        'KasRequestParams' => new stdClass(),
    ], JSON_THROW_ON_ERROR);
    $raw = $client->KasApi($payload);
    echo 'Response type: ' . gettype($raw) . PHP_EOL;
    if (is_array($raw)) {
        print_r($raw);
    } else {
        echo (string) $raw . PHP_EOL;
    }
} catch (Throwable $e) {
    fwrite(STDERR, $e->getMessage() . PHP_EOL);
    exit(1);
}
