<?php
declare(strict_types=1);

/**
 * MA1b: Token speichern / Logout / 401-Refresh in beiden Flutter-Apps.
 *
 * Usage: php bin/mobile-api-auth-selftest.php
 */

function assertMa1b(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "FEHLER: {$message}\n");
        exit(1);
    }
    echo "OK: {$message}\n";
}

$root = dirname(__DIR__);
$kal = $root . '/apps/kalender/lib/api_auth.dart';
$ma = $root . '/apps/mitarbeiter/lib/api_auth.dart';
$kalMain = $root . '/apps/kalender/lib/main.dart';
$maMain = $root . '/apps/mitarbeiter/lib/main.dart';

assertMa1b(is_file($kal) && is_file($ma), 'api_auth.dart in beiden Apps');
assertMa1b(
    hash_file('sha256', $kal) === hash_file('sha256', $ma),
    'api_auth.dart byte-identisch'
);

$src = (string) file_get_contents($kal);
assertMa1b(str_contains($src, 'mobileSaveToken'), 'mobileSaveToken');
assertMa1b(str_contains($src, 'mobileClearToken'), 'mobileClearToken');
assertMa1b(str_contains($src, 'mobileLoadToken'), 'mobileLoadToken');
assertMa1b(str_contains($src, 'mobileTryRefreshToken'), 'mobileTryRefreshToken');
assertMa1b(str_contains($src, 'mobileJsonWithRefresh'), 'mobileJsonWithRefresh');
assertMa1b(str_contains($src, 'apiIsUnauthorized'), 'apiIsUnauthorized');
assertMa1b(str_contains($src, "/api/mobile/auth/") || str_contains($src, 'apiIsAuthPath'), 'Auth-Pfad erkannt');
assertMa1b(str_contains($src, 'mobileTokenPrefsKey'), 'Prefs-Key token');

$kalMainSrc = (string) file_get_contents($kalMain);
$maMainSrc = (string) file_get_contents($maMain);

assertMa1b(str_contains($kalMainSrc, "import 'api_auth.dart'"), 'kalender import api_auth');
assertMa1b(str_contains($maMainSrc, "import 'api_auth.dart'"), 'mitarbeiter import api_auth');
assertMa1b(str_contains($kalMainSrc, 'mobileJsonWithRefresh'), 'kalender ApiClient Refresh');
assertMa1b(str_contains($maMainSrc, 'mobileJsonWithRefresh'), 'mitarbeiter ApiClient Refresh');
assertMa1b(str_contains($kalMainSrc, 'onSessionExpired'), 'kalender Session-Callback');
assertMa1b(str_contains($maMainSrc, 'onSessionExpired'), 'mitarbeiter Session-Callback');
assertMa1b(str_contains($kalMainSrc, 'mobileSaveToken('), 'kalender speichert Token');
assertMa1b(str_contains($maMainSrc, 'mobileSaveToken('), 'mitarbeiter speichert Token');
assertMa1b(str_contains($kalMainSrc, 'mobileClearToken('), 'kalender Logout clear');
assertMa1b(str_contains($maMainSrc, 'mobileClearToken('), 'mitarbeiter Logout clear');
assertMa1b(str_contains($maMainSrc, 'mobileTryRefreshToken'), 'mitarbeiter Binary-Refresh');
assertMa1b(str_contains($kalMainSrc, '/api/mobile/auth/refresh'), 'kalender refresh-URL');
assertMa1b(str_contains($maMainSrc, '/api/mobile/auth/refresh'), 'mitarbeiter refresh-URL');
assertMa1b(str_contains($kalMainSrc, 'Sitzung abgelaufen'), 'kalender Hinweis Sitzung');
assertMa1b(str_contains($maMainSrc, 'Sitzung abgelaufen'), 'mitarbeiter Hinweis Sitzung');

echo "MA1b Selftest bestanden.\n";
exit(0);
