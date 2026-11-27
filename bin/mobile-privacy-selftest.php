<?php
declare(strict_types=1);

/**
 * MA5b: Privacy-Text / Datenfluss dokumentiert (Login, Token, keine GPS).
 *
 * Usage: php bin/mobile-privacy-selftest.php
 */

function assertMa5b(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "FEHLER: {$message}\n");
        exit(1);
    }
    echo "OK: {$message}\n";
}

$root = dirname(__DIR__);
$doc = $root . '/docs/MOBILE-APPS-PRIVACY.md';
$plan = $root . '/docs/MOBILE-APPS-REST-PLAN.md';
$spec = $root . '/docs/MOBILE-APPS-PLAN.md';

assertMa5b(is_file($doc), 'MOBILE-APPS-PRIVACY.md');
$src = (string) file_get_contents($doc);

assertMa5b(str_contains($src, 'Login') || str_contains($src, 'Anmeldung'), 'Doc: Anmeldung');
assertMa5b(str_contains($src, 'Bearer') || str_contains($src, 'Token'), 'Doc: Token');
assertMa5b(str_contains($src, 'SharedPreferences') || str_contains($src, 'lokal'), 'Doc: lokale Speicherung');
assertMa5b(str_contains($src, 'GPS') || str_contains($src, 'Standort'), 'Doc: GPS/Standort');
assertMa5b(
    str_contains($src, 'Nein') && (str_contains($src, 'GPS') || str_contains($src, 'Standort')),
    'Doc: kein GPS'
);
assertMa5b(str_contains($src, '/api/mobile'), 'Doc: API-Pfad');
assertMa5b(str_contains($src, 'Logout') || str_contains($src, 'Abmelden'), 'Doc: Logout');
assertMa5b(str_contains($src, 'Kalender') && str_contains($src, 'Mitarbeiter'), 'Doc: beide Apps');
assertMa5b(!preg_match('/kontur-cosmetics|iqstrom|info@ganz-om/i', $src), 'kein Tenant-Hardcode');

$planSrc = (string) file_get_contents($plan);
assertMa5b(preg_match('/\|\s*\*\*MA5b\*\*.*\|\s*\[x\]/u', $planSrc) === 1, 'Plan MA5b abgehakt');
assertMa5b(str_contains($planSrc, 'MOBILE-APPS-PRIVACY'), 'Plan verweist Privacy-Doc');
assertMa5b(str_contains($planSrc, 'mobile-privacy-selftest'), 'Selftest in Verweisen');

$specSrc = (string) file_get_contents($spec);
assertMa5b(str_contains($specSrc, 'MOBILE-APPS-PRIVACY'), 'MOBILE-APPS-PLAN verweist Privacy');

// Manifeste: keine Location-Permission
foreach (['kalender', 'mitarbeiter'] as $app) {
    $manifest = $root . '/apps/' . $app . '/android/app/src/main/AndroidManifest.xml';
    assertMa5b(is_file($manifest), "{$app} AndroidManifest");
    $m = (string) file_get_contents($manifest);
    assertMa5b(
        !str_contains($m, 'ACCESS_FINE_LOCATION')
            && !str_contains($m, 'ACCESS_COARSE_LOCATION')
            && !str_contains($m, 'ACCESS_BACKGROUND_LOCATION'),
        "{$app}: keine GPS-Permission"
    );
}

echo "MA5b Selftest bestanden.\n";
exit(0);
