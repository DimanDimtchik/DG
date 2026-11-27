<?php
declare(strict_types=1);

/**
 * MA4a: PHP Auth + Customer- + Staff-Happy-Path (Dev-API, keine Tenant-Daten hardcoden).
 *
 * Usage:
 *   php bin/mobile-auth-happy-path-selftest.php
 *   MA4A_BASE_URL=https://ganz-om.de php bin/mobile-auth-happy-path-selftest.php
 */

function assertMa4a(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "FEHLER: {$message}\n");
        exit(1);
    }
    echo "OK: {$message}\n";
}

$root = dirname(__DIR__);
$authApi = $root . '/src/Mobile/MobileAuthApi.php';
$authSvc = $root . '/src/Mobile/MobileAuthService.php';
$plan = $root . '/docs/MOBILE-APPS-REST-PLAN.md';

assertMa4a(is_file($authApi), 'MobileAuthApi.php');
assertMa4a(is_file($authSvc), 'MobileAuthService.php');

$apiSrc = (string) file_get_contents($authApi);
$svcSrc = (string) file_get_contents($authSvc);

assertMa4a(str_contains($apiSrc, 'customer/register') || str_contains($apiSrc, 'register'), 'Auth-Route Register');
assertMa4a(str_contains($apiSrc, 'loginCustomer') || str_contains($svcSrc, 'loginCustomer'), 'loginCustomer');
assertMa4a(str_contains($svcSrc, 'function loginStaff'), 'loginStaff');
assertMa4a(str_contains($svcSrc, 'function logout'), 'logout');
assertMa4a(str_contains($svcSrc, 'function refresh'), 'refresh');
assertMa4a(str_contains($svcSrc, 'function requireAuth'), 'requireAuth');
assertMa4a(str_contains($svcSrc, 'Kein CRM-User') || str_contains($svcSrc, 'kein CRM-User') || str_contains($svcSrc, 'dg_users'), 'kein CRM-User-Login');
assertMa4a(!preg_match('/ganz-om\.de|kontur-cosmetics|iqstrom|info@ganz/i', $svcSrc . $apiSrc), 'kein Tenant-Hardcode in Auth-PHP');

$planSrc = (string) file_get_contents($plan);
assertMa4a(preg_match('/\|\s*\*\*MA4a\*\*.*\|\s*\[x\]/u', $planSrc) === 1, 'Plan MA4a abgehakt');
assertMa4a(str_contains($planSrc, 'mobile-auth-happy-path-selftest'), 'Selftest in Verweisen');

$base = rtrim((string) (getenv('MA4A_BASE_URL') ?: 'https://ganz-om.de'), '/');
$httpJson = static function (string $method, string $path, ?array $body = null, ?string $token = null) use ($base): array {
    $headers = "Accept: application/json\r\n";
    if ($token !== null && $token !== '') {
        $headers .= "Authorization: Bearer {$token}\r\n";
    }
    $opts = [
        'method' => $method,
        'header' => $headers,
        'timeout' => 30,
        'ignore_errors' => true,
    ];
    if ($body !== null) {
        $opts['header'] .= "Content-Type: application/json\r\n";
        $opts['content'] = json_encode($body, JSON_UNESCAPED_UNICODE);
    }
    $raw = @file_get_contents($base . $path, false, stream_context_create(['http' => $opts]));
    if (!is_string($raw) || $raw === '') {
        return ['ok' => false, 'error' => 'leere Antwort'];
    }
    $json = json_decode($raw, true);

    return is_array($json) ? $json : ['ok' => false, 'error' => 'kein JSON'];
};

$ssh = trim((string) shell_exec('command -v ssh'));
$stamp = time();

// --- Auth-Negativpfade (ohne Prep) ---
$badCustomer = $httpJson('POST', '/api/mobile/auth/customer/login', [
    'email' => 'ma4a-does-not-exist-' . $stamp . '@gmail.com',
    'password' => 'WrongPass9!',
]);
assertMa4a(($badCustomer['ok'] ?? true) === false, 'Customer Login unbekannt → fail');
assertMa4a(trim((string) ($badCustomer['error'] ?? '')) !== '', 'Customer Login error-Text');
assertMa4a(trim((string) ($badCustomer['code'] ?? '')) !== '', 'Customer Login code');

$badStaff = $httpJson('POST', '/api/mobile/auth/staff/login', [
    'identifier' => 'ma4a-does-not-exist-' . $stamp,
    'pin' => '1234',
]);
assertMa4a(($badStaff['ok'] ?? true) === false, 'Staff Login unbekannt → fail');

$badToken = $httpJson('GET', '/api/mobile/auth/me', null, 'ma4a-invalid-token-' . $stamp);
assertMa4a(($badToken['ok'] ?? true) === false, 'me mit Fake-Token → fail');
assertMa4a(
    ($badToken['code'] ?? '') === 'unauthorized'
        || str_contains(mb_strtolower((string) ($badToken['error'] ?? '')), 'token')
        || str_contains(mb_strtolower((string) ($badToken['error'] ?? '')), 'authorization')
        || str_contains(mb_strtolower((string) ($badToken['error'] ?? '')), 'bearer'),
    'me Fake-Token Meldung/code'
);

// --- Customer Happy-Path ---
$custEmail = 'ma4a.cust.' . $stamp . '@gmail.com';
$custPass = 'Ma4aPass9!';
$reg = $httpJson('POST', '/api/mobile/auth/customer/register', [
    'email' => $custEmail,
    'password' => $custPass,
    'name' => 'MA4a Customer',
]);
assertMa4a(($reg['ok'] ?? false) === true, 'Customer Register');
$custToken = (string) ($reg['data']['token'] ?? '');
$custContactId = (int) ($reg['data']['contact']['id'] ?? 0);
assertMa4a($custToken !== '', 'Customer Token');
assertMa4a(($reg['data']['subject_type'] ?? '') === 'customer', 'subject_type customer');
assertMa4a($custContactId > 0, 'Customer contact_id');

$custMe = $httpJson('GET', '/api/mobile/auth/me', null, $custToken);
assertMa4a(($custMe['ok'] ?? false) === true, 'Customer me');
assertMa4a(($custMe['data']['subject_type'] ?? '') === 'customer', 'me subject customer');

$catalog = $httpJson('GET', '/api/mobile/customer/catalog', null, $custToken);
assertMa4a(($catalog['ok'] ?? false) === true, 'Customer Katalog (Happy-Path)');
assertMa4a(is_array($catalog['data']['articles'] ?? null), 'Katalog articles');

$custLogin = $httpJson('POST', '/api/mobile/auth/customer/login', [
    'email' => $custEmail,
    'password' => $custPass,
]);
assertMa4a(($custLogin['ok'] ?? false) === true, 'Customer Login nach Register');
$custToken2 = (string) ($custLogin['data']['token'] ?? '');
assertMa4a($custToken2 !== '', 'Customer Login Token');

$refresh = $httpJson('POST', '/api/mobile/auth/refresh', [], $custToken2);
assertMa4a(($refresh['ok'] ?? false) === true, 'Customer Refresh');
$custToken3 = (string) ($refresh['data']['token'] ?? '');
assertMa4a($custToken3 !== '' && $custToken3 !== $custToken2, 'Refresh liefert neues Token');

$logoutCust = $httpJson('POST', '/api/mobile/auth/logout', [], $custToken3);
assertMa4a(($logoutCust['ok'] ?? false) === true, 'Customer Logout');

$meAfterLogout = $httpJson('GET', '/api/mobile/auth/me', null, $custToken3);
assertMa4a(($meAfterLogout['ok'] ?? true) === false, 'me nach Logout → fail');

// --- Staff Happy-Path (Temp-MA via SSH, kein realer Tenant) ---
$staffLogin = 'ma4a.staff.' . $stamp;
$staffPin = '2468';
$staffContactId = 0;

if ($ssh !== '') {
    $setupPhp = 'require "bootstrap.php";'
        . '$login=' . var_export($staffLogin, true) . ';'
        . '$pin=' . var_export($staffPin, true) . ';'
        . '$id=ContactRepository::save(['
        . '"login"=>$login,'
        . '"email"=>$login."@gmail.com",'
        . '"first_name"=>"MA4a",'
        . '"last_name"=>"Staff",'
        . '"display_name"=>"MA4a Staff",'
        . '"contact_role"=>"dg_eigenmitarbeiter",'
        . '"customer_number"=>"MA4A",'
        . ']);'
        . 'TimeKioskService::setPin($id, $pin, null);'
        . 'echo "CID=$id";';
    $setup = 'cd /www/htdocs/w0217246/ganz-om.de && php -r ' . escapeshellarg($setupPhp);
    $out = (string) shell_exec('ssh -o BatchMode=yes allinkl-ganzom ' . escapeshellarg($setup) . ' 2>/dev/null');
    if (preg_match('/CID=(\d+)/', $out, $m)) {
        $staffContactId = (int) $m[1];
    }
}

if ($staffContactId < 1) {
    echo "SKIP: Staff Happy-Path (kein SSH)\n";
} else {
    assertMa4a(true, "Temp-Staff #{$staffContactId}");

    $staffOk = $httpJson('POST', '/api/mobile/auth/staff/login', [
        'identifier' => $staffLogin,
        'pin' => $staffPin,
    ]);
    assertMa4a(($staffOk['ok'] ?? false) === true, 'Staff Login');
    $staffToken = (string) ($staffOk['data']['token'] ?? '');
    assertMa4a($staffToken !== '', 'Staff Token');
    assertMa4a(($staffOk['data']['subject_type'] ?? '') === 'staff', 'subject_type staff');

    $staffMe = $httpJson('GET', '/api/mobile/auth/me', null, $staffToken);
    assertMa4a(($staffMe['ok'] ?? false) === true, 'Staff me');
    assertMa4a(($staffMe['data']['subject_type'] ?? '') === 'staff', 'me subject staff');

    $clock = $httpJson('GET', '/api/mobile/staff/clock/status', null, $staffToken);
    assertMa4a(($clock['ok'] ?? false) === true, 'Staff clock/status (Happy-Path)');

    // Cross-type: Staff-Token darf Customer-Katalog nicht nutzen
    $cross = $httpJson('GET', '/api/mobile/customer/catalog', null, $staffToken);
    assertMa4a(($cross['ok'] ?? true) === false, 'Staff-Token ≠ Customer-Katalog');

    $logoutStaff = $httpJson('POST', '/api/mobile/auth/logout', [], $staffToken);
    assertMa4a(($logoutStaff['ok'] ?? false) === true, 'Staff Logout');
}

// --- Cleanup Dev (Customer + Staff) ---
if ($ssh !== '' && ($custContactId > 0 || $staffContactId > 0)) {
    $cleanupPhp = 'require "bootstrap.php";'
        . '$pdo=Database::pdo();'
        . '$cids=[' . (int) $custContactId . ',' . (int) $staffContactId . '];'
        . 'foreach($cids as $cid){if($cid<1)continue;'
        . '$pdo->prepare("DELETE FROM dg_mobile_tokens WHERE contact_id=?")->execute([$cid]);'
        . '$pdo->prepare("DELETE FROM dg_mobile_customer_accounts WHERE contact_id=?")->execute([$cid]);'
        . '$pdo->prepare("DELETE FROM dg_time_kiosk_pins WHERE contact_id=?")->execute([$cid]);'
        . '$pdo->prepare("DELETE FROM dg_time_kiosk_sessions WHERE contact_id=?")->execute([$cid]);'
        . '$pdo->prepare("DELETE FROM dg_time_kiosk_pin_resets WHERE contact_id=?")->execute([$cid]);'
        . '$pdo->prepare("DELETE FROM dg_contacts WHERE id=?")->execute([$cid]);'
        . '}'
        . 'echo "CLEANED";';
    $cleanup = 'cd /www/htdocs/w0217246/ganz-om.de && php -r ' . escapeshellarg($cleanupPhp);
    $cout = (string) shell_exec('ssh -o BatchMode=yes allinkl-ganzom ' . escapeshellarg($cleanup) . ' 2>/dev/null');
    assertMa4a(str_contains($cout, 'CLEANED'), 'Dev Temp-Konten bereinigt');
} else {
    echo "SKIP: Cleanup (kein SSH oder keine IDs)\n";
}

echo "MA4a Selftest bestanden gegen {$base}.\n";
exit(0);
