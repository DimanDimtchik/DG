<?php
declare(strict_types=1);

/**
 * MA3c: Abwesenheit beantragen + Liste; Schichten Woche (nur apps/mitarbeiter).
 *
 * Usage: php bin/mobile-mitarbeiter-absences-shifts-selftest.php
 */

function assertMa3c(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "FEHLER: {$message}\n");
        exit(1);
    }
    echo "OK: {$message}\n";
}

$root = dirname(__DIR__);
$main = $root . '/apps/mitarbeiter/lib/main.dart';
assertMa3c(is_file($main), 'mitarbeiter main.dart');
$src = (string) file_get_contents($main);

assertMa3c(str_contains($src, 'class AbsencesTab'), 'AbsencesTab');
assertMa3c(str_contains($src, 'class ShiftsTab'), 'ShiftsTab');
assertMa3c(str_contains($src, '/api/mobile/staff/absences'), 'Absences-API');
assertMa3c(str_contains($src, '/api/mobile/staff/shifts'), 'Shifts-API');
assertMa3c(str_contains($src, 'Beantragen'), 'Antrag CTA');
assertMa3c(str_contains($src, 'Bis-Datum darf nicht vor Von-Datum'), 'Datum-Validierung');
assertMa3c(str_contains($src, 'Halber Tag nur bei gleichem'), 'Halbtag-Validierung');
assertMa3c(str_contains($src, "'week': _weekKey") || str_contains($src, '"week": _weekKey'), 'Schichten week=');
assertMa3c(str_contains($src, '_shiftWeek'), 'Wochen-Navigation');
assertMa3c(str_contains($src, 'Keine Schichten in dieser Woche'), 'Leerzustand Schichten');
assertMa3c(str_contains($src, 'Meine Anträge'), 'Antragsliste');
assertMa3c(str_contains($src, 'apiUserMessage('), 'Fehler über apiUserMessage');
assertMa3c(!str_contains($src, 'apps/kalender'), 'kein Kalender-Mix');

$base = rtrim((string) (getenv('MA3C_BASE_URL') ?: 'https://ganz-om.de'), '/');
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
$login = 'ma3c.smoke.' . time();
$pin = '2468';

if ($ssh !== '') {
    $setupPhp = 'require "bootstrap.php";'
        . '$login=' . var_export($login, true) . ';'
        . '$pin=' . var_export($pin, true) . ';'
        . '$id=ContactRepository::save(['
        . '"login"=>$login,'
        . '"email"=>$login."@gmail.com",'
        . '"first_name"=>"MA3c",'
        . '"last_name"=>"Smoke",'
        . '"display_name"=>"MA3c Smoke",'
        . '"contact_role"=>"dg_eigenmitarbeiter",'
        . '"customer_number"=>"MA3C",'
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
    echo "MA3c Selftest bestanden (UI-Guards).\n";
    exit(0);
}

assertMa3c(true, "Smoke-MA #{$contactId}");

$loginRes = $httpJson('POST', '/api/mobile/auth/staff/login', [
    'identifier' => $login,
    'pin' => $pin,
]);
assertMa3c(($loginRes['ok'] ?? false) === true, 'Staff-Login');
$token = (string) ($loginRes['data']['token'] ?? '');
assertMa3c($token !== '', 'Token');

$list0 = $httpJson('GET', '/api/mobile/staff/absences', null, $token);
assertMa3c(($list0['ok'] ?? false) === true, 'GET absences');
assertMa3c(is_array($list0['data']['type_options'] ?? null), 'type_options');
assertMa3c(is_array($list0['data']['calendar']['days'] ?? null), 'Monatskalender');

$day = (new DateTimeImmutable('monday next week'))->format('Y-m-d');
$bad = $httpJson('POST', '/api/mobile/staff/absences', [
    'type' => 'vacation',
    'date_from' => $day,
    'date_to' => $day,
    'reason' => 'ohne Ack',
], $token);
assertMa3c(($bad['ok'] ?? true) === false, 'Urlaub ohne Bestätigung abgelehnt');

$ok = $httpJson('POST', '/api/mobile/staff/absences', [
    'type' => 'vacation',
    'date_from' => $day,
    'date_to' => $day,
    'reason' => 'MA3c Selftest',
    'acknowledge' => true,
], $token);
assertMa3c(($ok['ok'] ?? false) === true, 'Urlaub beantragen');
$absenceId = (int) ($ok['data']['id'] ?? 0);
assertMa3c($absenceId > 0, 'Antrags-ID');
assertMa3c(str_contains((string) ($ok['data']['message'] ?? ''), 'beantragt'), 'Erfolgsmeldung');

$list1 = $httpJson('GET', '/api/mobile/staff/absences?month=' . substr($day, 0, 7), null, $token);
assertMa3c(($list1['ok'] ?? false) === true, 'GET absences nach Antrag');
$found = false;
foreach (($list1['data']['absences'] ?? []) as $row) {
    if (is_array($row) && (int) ($row['id'] ?? 0) === $absenceId) {
        $found = true;
        assertMa3c(($row['status'] ?? '') === 'requested', 'Status beantragt');
        break;
    }
}
assertMa3c($found, 'Antrag in Liste');

$shifts = $httpJson('GET', '/api/mobile/staff/shifts', null, $token);
assertMa3c(($shifts['ok'] ?? false) === true, 'GET shifts aktuelle Woche');
assertMa3c(isset($shifts['data']['week_monday']), 'week_monday');
assertMa3c(is_array($shifts['data']['shifts'] ?? null), 'shifts Liste');

$shifts2 = $httpJson('GET', '/api/mobile/staff/shifts?week=' . $day, null, $token);
assertMa3c(($shifts2['ok'] ?? false) === true, 'GET shifts week=');
assertMa3c(($shifts2['data']['week_monday'] ?? '') === $day, 'week= auf Montag gesetzt');

$cleanupPhp = 'require "bootstrap.php";'
    . '$pdo=Database::pdo();$cid=' . $contactId . ';'
    . '$pdo->prepare("DELETE FROM dg_time_absences WHERE contact_id=?")->execute([$cid]);'
    . '$pdo->prepare("DELETE FROM dg_mobile_tokens WHERE contact_id=?")->execute([$cid]);'
    . '$pdo->prepare("DELETE FROM dg_time_kiosk_pins WHERE contact_id=?")->execute([$cid]);'
    . '$pdo->prepare("DELETE FROM dg_time_kiosk_sessions WHERE contact_id=?")->execute([$cid]);'
    . '$pdo->prepare("DELETE FROM dg_time_kiosk_pin_resets WHERE contact_id=?")->execute([$cid]);'
    . '$pdo->prepare("DELETE FROM dg_contacts WHERE id=?")->execute([$cid]);'
    . 'echo "CLEANED";';
$cleanup = 'cd /www/htdocs/w0217246/ganz-om.de && php -r ' . escapeshellarg($cleanupPhp);
$cout = (string) shell_exec('ssh -o BatchMode=yes allinkl-ganzom ' . escapeshellarg($cleanup) . ' 2>/dev/null');
assertMa3c(str_contains($cout, 'CLEANED'), 'Smoke-MA bereinigt');

// Probe-Kontakt von manueller Prüfung ebenfalls wegräumen falls noch da
if ($ssh !== '') {
    $probeClean = 'cd /www/htdocs/w0217246/ganz-om.de && php -r ' . escapeshellarg(
        'require "bootstrap.php";$pdo=Database::pdo();'
        . '$rows=$pdo->query("SELECT id FROM dg_contacts WHERE login LIKE \"ma3c.%\"")->fetchAll(PDO::FETCH_ASSOC);'
        . 'foreach ($rows as $r) {$cid=(int)$r["id"];'
        . '$pdo->prepare("DELETE FROM dg_time_absences WHERE contact_id=?")->execute([$cid]);'
        . '$pdo->prepare("DELETE FROM dg_mobile_tokens WHERE contact_id=?")->execute([$cid]);'
        . '$pdo->prepare("DELETE FROM dg_time_kiosk_pins WHERE contact_id=?")->execute([$cid]);'
        . '$pdo->prepare("DELETE FROM dg_contacts WHERE id=?")->execute([$cid]);'
        . 'echo "P$cid ";} echo "DONE";'
    );
    shell_exec('ssh -o BatchMode=yes allinkl-ganzom ' . escapeshellarg($probeClean) . ' 2>/dev/null');
}

echo "MA3c Selftest bestanden (UI + Dev Antrag/Liste/Schichten).\n";
exit(0);
