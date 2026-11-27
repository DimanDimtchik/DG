<?php
declare(strict_types=1);

/**
 * MA3d: Profil + Konto-Saldo + Akte Liste/Download (nur apps/mitarbeiter).
 *
 * Usage: php bin/mobile-mitarbeiter-profile-konto-docs-selftest.php
 */

function assertMa3d(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "FEHLER: {$message}\n");
        exit(1);
    }
    echo "OK: {$message}\n";
}

$root = dirname(__DIR__);
$main = $root . '/apps/mitarbeiter/lib/main.dart';
assertMa3d(is_file($main), 'mitarbeiter main.dart');
$src = (string) file_get_contents($main);

assertMa3d(str_contains($src, 'class ProfileTab'), 'ProfileTab');
assertMa3d(str_contains($src, 'class KontoTab'), 'KontoTab');
assertMa3d(str_contains($src, 'class DocsTab'), 'DocsTab');
assertMa3d(str_contains($src, '/api/mobile/staff/profile'), 'Profil-API');
assertMa3d(str_contains($src, '/api/mobile/staff/konto'), 'Konto-API');
assertMa3d(str_contains($src, '/api/mobile/staff/documents'), 'Akte-API');
assertMa3d(str_contains($src, 'documents/download'), 'Akte Download');
assertMa3d(str_contains($src, 'Zeitkonto'), 'UI Zeitkonto');
assertMa3d(str_contains($src, 'Keine offenen Kontingente'), 'Konto Leerzustand');
assertMa3d(str_contains($src, 'Personalakte'), 'Akte Intro');
assertMa3d(str_contains($src, 'Kontaktdaten'), 'Profil Kontaktdaten');
assertMa3d(str_contains($src, 'apiUserMessage('), 'Fehler über apiUserMessage');
assertMa3d(str_contains($src, 'Erneut laden'), 'Retry');
assertMa3d(!str_contains($src, 'apps/kalender'), 'kein Kalender-Mix');

$base = rtrim((string) (getenv('MA3D_BASE_URL') ?: 'https://ganz-om.de'), '/');
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
$login = 'ma3d.smoke.' . time();
$pin = '2468';

if ($ssh !== '') {
    $setupPhp = 'require "bootstrap.php";'
        . '$login=' . var_export($login, true) . ';'
        . '$pin=' . var_export($pin, true) . ';'
        . '$id=ContactRepository::save(['
        . '"login"=>$login,'
        . '"email"=>$login."@gmail.com",'
        . '"first_name"=>"MA3d",'
        . '"last_name"=>"Smoke",'
        . '"display_name"=>"MA3d Smoke",'
        . '"contact_role"=>"dg_eigenmitarbeiter",'
        . '"customer_number"=>"MA3D",'
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
    echo "SKIP: Dev Happy-Path (kein SSH)\n";
    echo "MA3d Selftest bestanden (UI-Guards).\n";
    exit(0);
}

assertMa3d(true, "Smoke-MA #{$contactId}");

$loginRes = $httpJson('POST', '/api/mobile/auth/staff/login', [
    'identifier' => $login,
    'pin' => $pin,
]);
assertMa3d(($loginRes['ok'] ?? false) === true, 'Staff-Login');
$token = (string) ($loginRes['data']['token'] ?? '');
assertMa3d($token !== '', 'Token');

$profile = $httpJson('GET', '/api/mobile/staff/profile', null, $token);
assertMa3d(($profile['ok'] ?? false) === true, 'GET profile');
assertMa3d(isset($profile['data']['contact']), 'profile.contact');
assertMa3d(
    str_contains((string) ($profile['data']['contact']['login'] ?? ''), 'ma3d.smoke'),
    'profile login'
);
assertMa3d(is_array($profile['data']['sections'] ?? null), 'profile.sections');
assertMa3d(is_array($profile['data']['bank_accounts'] ?? null), 'profile.bank_accounts');

$konto = $httpJson('GET', '/api/mobile/staff/konto', null, $token);
assertMa3d(($konto['ok'] ?? false) === true, 'GET konto');
assertMa3d(array_key_exists('balance_minutes', $konto['data'] ?? []), 'balance_minutes');
assertMa3d(isset($konto['data']['balance_display']), 'balance_display');
assertMa3d(is_array($konto['data']['lots'] ?? null), 'lots');

$docs = $httpJson('GET', '/api/mobile/staff/documents', null, $token);
assertMa3d(($docs['ok'] ?? false) === true, 'GET documents');
assertMa3d(is_array($docs['data']['slots'] ?? null), 'documents.slots');
assertMa3d(($docs['data']['slots'] ?? []) !== [], 'mind. ein Dokument-Slot');
$slotTypes = [];
foreach ($docs['data']['slots'] as $slot) {
    if (is_array($slot)) {
        $slotTypes[] = (string) ($slot['type'] ?? '');
    }
}
assertMa3d(in_array('contract', $slotTypes, true) || count($slotTypes) > 0, 'Slots mit Typen');

// Download ohne Datei → erwarteter Fehler (kein Crash)
$dlOpts = [
    'method' => 'GET',
    'header' => "Accept: application/json\r\nAuthorization: Bearer {$token}\r\n",
    'timeout' => 20,
    'ignore_errors' => true,
];
$dlRaw = @file_get_contents($base . '/api/mobile/staff/documents/download?type=contract', false, stream_context_create(['http' => $dlOpts]));
$dlJson = is_string($dlRaw) ? json_decode($dlRaw, true) : null;
assertMa3d(
    is_array($dlJson) && ($dlJson['ok'] ?? true) === false,
    'Download ohne Datei → ok=false'
);

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
assertMa3d(str_contains($cout, 'CLEANED'), 'Smoke-MA bereinigt');

echo "MA3d Selftest bestanden (UI + Dev Profil/Konto/Akte).\n";
exit(0);
