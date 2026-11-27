<?php
declare(strict_types=1);

/**
 * MA6a: TODOS.md + IQ-STROM §D3 Mobile-Haken + Parent Rest-Plan.
 *
 * Usage: php bin/mobile-ma6a-todos-selftest.php
 */

function assertMa6a(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "FEHLER: {$message}\n");
        exit(1);
    }
    echo "OK: {$message}\n";
}

$root = dirname(__DIR__);
$todos = $root . '/docs/TODOS.md';
$iq = $root . '/docs/IQ-STROM-HETZNER-TODO.md';
$plan = $root . '/docs/MOBILE-APPS-REST-PLAN.md';

assertMa6a(is_file($todos) && is_file($iq) && is_file($plan), 'Pflicht-Docs');

$todosSrc = (string) file_get_contents($todos);
$iqSrc = (string) file_get_contents($iq);
$planSrc = (string) file_get_contents($plan);

assertMa6a(preg_match('/\|\s*\*\*MA6a\*\*.*\|\s*\[x\]/u', $planSrc) === 1, 'Plan MA6a abgehakt');
assertMa6a(str_contains($planSrc, 'MA6a ✅') || str_contains($planSrc, '**MA6a ✅**'), 'Historie MA6a');

assertMa6a(
    preg_match('/\[\s*x\s*\].*Mobile Apps \(Flutter\)/u', $todosSrc) === 1
        || preg_match('/- \[x\] \*\*Mobile Apps \(Flutter\)/u', $todosSrc) === 1,
    'TODOS Mobile Apps abgehakt'
);
assertMa6a(str_contains($todosSrc, 'MA6a ✅'), 'TODOS nennt MA6a ✅');
assertMa6a(str_contains($todosSrc, 'MA6b'), 'TODOS zeigt MA6b als Folge');

assertMa6a(
    preg_match('/- \[x\] \*\*Mobile Apps\*\*/u', $iqSrc) === 1,
    'IQ-STROM §D3 Mobile Apps [x]'
);
assertMa6a(str_contains($iqSrc, 'MA0–MA5') || str_contains($iqSrc, 'MA0-MA5') || str_contains($iqSrc, 'MA6a'), 'IQ §D3 Rest-Stand');
assertMa6a(str_contains($iqSrc, 'MOBILE-APPS-REST-PLAN'), 'IQ verweist Rest-Plan');

echo "MA6a Selftest bestanden.\n";
exit(0);
}