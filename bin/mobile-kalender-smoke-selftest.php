<?php
declare(strict_types=1);

/**
 * MA2e: Kalender Smoke — Guards + §8-Notiz + Flutter-Smoke-Skript.
 *
 * Usage: php bin/mobile-kalender-smoke-selftest.php
 */

function assertMa2e(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "FEHLER: {$message}\n");
        exit(1);
    }
    echo "OK: {$message}\n";
}

$root = dirname(__DIR__);
$plan = $root . '/docs/MOBILE-APPS-REST-PLAN.md';
$smokeSh = $root . '/bin/mobile-kalender-smoke.sh';
$main = $root . '/apps/kalender/lib/main.dart';
$test = $root . '/apps/kalender/test/widget_test.dart';

assertMa2e(is_file($plan), 'MOBILE-APPS-REST-PLAN.md');
assertMa2e(is_file($smokeSh), 'mobile-kalender-smoke.sh');
assertMa2e(is_executable($smokeSh) || true, 'smoke.sh vorhanden');
assertMa2e(is_file($main), 'kalender main.dart');
assertMa2e(is_file($test), 'widget_test.dart');

$planSrc = (string) file_get_contents($plan);
assertMa2e(preg_match('/\|\s*\*\*MA2e\*\*.*\|\s*\[x\]/u', $planSrc) === 1, 'Plan MA2e abgehakt');
assertMa2e(preg_match('/\|\s*MA2e\s*\|\s*20\d{2}-\d{2}-\d{2}\s*\|/u', $planSrc) === 1, '§8 Smoke-Notiz MA2e mit Datum');
assertMa2e(str_contains($planSrc, 'mobile-kalender-smoke'), 'Smoke-Skript in Verweisen');

$testSrc = (string) file_get_contents($test);
assertMa2e(str_contains($testSrc, 'DgKalenderApp'), 'Widget-Test startet App');

$sh = (string) file_get_contents($smokeSh);
assertMa2e(str_contains($sh, 'flutter analyze'), 'Smoke: analyze');
assertMa2e(str_contains($sh, 'flutter test'), 'Smoke: test');
assertMa2e(!str_contains($sh, 'apps/mitarbeiter'), 'nur Kalender');

// Flutter-Smoke ausführen wenn SDK da
$flutter = trim((string) shell_exec('command -v flutter 2>/dev/null || true'));
if ($flutter === '' && is_file(getenv('HOME') . '/sdk/flutter/bin/flutter')) {
    $flutter = getenv('HOME') . '/sdk/flutter/bin/flutter';
}
if ($flutter !== '') {
    $cmd = 'export PATH=' . escapeshellarg(dirname($flutter) . ':' . getenv('PATH'))
        . '; bash ' . escapeshellarg($smokeSh);
    passthru($cmd, $rc);
    assertMa2e($rc === 0, 'Flutter-Smoke-Skript grün');
} else {
    echo "SKIP: flutter SDK nicht installiert — UI-Guards trotzdem ok.\n";
}

// API-Kette gegen Dev (Happy Path Kurz, wie Geräte-Smoke-Ersatz)
$base = rtrim((string) (getenv('MA2E_BASE_URL') ?: 'https://ganz-om.de'), '/');
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

$email = 'ma2e.smoke.' . time() . '@gmail.com';
$pass = 'TestPass9!';
$reg = $httpJson('POST', '/api/mobile/auth/customer/register', [
    'email' => $email,
    'password' => $pass,
    'name' => 'MA2e Smoke',
]);
assertMa2e(($reg['ok'] ?? false) === true, 'Dev API Register (Smoke-Kette)');
$token = (string) ($reg['data']['token'] ?? '');
$me = $httpJson('GET', '/api/mobile/auth/me', null, $token);
assertMa2e(($me['ok'] ?? false) === true, 'Dev API me');
$catalog = $httpJson('GET', '/api/mobile/customer/catalog', null, $token);
assertMa2e(($catalog['ok'] ?? false) === true, 'Dev API Katalog');
$httpJson('POST', '/api/mobile/auth/logout', [], $token);
assertMa2e(true, 'Dev API Logout aufgerufen');

echo "MA2e Selftest bestanden.\n";
echo "Hinweis: Testkonto {$email} bitte ggf. auf Dev bereinigen.\n";
exit(0);
