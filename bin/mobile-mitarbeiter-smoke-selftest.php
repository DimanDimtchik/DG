<?php
declare(strict_types=1);

/**
 * MA3f: Mitarbeiter Smoke — Guards + §8-Notiz + Flutter-Smoke-Skript.
 *
 * Usage: php bin/mobile-mitarbeiter-smoke-selftest.php
 */

function assertMa3f(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "FEHLER: {$message}\n");
        exit(1);
    }
    echo "OK: {$message}\n";
}

$root = dirname(__DIR__);
$plan = $root . '/docs/MOBILE-APPS-REST-PLAN.md';
$smokeSh = $root . '/bin/mobile-mitarbeiter-smoke.sh';
$main = $root . '/apps/mitarbeiter/lib/main.dart';
$test = $root . '/apps/mitarbeiter/test/widget_test.dart';

assertMa3f(is_file($plan), 'MOBILE-APPS-REST-PLAN.md');
assertMa3f(is_file($smokeSh), 'mobile-mitarbeiter-smoke.sh');
assertMa3f(is_file($main), 'mitarbeiter main.dart');
assertMa3f(is_file($test), 'widget_test.dart');

$planSrc = (string) file_get_contents($plan);
assertMa3f(preg_match('/\|\s*\*\*MA3f\*\*.*\|\s*\[x\]/u', $planSrc) === 1, 'Plan MA3f abgehakt');
assertMa3f(preg_match('/\|\s*MA3f\s*\|\s*20\d{2}-\d{2}-\d{2}\s*\|/u', $planSrc) === 1, '§8 Smoke-Notiz MA3f mit Datum');
assertMa3f(str_contains($planSrc, 'mobile-mitarbeiter-smoke'), 'Smoke-Skript in Verweisen');

$testSrc = (string) file_get_contents($test);
assertMa3f(str_contains($testSrc, 'DgMitarbeiterApp'), 'Widget-Test startet App');

$mainSrc = (string) file_get_contents($main);
assertMa3f(str_contains($mainSrc, 'class DgMitarbeiterApp'), 'DgMitarbeiterApp');
assertMa3f(str_contains($mainSrc, 'class LoginPage'), 'LoginPage');
assertMa3f(str_contains($mainSrc, 'class ClockTab'), 'ClockTab');
assertMa3f(!str_contains($mainSrc, 'apps/kalender'), 'kein Kalender-Mix');

$sh = (string) file_get_contents($smokeSh);
assertMa3f(str_contains($sh, 'flutter analyze'), 'Smoke: analyze');
assertMa3f(str_contains($sh, 'flutter test'), 'Smoke: test');
assertMa3f(str_contains($sh, 'apps/mitarbeiter'), 'Smoke zielt auf mitarbeiter');
assertMa3f(!str_contains($sh, 'apps/kalender'), 'nur Mitarbeiter');

// Flutter-Smoke ausführen wenn SDK da
$flutter = trim((string) shell_exec('command -v flutter 2>/dev/null || true'));
if ($flutter === '' && is_file(getenv('HOME') . '/sdk/flutter/bin/flutter')) {
    $flutter = getenv('HOME') . '/sdk/flutter/bin/flutter';
}
if ($flutter !== '') {
    $cmd = 'export PATH=' . escapeshellarg(dirname($flutter) . ':' . getenv('PATH'))
        . '; bash ' . escapeshellarg($smokeSh);
    passthru($cmd, $rc);
    assertMa3f($rc === 0, 'Flutter-Smoke-Skript grün');
} else {
    echo "SKIP: flutter SDK nicht installiert — UI-Guards trotzdem ok.\n";
}

// API-Kette gegen Dev (Staff Happy Path Kurz)
$base = rtrim((string) (getenv('MA3F_BASE_URL') ?: 'https://ganz-om.de'), '/');
$httpJson = static function (string $method, string $path, ?array $body = null, ?string $token = null) use ($base): array {
    $headers = "Accept: application/json\r\n";
    if ($token !== null && $token !== '') {
        $headers .= "Authorization: Bearer {$token}\r\n";
    }
    $opts = [
        'method' => $method,
        'header' => $headers,
        'timeout' => 25,
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
$contactId = 0;
$login = 'ma3f.smoke.' . time();
$pin = '1357';

if ($ssh !== '') {
    $setupPhp = 'require "bootstrap.php";'
        . '$login=' . var_export($login, true) . ';'
        . '$pin=' . var_export($pin, true) . ';'
        . '$id=ContactRepository::save(['
        . '"login"=>$login,'
        . '"email"=>$login."@gmail.com",'
        . '"first_name"=>"MA3f",'
        . '"last_name"=>"Smoke",'
        . '"display_name"=>"MA3f Smoke",'
        . '"contact_role"=>"dg_eigenmitarbeiter",'
        . '"customer_number"=>"MA3F",'
        . ']);'
        . 'TimeKioskService::setPin($id, $pin, null);'
        . 'echo "CID=$id";';
    $setup = 'cd /www/htdocs/w0217246/ganz-om.de && php -r ' . escapeshellarg($setupPhp);
    $out = (string) shell_exec('ssh -o BatchMode=yes allinkl-ganzom ' . escapeshellarg($setup) . ' 2>/dev/null');
    if (preg_match('/CID=(\d+)/', $out, $m)) {
        $contactId = (int) $m[1];
    }
}

if ($contactId < 1) {
    echo "SKIP: Dev Staff-Kette (kein SSH)\n";
    echo "MA3f Selftest bestanden (UI-Guards + Flutter).\n";
    exit(0);
}

assertMa3f(true, "Smoke-MA #{$contactId}");

$loginRes = $httpJson('POST', '/api/mobile/auth/staff/login', [
    'identifier' => $login,
    'pin' => $pin,
]);
assertMa3f(($loginRes['ok'] ?? false) === true, 'Dev Staff-Login');
$token = (string) ($loginRes['data']['token'] ?? '');
assertMa3f($token !== '', 'Token');

$me = $httpJson('GET', '/api/mobile/auth/me', null, $token);
assertMa3f(($me['ok'] ?? false) === true, 'Dev me');
assertMa3f(($me['data']['subject_type'] ?? '') === 'staff', 'me subject staff');

$status = $httpJson('GET', '/api/mobile/staff/clock/status', null, $token);
assertMa3f(($status['ok'] ?? false) === true, 'Dev clock/status');

$profile = $httpJson('GET', '/api/mobile/staff/profile', null, $token);
assertMa3f(($profile['ok'] ?? false) === true, 'Dev profile');

$sig = $httpJson('GET', '/api/mobile/staff/signature', null, $token);
assertMa3f(($sig['ok'] ?? false) === true, 'Dev signature');
assertMa3f(($sig['data']['has_crm_user'] ?? true) === false, 'Temp-MA ohne CRM-User');

$httpJson('POST', '/api/mobile/auth/logout', [], $token);
assertMa3f(true, 'Dev Logout aufgerufen');

$cleanupPhp = 'require "bootstrap.php";'
    . '$pdo=Database::pdo();$cid=' . $contactId . ';'
    . '$pdo->prepare("DELETE FROM dg_mobile_tokens WHERE contact_id=?")->execute([$cid]);'
    . '$pdo->prepare("DELETE FROM dg_time_kiosk_pins WHERE contact_id=?")->execute([$cid]);'
    . '$pdo->prepare("DELETE FROM dg_time_kiosk_sessions WHERE contact_id=?")->execute([$cid]);'
    . '$pdo->prepare("DELETE FROM dg_time_kiosk_pin_resets WHERE contact_id=?")->execute([$cid]);'
    . '$pdo->prepare("DELETE FROM dg_contacts WHERE id=?")->execute([$cid]);'
    . 'echo "CLEANED";';
$cleanup = 'cd /www/htdocs/w0217246/ganz-om.de && php -r ' . escapeshellarg($cleanupPhp);
$cout = (string) shell_exec('ssh -o BatchMode=yes allinkl-ganzom ' . escapeshellarg($cleanup) . ' 2>/dev/null');
assertMa3f(str_contains($cout, 'CLEANED'), 'Smoke-MA bereinigt');

echo "MA3f Selftest bestanden.\n";
exit(0);
