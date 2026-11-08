<?php
declare(strict_types=1);

/**
 * ES8a: UI-Texte Unterschrift — Zweck, kein QES, kein Löschen.
 *
 * Usage: php bin/contact-signature-ui-text-selftest.php
 */

function assertEs8a(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "FEHLER: {$message}\n");
        exit(1);
    }
    echo "OK: {$message}\n";
}

$root = dirname(__DIR__);
$partial = $root . '/views/partials/contact-signature-history.php';
$writer = $root . '/views/modules/dokumentenverfasser-form.php';
$app = $root . '/apps/mitarbeiter/lib/signature_page.dart';

assertEs8a(is_file($partial), 'contact-signature-history.php');
assertEs8a(is_file($writer), 'dokumentenverfasser-form.php');
assertEs8a(is_file($app), 'signature_page.dart');

$p = (string) file_get_contents($partial);
$w = (string) file_get_contents($writer);
$a = (string) file_get_contents($app);

assertEs8a(str_contains($p, 'Zweck:'), 'CRM: Zweck genannt');
assertEs8a(
    str_contains($p, 'Briefe und E-Mails') || str_contains($p, 'Briefe und E-Mails'),
    'CRM: Zweck Brief/Mail'
);
assertEs8a(
    str_contains($p, 'qualifizierte elektronische Signatur') || str_contains($p, 'QES'),
    'CRM: kein-QES-Hinweis'
);
assertEs8a(
    str_contains($p, 'Schriftform'),
    'CRM: Schriftform-Hinweis'
);
assertEs8a(
    str_contains($p, 'nicht gelöscht') || str_contains($p, 'kein Löschen') || str_contains($p, 'Löschen ist nicht'),
    'CRM: kein-Löschen-Hinweis'
);
assertEs8a(
    !preg_match('/name=["\'][^"\']*delete|contact_signature_delete|Löschen<\/button>/i', $p),
    'CRM: kein Löschen-Button in Signatur-Partial'
);

assertEs8a(str_contains($w, 'keine Auswahl fremder Signaturen'), 'Writer: keine fremde Auswahl');
assertEs8a(str_contains($w, 'kein QES') || str_contains($w, 'QES'), 'Writer: QES-Hinweis');

assertEs8a(str_contains($a, 'Zweck:'), 'App: Zweck genannt');
assertEs8a(str_contains($a, 'QES') || str_contains($a, 'qualifizierte'), 'App: kein-QES-Hinweis');
assertEs8a(
    str_contains($a, 'nicht gelöscht') || str_contains($a, 'Historie'),
    'App: Historie/kein Löschen'
);

echo "ES8a Selftest bestanden.\n";
exit(0);
