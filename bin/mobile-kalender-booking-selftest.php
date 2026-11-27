<?php
declare(strict_types=1);

/**
 * MA2b: Kalender Katalog → Slot → Buchen (Happy Path, nur apps/kalender).
 *
 * Usage: php bin/mobile-kalender-booking-selftest.php
 */

function assertMa2b(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "FEHLER: {$message}\n");
        exit(1);
    }
    echo "OK: {$message}\n";
}

$root = dirname(__DIR__);
$main = $root . '/apps/kalender/lib/main.dart';
assertMa2b(is_file($main), 'kalender main.dart');
$src = (string) file_get_contents($main);

assertMa2b(str_contains($src, 'class BookSlotPage'), 'BookSlotPage');
assertMa2b(str_contains($src, '/api/mobile/customer/catalog'), 'lädt Katalog');
assertMa2b(str_contains($src, '/api/mobile/customer/slots'), 'lädt Slots');
assertMa2b(str_contains($src, '/api/mobile/customer/bookings'), 'bucht Termine');
assertMa2b(str_contains($src, '_confirmAndBook'), 'Bestätigung vor Buchen');
assertMa2b(str_contains($src, 'employee_id'), 'employee_id optional');
assertMa2b(str_contains($src, 'booking_code'), 'zeigt Buchungscode');
assertMa2b(str_contains($src, 'staff: _staff'), 'Staff an BookSlotPage');
assertMa2b(str_contains($src, 'DropdownButtonFormField'), 'Mitarbeiter-Auswahl bei mehreren');
assertMa2b(str_contains($src, 'Jetzt buchen'), 'Bestätigungs-CTA');
assertMa2b(!str_contains($src, 'apps/mitarbeiter'), 'kein Mitarbeiter-App-Mix');

$base = rtrim((string) (getenv('MA2B_BASE_URL') ?: 'https://ganz-om.de'), '/');
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

    return is_array($json) ? $json : ['ok' => false, 'error' => 'kein JSON', 'raw' => $raw];
};

$email = 'ma2b.selftest.' . time() . '@gmail.com';
$pass = 'TestPass9!';
$reg = $httpJson('POST', '/api/mobile/auth/customer/register', [
    'email' => $email,
    'password' => $pass,
    'name' => 'MA2b Selftest',
]);
assertMa2b(($reg['ok'] ?? false) === true, 'Dev Register ok');
$token = (string) (($reg['data']['token'] ?? ''));
assertMa2b($token !== '', 'Dev Token');

$catalog = $httpJson('GET', '/api/mobile/customer/catalog', null, $token);
assertMa2b(($catalog['ok'] ?? false) === true, 'Dev Katalog ok (Online-Buchung an)');
$articles = $catalog['data']['articles'] ?? [];
assertMa2b(is_array($articles) && $articles !== [], 'Dev hat buchbare Leistungen');
$articleId = (int) ($articles[0]['id'] ?? 0);
assertMa2b($articleId > 0, 'Artikel-ID');

$slot = '';
$dateUsed = '';
for ($i = 1; $i <= 14; $i++) {
    $date = (new DateTimeImmutable('today'))->modify("+{$i} day")->format('Y-m-d');
    $slotsRes = $httpJson('GET', '/api/mobile/customer/slots?article_id=' . $articleId . '&date=' . $date, null, $token);
    $list = $slotsRes['data']['slots'] ?? [];
    if (is_array($list) && $list !== []) {
        $slot = (string) $list[0];
        $dateUsed = $date;
        break;
    }
}
assertMa2b($slot !== '', 'Dev freier Slot in 14 Tagen');

$book = $httpJson('POST', '/api/mobile/customer/bookings', [
    'article_id' => $articleId,
    'slot_datetime' => $slot,
], $token);
assertMa2b(($book['ok'] ?? false) === true, 'Dev Buchung ok');
$bookingId = (int) ($book['data']['booking']['id'] ?? 0);
$code = (string) ($book['data']['booking']['booking_code'] ?? '');
assertMa2b($bookingId > 0, 'Buchungs-ID');
assertMa2b($code !== '', 'Buchungscode');

$cancel = $httpJson('POST', '/api/mobile/customer/bookings/' . $bookingId . '/cancel', [], $token);
assertMa2b(($cancel['ok'] ?? false) === true, 'Dev Storno nach Smoke');

echo "MA2b Selftest bestanden (UI + Dev Happy Path {$dateUsed} {$slot}).\n";
echo "Hinweis: Testkonto {$email} bitte ggf. auf Dev bereinigen.\n";
exit(0);
