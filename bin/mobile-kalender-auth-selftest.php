<?php
declare(strict_types=1);

/**
 * MA2a: Kalender Login/Register UI + API-Fehlervertrag (Dev optional).
 *
 * Usage:
 *   php bin/mobile-kalender-auth-selftest.php
 *   MA2A_BASE_URL=https://ganz-om.de php bin/mobile-kalender-auth-selftest.php
 */

function assertMa2a(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "FEHLER: {$message}\n");
        exit(1);
    }
    echo "OK: {$message}\n";
}

$root = dirname(__DIR__);
$main = $root . '/apps/kalender/lib/main.dart';
assertMa2a(is_file($main), 'kalender main.dart');
$src = (string) file_get_contents($main);

assertMa2a(str_contains($src, 'class LoginPage'), 'LoginPage');
assertMa2a(str_contains($src, '_validateLocal'), 'Client-Validierung');
assertMa2a(str_contains($src, 'Bitte E-Mail eingeben.'), 'Meldung E-Mail fehlt');
assertMa2a(str_contains($src, 'Bitte Passwort eingeben.'), 'Meldung Passwort fehlt');
assertMa2a(str_contains($src, 'Passwort mindestens'), 'Meldung Passwort-Länge');
assertMa2a(str_contains($src, '/api/mobile/auth/customer/register'), 'Register-Endpunkt');
assertMa2a(str_contains($src, '/api/mobile/auth/customer/login'), 'Login-Endpunkt');
assertMa2a(str_contains($src, 'apiUserMessage('), 'API-Fehler über apiUserMessage');
assertMa2a(str_contains($src, 'Registrierung fehlgeschlagen.'), 'Register-Fallback');
assertMa2a(str_contains($src, 'Anmeldung fehlgeschlagen.'), 'Login-Fallback');
assertMa2a(str_contains($src, 'mobileSaveToken('), 'Token speichern');
assertMa2a(str_contains($src, "data['token']"), 'Token aus data');
assertMa2a(!str_contains($src, 'apps/mitarbeiter'), 'kein Mitarbeiter-Mix');

$base = rtrim((string) (getenv('MA2A_BASE_URL') ?: 'https://ganz-om.de'), '/');
$probe = static function (string $path, array $body) use ($base): array {
    $payload = json_encode($body, JSON_UNESCAPED_UNICODE);
    $ctx = stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => "Content-Type: application/json\r\nAccept: application/json\r\n",
            'content' => is_string($payload) ? $payload : '{}',
            'timeout' => 20,
            'ignore_errors' => true,
        ],
    ]);
    $raw = @file_get_contents($base . $path, false, $ctx);
    $code = 0;
    if (isset($http_response_header) && is_array($http_response_header)) {
        foreach ($http_response_header as $line) {
            if (preg_match('#^HTTP/\S+\s+(\d+)#', $line, $m)) {
                $code = (int) $m[1];
                break;
            }
        }
    }
    if (!is_string($raw) || $raw === '') {
        return ['http' => $code, 'ok' => false, 'error' => 'leere Antwort', 'code' => 'http'];
    }
    $json = json_decode($raw, true);

    return is_array($json) ? $json + ['http' => $code] : ['http' => $code, 'ok' => false, 'error' => 'kein JSON', 'raw' => $raw];
};

$loginBad = $probe('/api/mobile/auth/customer/login', [
    'email' => 'not-an-email',
    'password' => 'x',
]);
assertMa2a(($loginBad['ok'] ?? true) === false, 'Dev Login ungültig → ok=false');
assertMa2a(is_string($loginBad['error'] ?? null) && trim((string) $loginBad['error']) !== '', 'Dev Login hat error-Text');
assertMa2a(($loginBad['code'] ?? '') !== '', 'Dev Login hat code');

$regShort = $probe('/api/mobile/auth/customer/register', [
    'email' => 'ok@gmail.com',
    'password' => 'short',
]);
assertMa2a(($regShort['ok'] ?? true) === false, 'Dev Register kurzes Passwort → ok=false');
assertMa2a(
    str_contains((string) ($regShort['error'] ?? ''), '8'),
    'Dev Register meldet min. 8 Zeichen'
);

$regBadMail = $probe('/api/mobile/auth/customer/register', [
    'email' => 'bad',
    'password' => 'TestPass9!',
]);
assertMa2a(($regBadMail['ok'] ?? true) === false, 'Dev Register ungültige E-Mail → ok=false');
assertMa2a(
    str_contains(mb_strtolower((string) ($regBadMail['error'] ?? '')), 'e-mail')
        || str_contains(mb_strtolower((string) ($regBadMail['error'] ?? '')), 'email'),
    'Dev Register E-Mail-Fehlertext'
);

echo "MA2a Selftest bestanden (UI + Dev Fehlerpfade gegen {$base}).\n";
echo "Hinweis: Happy-Path Register/Login wurde manuell gegen Dev geprüft; Konten danach bereinigt.\n";
exit(0);
