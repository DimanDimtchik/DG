<?php
declare(strict_types=1);

/**
 * ES6b: Mitarbeiter-App UI Skizze Unterschrift.
 *
 * Usage: php bin/mobile-staff-signature-ui-selftest.php
 */

function assertEs6b(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "FEHLER: {$message}\n");
        exit(1);
    }
    echo "OK: {$message}\n";
}

$root = dirname(__DIR__);
$page = $root . '/apps/mitarbeiter/lib/signature_page.dart';
$main = $root . '/apps/mitarbeiter/lib/main.dart';
assertEs6b(is_file($page), 'signature_page.dart vorhanden (Mini-Paket)');
assertEs6b(is_file($main), 'main.dart vorhanden');

$pageSrc = (string) file_get_contents($page);
$mainSrc = (string) file_get_contents($main);

assertEs6b(str_contains($pageSrc, 'class SignaturePage'), 'SignaturePage Klasse');
assertEs6b(str_contains($pageSrc, "/api/mobile/staff/signature"), 'UI ruft Staff-Signature-API');
assertEs6b(str_contains($pageSrc, 'png_base64'), 'Zeichnung als png_base64');
assertEs6b(str_contains($pageSrc, 'signature_file'), 'Upload-Feld signature_file');
assertEs6b(str_contains($pageSrc, 'Zeichnung speichern'), 'Speichern-Button Text');
assertEs6b(str_contains($pageSrc, 'PNG/WebP'), 'Upload-Hinweis PNG/WebP');
assertEs6b(
    str_contains($pageSrc, 'qualifizierte Signatur') || str_contains($pageSrc, 'einfache elektronische'),
    'Soft-Hinweis kein QES'
);

assertEs6b(str_contains($mainSrc, "import 'signature_page.dart'"), 'main importiert signature_page');
assertEs6b(str_contains($mainSrc, 'SignaturePage(client:'), 'Profil öffnet SignaturePage');
assertEs6b(str_contains($mainSrc, 'Unterschrift aufnehmen'), 'Profil-CTA Unterschrift');

echo "ES6b Selftest bestanden.\n";
exit(0);
