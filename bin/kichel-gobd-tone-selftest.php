<?php

declare(strict_types=1);

/**
 * KH3 — GoBD-Ton: keine „einfach so buchen“-Vorschläge; Knowledge-Scan.
 * Usage: php bin/kichel-gobd-tone-selftest.php
 */

if (!defined('DG_ROOT')) {
    define('DG_ROOT', dirname(__DIR__));
}
require_once DG_ROOT . '/src/autoload.php';

$failed = 0;
$assert = static function (bool $ok, string $label, mixed $detail = null) use (&$failed): void {
    if (!$ok) {
        $failed++;
    }
    $suffix = $detail !== null
        ? ' → ' . json_encode($detail, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        : '';
    echo ($ok ? 'OK  ' : 'FAIL') . '  ' . $label . $suffix . "\n";
};

$unsafeSamples = [
    'Das kannst du einfach auf Konto 8400 buchen.',
    'Verbuche das als Aufwand.',
    'Einfach so buchen und fertig.',
    'Du solltest das auf 4400 buchen.',
    'Kontiere einfach als Einnahme.',
    'Ohne Beleg buchen geht auch.',
];
foreach ($unsafeSamples as $sample) {
    $assert(KichelGobdTone::isUnsafe($sample), 'detects unsafe', $sample);
    $filtered = KichelGobdTone::filterAnswer($sample);
    $assert(
        $filtered === KichelGobdTone::SAFE_REDIRECT && !KichelGobdTone::isUnsafe($filtered),
        'filter replaces unsafe',
        mb_substr($filtered, 0, 60)
    );
}

$safeSamples = [
    'Angebot und Rechnung legst du unter Buchhaltung → Belege an.',
    'Salden und Kontoauszüge je Konto findest du unter Buchhaltung → Kontenübersicht — dort ein Konto anklicken für alle Buchungen.',
    'Fachliche Kontierung bleibt eure/PHP-Logik. Orientierungshilfe, keine Rechtsberatung.',
    'Skonto 3 % von 1.000,00 €: Du zahlst 970,00 €.',
];
foreach ($safeSamples as $sample) {
    $assert(!KichelGobdTone::isUnsafe($sample), 'allows safe CRM tone', $sample);
    $assert(KichelGobdTone::filterAnswer($sample) === $sample, 'filter keeps safe');
}

// Statische Korpora dürfen keine unsicheren Muster enthalten
$corpusFiles = [
    DG_ROOT . '/src/Kichel/data/knowledge.php',
    DG_ROOT . '/src/Kichel/data/tax-shorttexts.php',
    DG_ROOT . '/src/Kichel/data/fields.php',
];
foreach ($corpusFiles as $file) {
    $raw = (string) file_get_contents($file);
    $assert(!KichelGobdTone::isUnsafe($raw), 'corpus clean: ' . basename($file), KichelGobdTone::matchedPatterns($raw));
}

$promptSrc = (string) file_get_contents(DG_ROOT . '/src/Kichel/KichelOllamaClient.php');
$assert(str_contains($promptSrc, 'Buchungs- oder Kontierungsvorschläge'), 'Ollama prompt has GoBD booking ban');
$assert(str_contains($promptSrc, 'KichelGobdTone::isUnsafe'), 'phrase rejects unsafe Ollama text');
$assert(str_contains($promptSrc, 'KichelGobdTone::filterAnswer'), 'compose filters answer');

$assistantSrc = (string) file_get_contents(DG_ROOT . '/src/Kichel/KichelAssistant.php');
$assert(str_contains($assistantSrc, 'applyGobdTone'), 'Assistant applies GoBD tone');

$adminRole = (string) App::config('roles.admin', 'administrator');
$user = new User(1, 'admin', 'Administrator', '', [$adminRole], true);
$ans = KichelAssistant::answer($user, 'Wo finde ich Belege?');
$assert(
    ($ans['answer'] ?? '') !== '' && !KichelGobdTone::isUnsafe((string) $ans['answer']),
    'live Belege answer is GoBD-safe'
);

// Simuliere riskante Compose-Ausgabe über filter
$assert(
    KichelGobdTone::filterAnswer('Bitte einfach auf Konto 1200 buchen.') === KichelGobdTone::SAFE_REDIRECT,
    'konto number advice blocked'
);

if ($failed > 0) {
    fwrite(STDERR, "FAILED: {$failed}\n");
    exit(1);
}
echo "OK KH3 GoBD tone selftest\n";
exit(0);
