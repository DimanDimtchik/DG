<?php
declare(strict_types=1);

/**
 * MA4b: Flutter analyze + Widget-/Unit-Tests Mindeststand je App.
 *
 * Usage: php bin/mobile-flutter-minstand-selftest.php
 */

function assertMa4b(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "FEHLER: {$message}\n");
        exit(1);
    }
    echo "OK: {$message}\n";
}

$root = dirname(__DIR__);
$plan = $root . '/docs/MOBILE-APPS-REST-PLAN.md';
$sh = $root . '/bin/mobile-flutter-minstand.sh';

assertMa4b(is_file($plan), 'MOBILE-APPS-REST-PLAN.md');
assertMa4b(is_file($sh), 'mobile-flutter-minstand.sh');

$planSrc = (string) file_get_contents($plan);
assertMa4b(preg_match('/\|\s*\*\*MA4b\*\*.*\|\s*\[x\]/u', $planSrc) === 1, 'Plan MA4b abgehakt');
assertMa4b(str_contains($planSrc, 'mobile-flutter-minstand'), 'Skript in Verweisen');

foreach (['kalender', 'mitarbeiter'] as $app) {
    $widget = $root . "/apps/{$app}/test/widget_test.dart";
    $api = $root . "/apps/{$app}/test/api_message_test.dart";
    assertMa4b(is_file($widget), "{$app} widget_test.dart");
    assertMa4b(is_file($api), "{$app} api_message_test.dart");
    $w = (string) file_get_contents($widget);
    $a = (string) file_get_contents($api);
    assertMa4b(str_contains($w, 'testWidgets'), "{$app} Widget-Tests");
    assertMa4b(str_contains($w, 'LoginPage'), "{$app} LoginPage-Test");
    assertMa4b(str_contains($a, 'apiIsOk'), "{$app} api_message Unit-Test");
    assertMa4b(str_contains($a, 'normalizeBaseUrl'), "{$app} api_base_url Unit-Test");
}

$shSrc = (string) file_get_contents($sh);
assertMa4b(str_contains($shSrc, 'run_app kalender'), 'Smoke: kalender');
assertMa4b(str_contains($shSrc, 'run_app mitarbeiter'), 'Smoke: mitarbeiter');
assertMa4b(str_contains($shSrc, 'flutter analyze'), 'Smoke: analyze');
assertMa4b(str_contains($shSrc, 'flutter test'), 'Smoke: test');

$flutter = trim((string) shell_exec('command -v flutter 2>/dev/null || true'));
if ($flutter === '' && is_file(getenv('HOME') . '/sdk/flutter/bin/flutter')) {
    $flutter = getenv('HOME') . '/sdk/flutter/bin/flutter';
}
if ($flutter !== '') {
    $cmd = 'export PATH=' . escapeshellarg(dirname($flutter) . ':' . getenv('PATH'))
        . '; bash ' . escapeshellarg($sh);
    passthru($cmd, $rc);
    assertMa4b($rc === 0, 'Flutter-Mindeststand-Skript grün');
} else {
    echo "SKIP: flutter SDK nicht installiert — UI-Guards trotzdem ok.\n";
}

echo "MA4b Selftest bestanden.\n";
exit(0);
