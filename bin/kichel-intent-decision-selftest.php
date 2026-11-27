<?php

declare(strict_types=1);

/**
 * KI0 — Entscheidungsdoc vorhanden: vorerst kein LLM-Parser; Öffnungs-Kriterien.
 * Usage: php bin/kichel-intent-decision-selftest.php
 */

if (!defined('DG_ROOT')) {
    define('DG_ROOT', dirname(__DIR__));
}

$plan = (string) file_get_contents(DG_ROOT . '/docs/KICHEL-RAG-TRAINING-PLAN.md');
$fail = static function (string $msg): never {
    fwrite(STDERR, "FAIL: {$msg}\n");
    exit(1);
};

if (!str_contains($plan, '### 5.1 KI0')) {
    $fail('missing KI0 section 5.1');
}
if (!str_contains($plan, '**KI0** ✅')) {
    $fail('KI0 not marked done in table');
}
if (!str_contains($plan, 'Vorerst kein LLM-Intent-Parser')) {
    $fail('missing binding decision text');
}
foreach (['Kosten', 'Latenz', 'Fehlerrate', 'Kill-Switch', 'KI2'] as $need) {
    if (!str_contains($plan, $need)) {
        $fail("decision criteria missing: {$need}");
    }
}
if (!str_contains($plan, 'KI1–3 geparkt')) {
    $fail('KI1–3 park status missing');
}
// No accidental "KI1 ✅" without decision reopen
if (preg_match('/\|\s*\*\*KI1\*\*\s*✅/', $plan) === 1) {
    $fail('KI1 must stay parked (not ✅) after KI0 no');
}

$intent = (string) file_get_contents(DG_ROOT . '/src/Kichel/KichelIntent.php');
if (!str_contains($intent, 'function detect')) {
    $fail('PHP Intent detect still required');
}
if (str_contains($intent, 'Ollama') || str_contains($intent, 'ollama')) {
    $fail('KichelIntent must stay PHP-only after KI0');
}

fwrite(STDOUT, "OK KI0 intent decision selftest\n");
exit(0);
