<?php
declare(strict_types=1);

/**
 * MA2c: Kalender Meine Termine — Liste, Storno, Umbuchen (nur apps/kalender).
 *
 * Usage: php bin/mobile-kalender-reschedule-selftest.php
 */

function assertMa2c(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "FEHLER: {$message}\n");
        exit(1);
    }
    echo "OK: {$message}\n";
}

$root = dirname(__DIR__);
$main = $root . '/apps/kalender/lib/main.dart';
assertMa2c(is_file($main), 'kalender main.dart');
$src = (string) file_get_contents($main);

assertMa2c(str_contains($src, '_openReschedule'), 'Umbuchen-Einstieg');
assertMa2c(str_contains($src, 'rescheduleBookingId'), 'BookSlotPage Reschedule-Modus');
assertMa2c(str_contains($src, '/reschedule'), 'API Umbuchen-Pfad');
assertMa2c(str_contains($src, '_cancelBooking'), 'Storno');
assertMa2c(str_contains($src, 'Umbuchen'), 'UI-Text Umbuchen');
assertMa2c(str_contains($src, 'Stornieren'), 'UI-Text Stornieren');
assertMa2c(str_contains($src, '_articleTitleForBooking'), 'Leistungstitel in Liste');
assertMa2c(str_contains($src, '_isActiveBooking'), 'aktive vs. stornierte Termine');
assertMa2c(!str_contains($src, 'apps/mitarbeiter'), 'kein Mitarbeiter-App-Mix');

$base = rtrim((string) (getenv('MA2C_BASE_URL') ?: 'https://ganz-om.de'), '/');
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

$email = 'ma2c.selftest.' . time() . '@gmail.com';
$pass = 'TestPass9!';
$reg = $httpJson('POST', '/api/mobile/auth/customer/register', [
    'email' => $email,
    'password' => $pass,
    'name' => 'MA2c Selftest',
]);
assertMa2c(($reg['ok'] ?? false) === true, 'Dev Register');
$token = (string) ($reg['data']['token'] ?? '');
assertMa2c($token !== '', 'Token');

$catalog = $httpJson('GET', '/api/mobile/customer/catalog', null, $token);
assertMa2c(($catalog['ok'] ?? false) === true, 'Katalog');
$articleId = (int) (($catalog['data']['articles'][0]['id'] ?? 0));
assertMa2c($articleId > 0, 'Artikel');

$slotA = '';
$slotB = '';
for ($i = 1; $i <= 14; $i++) {
    $date = (new DateTimeImmutable('today'))->modify("+{$i} day")->format('Y-m-d');
    $slotsRes = $httpJson('GET', '/api/mobile/customer/slots?article_id=' . $articleId . '&date=' . $date, null, $token);
    $list = $slotsRes['data']['slots'] ?? [];
    if (!is_array($list) || count($list) < 2) {
        continue;
    }
    $slotA = (string) $list[0];
    $slotB = (string) $list[1];
    break;
}
assertMa2c($slotA !== '' && $slotB !== '' && $slotA !== $slotB, 'zwei freie Slots');

$book = $httpJson('POST', '/api/mobile/customer/bookings', [
    'article_id' => $articleId,
    'slot_datetime' => $slotA,
], $token);
assertMa2c(($book['ok'] ?? false) === true, 'Erstbuchung');
$bookingId = (int) ($book['data']['booking']['id'] ?? 0);
assertMa2c($bookingId > 0, 'Buchungs-ID');

$list1 = $httpJson('GET', '/api/mobile/customer/bookings', null, $token);
assertMa2c(($list1['ok'] ?? false) === true, 'Terminliste');
assertMa2c(is_array($list1['data']['bookings'] ?? null) && ($list1['data']['bookings'] !== []), 'Liste nicht leer');

$resched = $httpJson('POST', '/api/mobile/customer/bookings/' . $bookingId . '/reschedule', [
    'slot_datetime' => $slotB,
], $token);
assertMa2c(($resched['ok'] ?? false) === true, 'Umbuchen API');
$newSlot = (string) ($resched['data']['booking']['slot_datetime'] ?? '');
assertMa2c($newSlot === $slotB, 'neuer Slot gespeichert');

$cancel = $httpJson('POST', '/api/mobile/customer/bookings/' . $bookingId . '/cancel', [], $token);
assertMa2c(($cancel['ok'] ?? false) === true, 'Storno nach Umbuchen');
$status = (string) ($cancel['data']['booking']['status'] ?? '');
assertMa2c(str_contains(mb_strtolower($status), 'storn'), 'Status storniert');

echo "MA2c Selftest bestanden (UI + Dev Liste/Umbuchen/Storno).\n";
echo "Hinweis: Testkonto {$email} bitte ggf. auf Dev bereinigen.\n";
exit(0);
