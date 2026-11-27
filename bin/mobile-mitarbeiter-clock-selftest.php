<?php
declare(strict_types=1);

/**
 * MA3b: Mitarbeiter Stempel Ein/Aus/Pause + Tagesstatus (nur apps/mitarbeiter).
 *
 * Usage: php bin/mobile-mitarbeiter-clock-selftest.php
 */

function assertMa3b(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "FEHLER: {$message}\n");
        exit(1);
    }
    echo "OK: {$message}\n";
}

$root = dirname(__DIR__);
$main = $root . '/apps/mitarbeiter/lib/main.dart';
$clockSvc = $root . '/src/TimeTracking/TimeClockService.php';
assertMa3b(is_file($main), 'mitarbeiter main.dart');
assertMa3b(is_file($clockSvc), 'TimeClockService.php');

$src = (string) file_get_contents($main);
$svc = (string) file_get_contents($clockSvc);

assertMa3b(str_contains($src, 'class ClockTab'), 'ClockTab');
assertMa3b(str_contains($src, '/api/mobile/staff/clock'), 'Clock-API');
assertMa3b(str_contains($src, "clock_in"), 'Einstempeln');
assertMa3b(str_contains($src, "clock_out"), 'Ausstempeln');
assertMa3b(str_contains($src, "break_start"), 'Pause starten');
assertMa3b(str_contains($src, "break_end"), 'Pause beenden');
assertMa3b(str_contains($src, '_busy'), 'Busy gegen Doppelklick');
assertMa3b(str_contains($src, 'Ausstempeln?'), 'Bestätigung Ausstempeln');
assertMa3b(str_contains($src, '_eventSuccessMessage'), 'Erfolgs-Snackbar');
assertMa3b(str_contains($src, 'Gearbeitet'), 'Tagesstatus Gearbeitet');
assertMa3b(str_contains($src, 'Heutige Stempel'), 'Event-Liste heute');
assertMa3b(str_contains($src, 'apiUserMessage('), 'Fehler über apiUserMessage');
assertMa3b(!str_contains($src, 'apps/kalender'), 'kein Kalender-Mix');

assertMa3b(str_contains($svc, 'SOURCE_MOBILE'), 'SOURCE_MOBILE erlaubt');
assertMa3b(str_contains($svc, "'App'") || str_contains($svc, '"App"'), 'source_label App');

$base = rtrim((string) (getenv('MA3B_BASE_URL') ?: 'https://ganz-om.de'), '/');
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
$login = 'ma3b.smoke.' . time();
$pin = '2468';
$token = '';

if ($ssh !== '') {
    $setupPhp = 'require "bootstrap.php";'
        . '$login=' . var_export($login, true) . ';'
        . '$pin=' . var_export($pin, true) . ';'
        . '$id=ContactRepository::save(['
        . '"login"=>$login,'
        . '"email"=>$login."@gmail.com",'
        . '"first_name"=>"MA3b",'
        . '"last_name"=>"Smoke",'
        . '"display_name"=>"MA3b Smoke",'
        . '"contact_role"=>"dg_eigenmitarbeiter",'
        . '"customer_number"=>"MA3B",'
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
    echo "SKIP: Dev Happy-Path (kein SSH / Kontakt)\n";
    echo "MA3b Selftest bestanden (UI-Guards).\n";
    exit(0);
}

assertMa3b(true, "Smoke-MA #{$contactId}");

$loginRes = $httpJson('POST', '/api/mobile/auth/staff/login', [
    'identifier' => $login,
    'pin' => $pin,
]);
assertMa3b(($loginRes['ok'] ?? false) === true, 'Staff-Login');
$token = (string) ($loginRes['data']['token'] ?? '');
assertMa3b($token !== '', 'Token');

$status0 = $httpJson('GET', '/api/mobile/staff/clock', null, $token);
assertMa3b(($status0['ok'] ?? false) === true, 'GET clock');
assertMa3b(($status0['data']['status']['state'] ?? '') === 'off', 'Startzustand off');
assertMa3b(isset($status0['data']['summary']['worked_minutes']), 'Tagesstatus worked_minutes');

$in = $httpJson('POST', '/api/mobile/staff/clock', ['event_type' => 'clock_in'], $token);
assertMa3b(($in['ok'] ?? false) === true, 'clock_in');
assertMa3b(($in['data']['status']['state'] ?? '') === 'working', 'state working nach Ein');

$bad = $httpJson('POST', '/api/mobile/staff/clock', ['event_type' => 'clock_in'], $token);
assertMa3b(($bad['ok'] ?? true) === false, 'Doppel-Ein abgelehnt');

$br = $httpJson('POST', '/api/mobile/staff/clock', ['event_type' => 'break_start'], $token);
assertMa3b(($br['ok'] ?? false) === true, 'break_start');
assertMa3b(($br['data']['status']['state'] ?? '') === 'break', 'state break');

$be = $httpJson('POST', '/api/mobile/staff/clock', ['event_type' => 'break_end'], $token);
assertMa3b(($be['ok'] ?? false) === true, 'break_end');
assertMa3b(($be['data']['status']['state'] ?? '') === 'working', 'state working nach Pause');

$outRes = $httpJson('POST', '/api/mobile/staff/clock', ['event_type' => 'clock_out'], $token);
assertMa3b(($outRes['ok'] ?? false) === true, 'clock_out');
assertMa3b(($outRes['data']['status']['state'] ?? '') === 'off', 'state off nach Aus');

$events = $outRes['data']['summary']['events'] ?? [];
assertMa3b(is_array($events) && count($events) >= 4, 'mind. 4 Events heute');
$sources = [];
foreach ($events as $ev) {
    if (is_array($ev)) {
        $sources[] = (string) ($ev['source'] ?? '');
    }
}
assertMa3b(in_array('mobile', $sources, true), 'Events mit source=mobile');
$labels = [];
foreach ($events as $ev) {
    if (is_array($ev)) {
        $labels[] = (string) ($ev['source_label'] ?? '');
    }
}
assertMa3b(in_array('App', $labels, true), 'source_label App');

$cleanupPhp = 'require "bootstrap.php";'
    . '$pdo=Database::pdo();$cid=' . $contactId . ';'
    . '$pdo->prepare("DELETE FROM dg_time_clock_events WHERE contact_id=?")->execute([$cid]);'
    . '$pdo->prepare("DELETE FROM dg_mobile_tokens WHERE contact_id=?")->execute([$cid]);'
    . '$pdo->prepare("DELETE FROM dg_time_kiosk_pins WHERE contact_id=?")->execute([$cid]);'
    . '$pdo->prepare("DELETE FROM dg_time_kiosk_sessions WHERE contact_id=?")->execute([$cid]);'
    . '$pdo->prepare("DELETE FROM dg_time_kiosk_pin_resets WHERE contact_id=?")->execute([$cid]);'
    . '$pdo->prepare("DELETE FROM dg_contacts WHERE id=?")->execute([$cid]);'
    . 'echo "CLEANED";';
$cleanup = 'cd /www/htdocs/w0217246/ganz-om.de && php -r ' . escapeshellarg($cleanupPhp);
$cout = (string) shell_exec('ssh -o BatchMode=yes allinkl-ganzom ' . escapeshellarg($cleanup) . ' 2>/dev/null');
assertMa3b(str_contains($cout, 'CLEANED'), 'Smoke-MA bereinigt');

echo "MA3b Selftest bestanden (UI + Dev Stempel-Kette + source=mobile).\n";
exit(0);
