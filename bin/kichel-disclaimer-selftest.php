<?php

declare(strict_types=1);

/**
 * KH0 — Hinweistext vorhanden, kurz, ohne Plan-Codes.
 * Usage: php bin/kichel-disclaimer-selftest.php
 */

if (!defined('DG_ROOT')) {
    define('DG_ROOT', dirname(__DIR__));
}
require_once DG_ROOT . '/src/autoload.php';

$fail = static function (string $msg): never {
    fwrite(STDERR, "FAIL: {$msg}\n");
    exit(1);
};

$text = KichelDisclaimer::text();
if ($text === '') {
    $fail('empty disclaimer');
}
if (mb_strlen($text) > 120) {
    $fail('disclaimer too long (>120)');
}
foreach (['KH0', 'KR8', 'Gate', 'payroll_'] as $banned) {
    if (str_contains($text, $banned)) {
        $fail("plan/internal jargon in disclaimer: {$banned}");
    }
}
foreach (['Rechtsberatung', 'Quellen'] as $need) {
    if (!str_contains($text, $need)) {
        $fail("missing key phrase: {$need}");
    }
}

$widget = (string) file_get_contents(DG_ROOT . '/views/partials/kichel-widget.php');
$super = (string) file_get_contents(DG_ROOT . '/views/modules/superkihel.php');
$partial = (string) file_get_contents(DG_ROOT . '/views/partials/kichel-disclaimer.php');

if (!str_contains($partial, 'dg-kichel-disclaimer') || !str_contains($partial, 'KichelDisclaimer')) {
    $fail('partial missing disclaimer markup/class');
}
if (!str_contains($widget, 'kichel-disclaimer')) {
    $fail('widget does not include disclaimer partial');
}
if (!str_contains($super, 'kichel-disclaimer')) {
    $fail('superkihel does not include disclaimer partial');
}
if (!str_contains($widget, 'disclaimer')) {
    $fail('widget cfg should expose disclaimer');
}

fwrite(STDOUT, "OK KH0 disclaimer selftest\n");
exit(0);
