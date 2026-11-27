<?php
declare(strict_types=1);

/**
 * MA1c: Shared CRM-Basis-URL Validierung/Speichern (kein QR-Plugin).
 *
 * Usage: php bin/mobile-api-base-url-selftest.php
 */

function assertMa1c(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "FEHLER: {$message}\n");
        exit(1);
    }
    echo "OK: {$message}\n";
}

$root = dirname(__DIR__);
$kal = $root . '/apps/kalender/lib/api_base_url.dart';
$ma = $root . '/apps/mitarbeiter/lib/api_base_url.dart';
$kalMain = $root . '/apps/kalender/lib/main.dart';
$maMain = $root . '/apps/mitarbeiter/lib/main.dart';

assertMa1c(is_file($kal) && is_file($ma), 'api_base_url.dart in beiden Apps');
assertMa1c(
    hash_file('sha256', $kal) === hash_file('sha256', $ma),
    'api_base_url.dart byte-identisch'
);

$src = (string) file_get_contents($kal);
assertMa1c(str_contains($src, 'String normalizeBaseUrl('), 'normalizeBaseUrl');
assertMa1c(str_contains($src, 'String resolveCrmBaseUrl('), 'resolveCrmBaseUrl');
assertMa1c(str_contains($src, 'mobileSaveBaseUrl'), 'mobileSaveBaseUrl');
assertMa1c(str_contains($src, 'mobileLoadBaseUrl'), 'mobileLoadBaseUrl');
assertMa1c(str_contains($src, "queryParameters['base']"), 'liest Query base=');
assertMa1c(str_contains($src, "startsWith('dg-')"), 'Deep-Link-Scheme dg-');
assertMa1c(str_contains($src, 'Kein QR-Kamera-Plugin') || str_contains($src, 'kein Scanner'), 'Hinweis kein QR-Plugin');

$kalMainSrc = (string) file_get_contents($kalMain);
$maMainSrc = (string) file_get_contents($maMain);

assertMa1c(str_contains($kalMainSrc, "import 'api_base_url.dart'"), 'kalender import');
assertMa1c(str_contains($maMainSrc, "import 'api_base_url.dart'"), 'mitarbeiter import');
assertMa1c(str_contains($kalMainSrc, 'resolveCrmBaseUrl('), 'kalender resolve');
assertMa1c(str_contains($maMainSrc, 'resolveCrmBaseUrl('), 'mitarbeiter resolve');
assertMa1c(str_contains($kalMainSrc, 'mobileSaveBaseUrl('), 'kalender save');
assertMa1c(str_contains($maMainSrc, 'mobileSaveBaseUrl('), 'mitarbeiter save');
assertMa1c(str_contains($kalMainSrc, 'mobileLoadBaseUrl('), 'kalender load');
assertMa1c(str_contains($maMainSrc, 'mobileLoadBaseUrl('), 'mitarbeiter load');
assertMa1c(
    !preg_match('/^String normalizeBaseUrl\(/m', $kalMainSrc),
    'kalender main ohne lokale normalizeBaseUrl'
);
assertMa1c(
    !preg_match('/^String normalizeBaseUrl\(/m', $maMainSrc),
    'mitarbeiter main ohne lokale normalizeBaseUrl'
);
assertMa1c(str_contains($kalMainSrc, 'Verbindungs-Link') || str_contains($kalMainSrc, 'App verbinden'), 'kalender Hinweis Link');
assertMa1c(str_contains($maMainSrc, 'Verbindungs-Link') || str_contains($maMainSrc, 'App verbinden'), 'mitarbeiter Hinweis Link');
assertMa1c(!str_contains($kalMainSrc, 'mobile_scanner'), 'kein QR-Plugin kalender');
assertMa1c(!str_contains($maMainSrc, 'mobile_scanner'), 'kein QR-Plugin mitarbeiter');

echo "MA1c Selftest bestanden.\n";
exit(0);
