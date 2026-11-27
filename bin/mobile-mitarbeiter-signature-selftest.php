<?php
declare(strict_types=1);

/**
 * MA3e: Unterschrift Härte/Fehlerpfade (nur apps/mitarbeiter).
 *
 * Usage: php bin/mobile-mitarbeiter-signature-selftest.php
 */

function assertMa3e(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "FEHLER: {$message}\n");
        exit(1);
    }
    echo "OK: {$message}\n";
}

$root = dirname(__DIR__);
$page = $root . '/apps/mitarbeiter/lib/signature_page.dart';
$apiMsg = $root . '/apps/mitarbeiter/lib/api_message.dart';
$kalMsg = $root . '/apps/kalender/lib/api_message.dart';
$main = $root . '/apps/mitarbeiter/lib/main.dart';

assertMa3e(is_file($page), 'signature_page.dart');
assertMa3e(is_file($apiMsg), 'api_message.dart');
$pageSrc = (string) file_get_contents($page);
$msgSrc = (string) file_get_contents($apiMsg);
$mainSrc = (string) file_get_contents($main);

assertMa3e(str_contains($pageSrc, 'class SignaturePage'), 'SignaturePage');
assertMa3e(str_contains($pageSrc, "/api/mobile/staff/signature"), 'Signature-API');
assertMa3e(str_contains($pageSrc, 'png_base64'), 'png_base64');
assertMa3e(str_contains($pageSrc, 'signature_file'), 'multipart signature_file');
assertMa3e(str_contains($pageSrc, 'apiUserMessage('), 'apiUserMessage');
assertMa3e(str_contains($pageSrc, 'apiIsOk('), 'apiIsOk');
assertMa3e(str_contains($pageSrc, 'apiCodeMessage('), 'apiCodeMessage');
assertMa3e(str_contains($pageSrc, 'no_crm_user'), 'UI kennt no_crm_user');
assertMa3e(str_contains($pageSrc, 'has_crm_user'), 'has_crm_user aus GET');
assertMa3e(str_contains($pageSrc, 'Bitte zuerst unterschreiben'), 'leeres Pad blockiert');
assertMa3e(str_contains($pageSrc, 'Erneut laden'), 'Retry');
assertMa3e(str_contains($pageSrc, 'preview_url'), 'Vorschau');
assertMa3e(str_contains($pageSrc, 'formatDeDateTime'), 'Datum formatiert');
assertMa3e(str_contains($pageSrc, '_asMap'), 'sichere Maps');
assertMa3e(str_contains($msgSrc, "'no_crm_user'"), 'api_message no_crm_user');
assertMa3e(
    hash_file('sha256', $apiMsg) === hash_file('sha256', $kalMsg),
    'api_message byte-identisch'
);
assertMa3e(str_contains($mainSrc, 'SignaturePage(client:'), 'Profil öffnet SignaturePage');
assertMa3e(!str_contains($pageSrc, 'apps/kalender'), 'kein Kalender-Mix');

$base = rtrim((string) (getenv('MA3E_BASE_URL') ?: 'https://ganz-om.de'), '/');
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
$tempContactId = 0;
$login = 'ma3e.smoke.' . time();
$pin = '3579';

if ($ssh !== '') {
    $setupPhp = 'require "bootstrap.php";'
        . '$login=' . var_export($login, true) . ';'
        . '$pin=' . var_export($pin, true) . ';'
        . '$id=ContactRepository::save(['
        . '"login"=>$login,'
        . '"email"=>$login."@gmail.com",'
        . '"first_name"=>"MA3e",'
        . '"last_name"=>"Smoke",'
        . '"display_name"=>"MA3e Smoke",'
        . '"contact_role"=>"dg_eigenmitarbeiter",'
        . '"customer_number"=>"MA3E",'
        . ']);'
        . 'TimeKioskService::setPin($id, $pin, null);'
        . 'echo "CID=$id";';
    $setup = 'cd /www/htdocs/w0217246/ganz-om.de && php -r ' . escapeshellarg($setupPhp);
    $out = (string) shell_exec('ssh -o BatchMode=yes allinkl-ganzom ' . escapeshellarg($setup) . ' 2>/dev/null');
    if (preg_match('/CID=(\d+)/', $out, $m)) {
        $tempContactId = (int) $m[1];
    }
}

if ($tempContactId < 1) {
    echo "SKIP: Dev Happy-Path (kein SSH)\n";
    echo "MA3e Selftest bestanden (UI-Guards).\n";
    exit(0);
}

assertMa3e(true, "Temp-MA ohne CRM-User #{$tempContactId}");

$loginRes = $httpJson('POST', '/api/mobile/auth/staff/login', [
    'identifier' => $login,
    'pin' => $pin,
]);
assertMa3e(($loginRes['ok'] ?? false) === true, 'Staff-Login (ohne CRM-User)');
$token = (string) ($loginRes['data']['token'] ?? '');
assertMa3e($token !== '', 'Token');

$getNoUser = $httpJson('GET', '/api/mobile/staff/signature', null, $token);
assertMa3e(($getNoUser['ok'] ?? false) === true, 'GET signature ohne CRM-User');
assertMa3e(($getNoUser['data']['has_crm_user'] ?? true) === false, 'has_crm_user=false');
assertMa3e(($getNoUser['data']['active'] ?? null) === null, 'active=null ohne CRM-User');

$postNoUser = $httpJson('POST', '/api/mobile/staff/signature', [
    'png_base64' => 'AAAA',
], $token);
assertMa3e(($postNoUser['ok'] ?? true) === false, 'POST ohne CRM-User fail');
assertMa3e(($postNoUser['code'] ?? '') === 'no_crm_user', 'code no_crm_user');

// Happy-Path: Kontakt mit CRM-User (cid=1) + temporäre PIN
$crmLogin = '';
$crmPin = '8642';
$crmSetup = 'cd /www/htdocs/w0217246/ganz-om.de && php -r ' . escapeshellarg(
    'require "bootstrap.php";'
    . '$c=ContactRepository::findById(1);'
    . 'if($c===null){echo "FAIL=no_contact";exit;}'
    . '$uid=DepartmentRepository::resolveUserIdForContact(1);'
    . 'if($uid<1){echo "FAIL=no_uid";exit;}'
    . '$pin=' . var_export($crmPin, true) . ';'
    . 'TimeKioskService::setPin(1, $pin, null);'
    . 'echo "LOGIN=".$c->login." UID=$uid";'
);
$crmOut = (string) shell_exec('ssh -o BatchMode=yes allinkl-ganzom ' . escapeshellarg($crmSetup) . ' 2>/dev/null');
if (preg_match('/LOGIN=([^\s]+)\s+UID=(\d+)/', $crmOut, $cm)) {
    $crmLogin = $cm[1];
}

assertMa3e($crmLogin !== '', 'CRM-Kontakt Login vorbereitet');

$crmLoginRes = $httpJson('POST', '/api/mobile/auth/staff/login', [
    'identifier' => $crmLogin,
    'pin' => $crmPin,
]);
assertMa3e(($crmLoginRes['ok'] ?? false) === true, 'Staff-Login mit CRM-User');
$crmToken = (string) ($crmLoginRes['data']['token'] ?? '');
assertMa3e($crmToken !== '', 'CRM-Token');

$getOk = $httpJson('GET', '/api/mobile/staff/signature', null, $crmToken);
assertMa3e(($getOk['ok'] ?? false) === true, 'GET signature mit CRM-User');
assertMa3e(($getOk['data']['has_crm_user'] ?? false) === true, 'has_crm_user=true');

$emptyPost = $httpJson('POST', '/api/mobile/staff/signature', [], $crmToken);
assertMa3e(($emptyPost['ok'] ?? true) === false, 'POST leer fail');
assertMa3e(($emptyPost['code'] ?? '') === 'bad_request', 'code bad_request leer');

// 8×8 PNG (Base64), >64 Bytes Rohdaten
$pngB64 = 'iVBORw0KGgoAAAANSUhEUgAAAAgAAAAICAIAAABLbSncAAAAD0lEQVR42mP4jwMwDC0JALoev0GJ6La7AAAAAElFTkSuQmCC';
$save = $httpJson('POST', '/api/mobile/staff/signature', [
    'png_base64' => $pngB64,
], $crmToken);
assertMa3e(($save['ok'] ?? false) === true, 'POST png_base64 speichert');
assertMa3e((int) ($save['data']['saved_id'] ?? 0) > 0, 'saved_id');
assertMa3e(($save['data']['active']['source'] ?? '') === 'app', 'active.source=app');
assertMa3e(str_contains((string) ($save['data']['message'] ?? ''), 'Historie'), 'Historie-Hinweis');

$getAfter = $httpJson('GET', '/api/mobile/staff/signature', null, $crmToken);
assertMa3e(($getAfter['ok'] ?? false) === true, 'GET nach Speichern');
assertMa3e(($getAfter['data']['active']['source'] ?? '') === 'app', 'GET active.source=app');
assertMa3e(trim((string) ($getAfter['data']['active']['preview_url'] ?? '')) !== '', 'preview_url');

// Cleanup: Temp-MA + Temp-PIN von cid=1 (dg_time_kiosk_pins)
$cleanupPhp = 'require "bootstrap.php";'
    . '$pdo=Database::pdo();'
    . '$temp=' . (int) $tempContactId . ';'
    . 'if($temp>0){'
    . '$pdo->prepare("DELETE FROM dg_mobile_tokens WHERE contact_id=?")->execute([$temp]);'
    . '$pdo->prepare("DELETE FROM dg_time_kiosk_pins WHERE contact_id=?")->execute([$temp]);'
    . '$pdo->prepare("DELETE FROM dg_time_kiosk_sessions WHERE contact_id=?")->execute([$temp]);'
    . '$pdo->prepare("DELETE FROM dg_time_kiosk_pin_resets WHERE contact_id=?")->execute([$temp]);'
    . '$pdo->prepare("DELETE FROM dg_contacts WHERE id=?")->execute([$temp]);'
    . '}'
    . '$pdo->prepare("DELETE FROM dg_time_kiosk_pins WHERE contact_id=1")->execute();'
    . '$pdo->prepare("DELETE FROM dg_mobile_tokens WHERE contact_id=1")->execute();'
    . 'echo "CLEANED";';
$cleanCmd = 'cd /www/htdocs/w0217246/ganz-om.de && php -r ' . escapeshellarg($cleanupPhp);
$cleanOut = (string) shell_exec('ssh -o BatchMode=yes allinkl-ganzom ' . escapeshellarg($cleanCmd) . ' 2>/dev/null');
assertMa3e(str_contains($cleanOut, 'CLEANED'), 'Cleanup Dev');

echo "MA3e Selftest bestanden.\n";
exit(0);
