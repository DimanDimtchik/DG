<?php
declare(strict_types=1);

/**
 * MA5d: Optional TestFlight / Internal Track — Guard für bewusstes Überspringen.
 *
 * Usage: php bin/mobile-ma5d-skip-selftest.php
 */

function assertMa5d(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "FEHLER: {$message}\n");
        exit(1);
    }
    echo "OK: {$message}\n";
}

$root = dirname(__DIR__);
$plan = $root . '/docs/MOBILE-APPS-REST-PLAN.md';
$checklist = $root . '/docs/MOBILE-APPS-RELEASE-CHECKLIST.md';

assertMa5d(is_file($plan), 'MOBILE-APPS-REST-PLAN.md');
$planSrc = (string) file_get_contents($plan);
assertMa5d(preg_match('/\|\s*\*\*MA5d\*\*.*\|\s*\[x\]/u', $planSrc) === 1, 'Plan MA5d abgehakt');
assertMa5d(str_contains($planSrc, 'übersprungen') || str_contains($planSrc, '⏭'), 'MA5d als übersprungen dokumentiert');
assertMa5d(str_contains($planSrc, '§5.2') || str_contains($planSrc, '5.2'), '§5.2 Voraussetzungen');
assertMa5d(str_contains($planSrc, 'kein Upload-Auftrag') || str_contains($planSrc, 'kein expliziter'), 'Grund: kein Upload-Auftrag');

$cl = (string) file_get_contents($checklist);
assertMa5d(str_contains($cl, 'MA5d'), 'Release-Checkliste erwähnt MA5d');

// Kein iOS-Projekt → TestFlight ohnehin blockiert
assertMa5d(!is_dir($root . '/apps/kalender/ios'), 'Kalender ohne ios/ (TestFlight noch nicht möglich)');
assertMa5d(!is_dir($root . '/apps/mitarbeiter/ios'), 'Mitarbeiter ohne ios/ (TestFlight noch nicht möglich)');

echo "MA5d Selftest bestanden (Skip dokumentiert).\n";
exit(0);
