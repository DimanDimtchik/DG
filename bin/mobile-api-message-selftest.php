<?php
declare(strict_types=1);

/**
 * MA1a: Shared Mobile-API Fehlerformat-Helfer in beiden Flutter-Apps.
 *
 * Usage: php bin/mobile-api-message-selftest.php
 */

function assertMa1a(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "FEHLER: {$message}\n");
        exit(1);
    }
    echo "OK: {$message}\n";
}

$root = dirname(__DIR__);
$kal = $root . '/apps/kalender/lib/api_message.dart';
$ma = $root . '/apps/mitarbeiter/lib/api_message.dart';
$kalMain = $root . '/apps/kalender/lib/main.dart';
$maMain = $root . '/apps/mitarbeiter/lib/main.dart';
$sig = $root . '/apps/mitarbeiter/lib/signature_page.dart';

assertMa1a(is_file($kal), 'kalender api_message.dart');
assertMa1a(is_file($ma), 'mitarbeiter api_message.dart');
assertMa1a(
    hash_file('sha256', $kal) === hash_file('sha256', $ma),
    'api_message.dart byte-identisch in beiden Apps'
);

$helper = (string) file_get_contents($kal);
assertMa1a(str_contains($helper, 'String apiUserMessage('), 'apiUserMessage');
assertMa1a(str_contains($helper, 'bool apiIsOk('), 'apiIsOk');
assertMa1a(str_contains($helper, 'apiParseJsonBody('), 'apiParseJsonBody');
assertMa1a(str_contains($helper, 'apiCodeMessage('), 'apiCodeMessage');
assertMa1a(str_contains($helper, "'unauthorized'"), 'code unauthorized gemappt');
assertMa1a(str_contains($helper, "'invalid_credentials'"), 'code invalid_credentials gemappt');
assertMa1a(str_contains($helper, 'bad_response'), 'code bad_response');

$kalMainSrc = (string) file_get_contents($kalMain);
$maMainSrc = (string) file_get_contents($maMain);
$sigSrc = (string) file_get_contents($sig);

assertMa1a(str_contains($kalMainSrc, "import 'api_message.dart'"), 'kalender main import');
assertMa1a(str_contains($maMainSrc, "import 'api_message.dart'"), 'mitarbeiter main import');
assertMa1a(str_contains($sigSrc, "import 'api_message.dart'"), 'signature_page import');

$kalAuthSrc = (string) file_get_contents($root . '/apps/kalender/lib/api_auth.dart');
$maAuthSrc = (string) file_get_contents($root . '/apps/mitarbeiter/lib/api_auth.dart');
assertMa1a(
    str_contains($kalMainSrc, 'apiParseJsonBody(') || str_contains($kalAuthSrc, 'apiParseJsonBody('),
    'kalender ApiClient/Auth parse'
);
assertMa1a(
    str_contains($maMainSrc, 'apiParseJsonBody(') || str_contains($maAuthSrc, 'apiParseJsonBody('),
    'mitarbeiter ApiClient/Auth parse'
);

assertMa1a(str_contains($kalMainSrc, 'apiUserMessage('), 'kalender zeigt apiUserMessage');
assertMa1a(str_contains($maMainSrc, 'apiUserMessage('), 'mitarbeiter zeigt apiUserMessage');
assertMa1a(str_contains($sigSrc, 'apiUserMessage('), 'signature_page zeigt apiUserMessage');

assertMa1a(str_contains($kalMainSrc, 'apiIsOk('), 'kalender apiIsOk');
assertMa1a(str_contains($maMainSrc, 'apiIsOk('), 'mitarbeiter apiIsOk');
assertMa1a(str_contains($sigSrc, 'apiIsOk('), 'signature_page apiIsOk');

// Keine Roh-Zugriffe auf res['error'] in UI-Dateien (Helfer darf intern)
foreach ([
    'kalender main' => $kalMainSrc,
    'mitarbeiter main' => $maMainSrc,
    'signature_page' => $sigSrc,
] as $label => $src) {
    assertMa1a(
        !preg_match("/\\[['\\\"]error['\\\"]\\]/", $src),
        "{$label}: kein direktes ['error'] (nur apiUserMessage)"
    );
}

echo "MA1a Selftest bestanden.\n";
exit(0);
