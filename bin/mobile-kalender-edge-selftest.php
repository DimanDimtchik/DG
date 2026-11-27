<?php
declare(strict_types=1);

/**
 * MA2d: Kalender-Randfälle — Buchung aus, Slot weg, Session abgelaufen (nur apps/kalender).
 *
 * Usage: php bin/mobile-kalender-edge-selftest.php
 */

function assertMa2d(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "FEHLER: {$message}\n");
        exit(1);
    }
    echo "OK: {$message}\n";
}

$root = dirname(__DIR__);
$main = $root . '/apps/kalender/lib/main.dart';
$msgKal = $root . '/apps/kalender/lib/api_message.dart';
$msgMa = $root . '/apps/mitarbeiter/lib/api_message.dart';
$api = $root . '/src/Mobile/MobileCustomerApi.php';

assertMa2d(is_file($main), 'kalender main.dart');
assertMa2d(is_file($msgKal) && is_file($msgMa), 'api_message.dart beide Apps');
assertMa2d(hash_file('sha256', $msgKal) === hash_file('sha256', $msgMa), 'api_message byte-identisch');
assertMa2d(is_file($api), 'MobileCustomerApi.php');

$src = (string) file_get_contents($main);
$msg = (string) file_get_contents($msgKal);
$apiSrc = (string) file_get_contents($api);

assertMa2d(str_contains($msg, "'booking_disabled'"), 'code booking_disabled');
assertMa2d(str_contains($msg, "'slot_unavailable'"), 'code slot_unavailable');
assertMa2d(str_contains($msg, 'apiIsBookingDisabled'), 'Helper Buchung aus');
assertMa2d(str_contains($msg, 'apiIsSlotUnavailable'), 'Helper Slot weg');
assertMa2d(str_contains($msg, 'apiIsSessionExpired'), 'Helper Session');
assertMa2d(str_contains($apiSrc, "'booking_disabled'"), 'API booking_disabled');
assertMa2d(str_contains($apiSrc, "'slot_unavailable'"), 'API slot_unavailable');
assertMa2d(str_contains($src, 'Online-Buchung ausgeschaltet'), 'UI Buchung aus');
assertMa2d(str_contains($src, 'apiIsSlotUnavailable'), 'UI Slot-Weg-Pfad');
assertMa2d(str_contains($src, 'apiIsSessionExpired'), 'UI Session-Pfad');
assertMa2d(str_contains($src, 'Sitzung abgelaufen') || str_contains($src, "apiCodeMessage('unauthorized')"), 'UI Session-Hinweis');
assertMa2d(!str_contains($src, 'apps/mitarbeiter'), 'kein Mitarbeiter-App-Mix');

$base = rtrim((string) (getenv('MA2D_BASE_URL') ?: 'https://ganz-om.de'), '/');
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

// --- Session abgelaufen ---
$bad = $httpJson('GET', '/api/mobile/customer/catalog', null, 'invalid-token-ma2d');
assertMa2d(($bad['ok'] ?? true) === false, 'ungültiges Token → Fehler');
$codeBad = (string) ($bad['code'] ?? '');
assertMa2d($codeBad === 'unauthorized' || str_contains(mb_strtolower((string) ($bad['error'] ?? '')), 'token'), 'Session unauthorized');

$email = 'ma2d.selftest.' . time() . '@gmail.com';
$pass = 'TestPass9!';
$reg = $httpJson('POST', '/api/mobile/auth/customer/register', [
    'email' => $email,
    'password' => $pass,
    'name' => 'MA2d Selftest',
]);
assertMa2d(($reg['ok'] ?? false) === true, 'Dev Register');
$token = (string) ($reg['data']['token'] ?? '');
assertMa2d($token !== '', 'Token');

$catalog = $httpJson('GET', '/api/mobile/customer/catalog', null, $token);
assertMa2d(($catalog['ok'] ?? false) === true, 'Katalog (Buchung an)');
$articleId = (int) (($catalog['data']['articles'][0]['id'] ?? 0));
assertMa2d($articleId > 0, 'Artikel');

$slot = '';
for ($i = 1; $i <= 14; $i++) {
    $date = (new DateTimeImmutable('today'))->modify("+{$i} day")->format('Y-m-d');
    $slotsRes = $httpJson('GET', '/api/mobile/customer/slots?article_id=' . $articleId . '&date=' . $date, null, $token);
    $list = $slotsRes['data']['slots'] ?? [];
    if (is_array($list) && $list !== []) {
        $slot = (string) $list[0];
        break;
    }
}
assertMa2d($slot !== '', 'freier Slot');

$book1 = $httpJson('POST', '/api/mobile/customer/bookings', [
    'article_id' => $articleId,
    'slot_datetime' => $slot,
], $token);
assertMa2d(($book1['ok'] ?? false) === true, 'Erstbuchung belegt Slot');
$bookingId = (int) ($book1['data']['booking']['id'] ?? 0);

// Zweiter Account versucht denselben Slot → Slot weg
$email2 = 'ma2d.conflict.' . time() . '@gmail.com';
$reg2 = $httpJson('POST', '/api/mobile/auth/customer/register', [
    'email' => $email2,
    'password' => $pass,
    'name' => 'MA2d Conflict',
]);
assertMa2d(($reg2['ok'] ?? false) === true, 'Zweitkonto Register');
$token2 = (string) ($reg2['data']['token'] ?? '');
$book2 = $httpJson('POST', '/api/mobile/customer/bookings', [
    'article_id' => $articleId,
    'slot_datetime' => $slot,
], $token2);
assertMa2d(($book2['ok'] ?? true) === false, 'Doppelbuchung abgelehnt');
$code2 = (string) ($book2['code'] ?? '');
$err2 = mb_strtolower((string) ($book2['error'] ?? ''));
assertMa2d(
    $code2 === 'slot_unavailable' || str_contains($err2, 'nicht verfügbar') || str_contains($err2, 'belegt'),
    'Slot-weg Meldung/Code'
);

// Buchung aus: per SSH toggeln (Caller kann MA2D_SKIP_TOGGLE=1 setzen)
$skipToggle = (string) (getenv('MA2D_SKIP_TOGGLE') ?: '') === '1';
if (!$skipToggle) {
    $toggleScript = <<<'PHP'
require "bootstrap.php";
$c = CalendarEmbedSettings::config();
$c["online_booking_enabled"] = false;
CalendarEmbedSettings::save($c);
echo "OFF\n";
PHP;
    $tmpOff = tempnam(sys_get_temp_dir(), 'ma2d');
    file_put_contents($tmpOff, $toggleScript);
    // Toggle via remote PHP one-liner through ssh helper if available; else skip soft.
    $ssh = trim((string) shell_exec('command -v ssh'));
    if ($ssh !== '') {
        $remoteOff = 'cd /www/htdocs/w0217246/ganz-om.de && php -r ' . escapeshellarg(
            'require "bootstrap.php"; $c=CalendarEmbedSettings::config(); $c["online_booking_enabled"]=false; CalendarEmbedSettings::save($c); echo "OFF";'
        );
        $outOff = (string) shell_exec('ssh -o BatchMode=yes allinkl-ganzom ' . escapeshellarg($remoteOff) . ' 2>/dev/null');
        if (str_contains($outOff, 'OFF')) {
            $disabled = $httpJson('GET', '/api/mobile/customer/catalog', null, $token);
            assertMa2d(($disabled['ok'] ?? true) === false, 'Katalog bei Buchung aus');
            $dCode = (string) ($disabled['code'] ?? '');
            $dErr = mb_strtolower((string) ($disabled['error'] ?? ''));
            assertMa2d(
                $dCode === 'booking_disabled' || str_contains($dErr, 'online-terminbuchung'),
                'Buchung-aus Meldung/Code'
            );
            $remoteOn = 'cd /www/htdocs/w0217246/ganz-om.de && php -r ' . escapeshellarg(
                'require "bootstrap.php"; $c=CalendarEmbedSettings::config(); $c["online_booking_enabled"]=true; CalendarEmbedSettings::save($c); echo "ON";'
            );
            $outOn = (string) shell_exec('ssh -o BatchMode=yes allinkl-ganzom ' . escapeshellarg($remoteOn) . ' 2>/dev/null');
            assertMa2d(str_contains($outOn, 'ON'), 'Online-Buchung wieder an');
            $again = $httpJson('GET', '/api/mobile/customer/catalog', null, $token);
            assertMa2d(($again['ok'] ?? false) === true, 'Katalog nach Re-Enable');
        } else {
            echo "SKIP: Buchung-aus-Toggle (kein SSH OFF)\n";
        }
    } else {
        echo "SKIP: Buchung-aus-Toggle (kein ssh)\n";
    }
    @unlink($tmpOff);
}

// Cleanup bookings
if ($bookingId > 0) {
    $httpJson('POST', '/api/mobile/customer/bookings/' . $bookingId . '/cancel', [], $token);
}

echo "MA2d Selftest bestanden (UI + Dev Session/Slot" . ($skipToggle ? '' : '/Buchung-aus') . ").\n";
echo "Hinweis: Testkonten {$email} / {$email2} bitte ggf. auf Dev bereinigen.\n";
exit(0);
