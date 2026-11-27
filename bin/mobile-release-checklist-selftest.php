<?php
declare(strict_types=1);

/**
 * MA5c: Release-Build-Checkliste (Doc only) — Android AAB / iOS Archive.
 *
 * Usage: php bin/mobile-release-checklist-selftest.php
 */

function assertMa5c(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "FEHLER: {$message}\n");
        exit(1);
    }
    echo "OK: {$message}\n";
}

$root = dirname(__DIR__);
$doc = $root . '/docs/MOBILE-APPS-RELEASE-CHECKLIST.md';
$plan = $root . '/docs/MOBILE-APPS-REST-PLAN.md';
$downloads = $root . '/downloads/README.md';

assertMa5c(is_file($doc), 'MOBILE-APPS-RELEASE-CHECKLIST.md');
$src = (string) file_get_contents($doc);

assertMa5c(str_contains($src, 'Doc only') || str_contains($src, 'Doc only'), 'Doc only');
assertMa5c(str_contains($src, 'appbundle') || str_contains($src, 'AAB'), 'Android AAB');
assertMa5c(str_contains($src, 'flutter build apk'), 'Android APK intern');
assertMa5c(str_contains($src, 'Archive') || str_contains($src, 'ipa') || str_contains($src, 'IPA'), 'iOS Archive/IPA');
assertMa5c(str_contains($src, 'signing') || str_contains($src, 'Signing') || str_contains($src, 'Keystore') || str_contains($src, 'keystore'), 'Signing');
assertMa5c(str_contains($src, 'de.ganzsoft.dgkalender'), 'Kalender-App-ID');
assertMa5c(str_contains($src, 'de.ganzsoft.dgmitarbeiter'), 'Mitarbeiter-App-ID');
assertMa5c(str_contains($src, 'MA5d') || str_contains($src, 'TestFlight') || str_contains($src, 'Kein'), 'kein Store-Upload hier');
assertMa5c(str_contains($src, 'debug'), 'Hinweis Debug-Signing Ist');
assertMa5c(!preg_match('/kontur-cosmetics|iqstrom|info@ganz-om/i', $src), 'kein Tenant-Hardcode');

$planSrc = (string) file_get_contents($plan);
assertMa5c(preg_match('/\|\s*\*\*MA5c\*\*.*\|\s*\[x\]/u', $planSrc) === 1, 'Plan MA5c abgehakt');
assertMa5c(str_contains($planSrc, 'MOBILE-APPS-RELEASE-CHECKLIST'), 'Plan verweist Checkliste');
assertMa5c(str_contains($planSrc, 'mobile-release-checklist-selftest'), 'Selftest in Verweisen');

assertMa5c(is_file($downloads), 'downloads/README.md');
$dl = (string) file_get_contents($downloads);
assertMa5c(str_contains($dl, 'dg-kalender.apk') && str_contains($dl, 'dg-mitarbeiter.apk'), 'interne APK-Namen');

echo "MA5c Selftest bestanden.\n";
exit(0);
