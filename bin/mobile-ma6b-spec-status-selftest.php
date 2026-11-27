<?php
declare(strict_types=1);

/**
 * MA6b: MOBILE-APPS-PLAN.md Statuszeile Rest MA0–MA6.
 *
 * Usage: php bin/mobile-ma6b-spec-status-selftest.php
 */

function assertMa6b(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "FEHLER: {$message}\n");
        exit(1);
    }
    echo "OK: {$message}\n";
}

$root = dirname(__DIR__);
$spec = $root . '/docs/MOBILE-APPS-PLAN.md';
$plan = $root . '/docs/MOBILE-APPS-REST-PLAN.md';
$todos = $root . '/docs/TODOS.md';

assertMa6b(is_file($spec) && is_file($plan), 'Spec + Rest-Plan');

$specSrc = (string) file_get_contents($spec);
$planSrc = (string) file_get_contents($plan);
$todosSrc = (string) file_get_contents($todos);

assertMa6b(
    str_contains($specSrc, 'Rest MA0–MA6') && str_contains($specSrc, 'MA0–MA6 ✅'),
    'Spec: Rest MA0–MA6 ✅'
);
assertMa6b(str_contains($specSrc, 'abgeschlossen'), 'Spec-Stand: Serie abgeschlossen');
assertMa6b(preg_match('/\|\s*\*\*MA6b\*\*.*\|\s*\[x\]/u', $planSrc) === 1, 'Plan MA6b abgehakt');
assertMa6b(str_contains($planSrc, 'MA6b ✅') || str_contains($planSrc, 'Serie fertig'), 'Historie MA6b');
assertMa6b(str_contains($todosSrc, 'MA0–MA6 ✅'), 'TODOS MA0–MA6 ✅');

echo "MA6b Selftest bestanden.\n";
exit(0);
