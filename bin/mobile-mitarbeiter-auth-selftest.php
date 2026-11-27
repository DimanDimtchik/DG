<?php
declare(strict_types=1);

/**
 * MA3a: Mitarbeiter Login Kennung+PIN (nur apps/mitarbeiter).
 *
 * Usage: php bin/mobile-mitarbeiter-auth-selftest.php
 */

function assertMa3a(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "FEHLER: {$message}\n");
        exit(1);
    }
    echo "OK: {$message}\n";
}

$root = dirname(__DIR__);
$main = $root . '/apps/mitarbeiter/lib/main.dart';
assertMa3a(is_file($main), 'mitarbeiter main.dart');
$src = (string) file_get_contents($main);

assertMa3a(str_contains($src, 'class LoginPage'), 'LoginPage');
assertMa3a(str_contains($src, '_validateLocal'), 'Client-Validierung');
assertMa3a(str_contains($src, 'Bitte Kennung eingeben.'), 'Meldung Kennung fehlt');
assertMa3a(str_contains($src, 'Bitte PIN eingeben.'), 'Meldung PIN fehlt');
assertMa3a(str_contains($src, 'PIN muss'), 'Meldung PIN-Länge');
assertMa3a(str_contains($src, 'PIN darf nur Ziffern'), 'Meldung PIN-Ziffern');
assertMa3a(str_contains($src, '/api/mobile/auth/staff/login'), 'Staff-Login-Endpunkt');
assertMa3a(str_contains($src, 'apiUserMessage('), 'API-Fehler über apiUserMessage');
assertMa3a(str_contains($src, 'Anmeldung fehlgeschlagen.'), 'Login-Fallback');
assertMa3a(str_contains($src, 'mobileSaveToken('), 'Token speichern');
assertMa3a(str_contains($src, "data['token']"), 'Token aus data sicher');
assertMa3a(str_contains($src, 'Stempeluhr-PIN'), 'Hinweis Stempeluhr-PIN');
assertMa3a(!str_contains($src, 'apps/kalender'), 'kein Kalender-App-Mix');

$base = rtrim((string) (getenv('MA3A_BASE_URL') ?: 'https://ganz-om.de'), '/');
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

// Fehlerpfade ohne Prep
$badEmpty = $httpJson('POST', '/api/mobile/auth/staff/login', [
    'identifier' => '',
    'pin' => '',
]);
assertMa3a(($badEmpty['ok'] ?? true) === false, 'Dev Login leer → ok=false');
assertMa3a(is_string($badEmpty['error'] ?? null) && trim((string) $badEmpty['error']) !== '', 'Dev Login error-Text');

$badUser = $httpJson('POST', '/api/mobile/auth/staff/login', [
    'identifier' => 'ma3a-does-not-exist-' . time(),
    'pin' => '1234',
]);
assertMa3a(($badUser['ok'] ?? true) === false, 'Dev Login unbekannter MA → ok=false');
assertMa3a(
    str_contains(mb_strtolower((string) ($badUser['error'] ?? '')), 'nicht gefunden')
        || str_contains(mb_strtolower((string) ($badUser['error'] ?? '')), 'ungültig'),
    'Dev Login unbekannter MA Meldung'
);

// Happy Path: temporären Mitarbeiter + PIN auf Dev anlegen (SSH)
$login = 'ma3a.smoke.' . time();
$pin = '2468';
$email = $login . '@gmail.com';
$ssh = trim((string) shell_exec('command -v ssh'));
$contactId = 0;
if ($ssh !== '') {
    $setup = 'cd /www/htdocs/w0217246/ganz-om.de && php -r ' . escapeshellarg(
        'require "bootstrap.php";'
        . '$id=ContactRepository::save(['
        . '"login"=>' . var_export($login, true) . ','
        . '"email"=>' . var_export($email, true) . ','
        . '"first_name"=>"MA3a",'
        . '"last_name"=>"Smoke",'
        . '"display_name"=>"MA3a Smoke",'
        . '"contact_role"=>"dg_eigenmitarbeiter",'
        . '"customer_number"=>"MA3A",'
        . ']);'
        . 'TimeKioskService::setPin($id, ' . var_export($pin, true) . ', null);'
        . 'echo "CID=$id";'
    );
    $out = (string) shell_exec('ssh -o BatchMode=yes allinkl-ganzom ' . escapeshellarg($setup) . ' 2>/dev/null');
    if (preg_match('/CID=(\d+)/', $out, $m)) {
        $contactId = (int) $m[1];
    }
}

if ($contactId > 0) {
    assertMa3a(true, "Smoke-MA angelegt (#{$contactId})");

    $badPin = $httpJson('POST', '/api/mobile/auth/staff/login', [
        'identifier' => $login,
        'pin' => '9999',
    ]);
    assertMa3a(($badPin['ok'] ?? true) === false, 'Dev Login falsche PIN → ok=false');
    assertMa3a(
        str_contains(mb_strtolower((string) ($badPin['error'] ?? '')), 'pin'),
        'Dev Login falsche PIN Meldung'
    );

    $ok = $httpJson('POST', '/api/mobile/auth/staff/login', [
        'identifier' => $login,
        'pin' => $pin,
    ]);
    assertMa3a(($ok['ok'] ?? false) === true, 'Dev Login Kennung+PIN ok');
    $token = (string) ($ok['data']['token'] ?? '');
    assertMa3a($token !== '', 'Token');
    assertMa3a(($ok['data']['subject_type'] ?? '') === 'staff', 'subject_type staff');

    $me = $httpJson('GET', '/api/mobile/auth/me', null, $token);
    assertMa3a(($me['ok'] ?? false) === true, 'Dev me nach Staff-Login');
    assertMa3a(($me['data']['subject_type'] ?? '') === 'staff', 'me subject staff');

    $logout = $httpJson('POST', '/api/mobile/auth/logout', [], $token);
    assertMa3a(($logout['ok'] ?? false) === true, 'Dev Logout');

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
    assertMa3a(str_contains($cout, 'CLEANED'), 'Smoke-MA bereinigt');
} else {
    echo "SKIP: Happy-Path (kein SSH / Kontakt nicht anlegbar) — Fehlerpfade trotzdem geprüft.\n";
}

echo "MA3a Selftest bestanden (UI + Dev Staff-Login).\n";
exit(0);
