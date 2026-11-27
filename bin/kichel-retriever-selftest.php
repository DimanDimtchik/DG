<?php
declare(strict_types=1);

/**
 * KR3 — Retriever-Pfade, Budget, rag_enabled.
 * Usage: php bin/kichel-retriever-selftest.php
 */
if (!defined('DG_ROOT')) {
    define('DG_ROOT', dirname(__DIR__));
}
require_once DG_ROOT . '/src/autoload.php';

$failed = 0;
$adminRole = (string) App::config('roles.admin', 'administrator');
$user = new User(1, 'admin', 'Administrator', '', [$adminRole], true);

function kr3_assert(bool $ok, string $label, mixed $detail = null): void
{
    global $failed;
    if (!$ok) {
        $failed++;
    }
    $suffix = $detail !== null
        ? ' → ' . json_encode($detail, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        : '';
    echo ($ok ? 'OK  ' : 'FAIL') . '  ' . $label . $suffix . "\n";
}

// Budget
$big = [];
for ($i = 0; $i < 10; $i++) {
    $big[] = KichelRagFact::make(
        KichelRagFact::TYPE_TOPIC,
        'T' . $i,
        str_repeat('x', 200),
        'CRM-Hilfe',
        'topic:t' . $i
    );
}
$budgeted = KichelRetriever::applyBudget($big);
kr3_assert(count($budgeted) <= KichelRetriever::maxFacts(), 'budget respects max facts', [
    'count' => count($budgeted),
    'max' => KichelRetriever::maxFacts(),
]);

$chars = 0;
foreach ($budgeted as $f) {
    $chars += mb_strlen($f['title'] . "\n" . $f['body'] . "\n" . $f['source_label'], 'UTF-8');
}
kr3_assert($chars <= KichelRetriever::maxChars() + 50, 'budget roughly within max chars', $chars);

// Pflichtpfad Legal vor freier Suche
$legal = KichelRetriever::retrieve($user, 'Wo sind die Pflichtseiten Impressum?');
kr3_assert(($legal['path'] ?? '') === 'legal', 'legal path before free search', $legal['path'] ?? null);

// Money calculate
$money = KichelRetriever::retrieve($user, 'Skonto 3 % von 1000 Euro');
kr3_assert(($money['path'] ?? '') === 'money', 'money path on calculate intent', $money['path'] ?? null);
kr3_assert(($money['facts'][0]['type'] ?? '') === KichelRagFact::TYPE_CALCULATION, 'money facts typed');

// Freie Suche → candidates + KR2 facts
$ust = KichelRetriever::retrieve($user, 'Wo trage ich die USt-ID ein?');
kr3_assert(($ust['path'] ?? '') === 'candidates', 'ust-id candidates path', $ust['path'] ?? null);
$refs = array_map(static fn (array $f): string => (string) ($f['source_ref'] ?? ''), $ust['facts'] ?? []);
$hasUstFact = false;
foreach ($refs as $ref) {
    if (str_contains($ref, 'ust') || str_contains($ref, 'topic:')) {
        $hasUstFact = true;
        break;
    }
}
kr3_assert($hasUstFact && ($ust['facts'] ?? []) !== [], 'ust-id yields rag facts', $refs);

// Assistant RAG path (ohne Ollama = Fallback)
$answer = KichelAssistant::answer($user, 'Wo trage ich die USt-ID ein?');
if (KichelRetriever::isRagEnabled()) {
    kr3_assert(!empty($answer['rag_used']), 'assistant rag_used when enabled', $answer['rag_used'] ?? null);
    kr3_assert(str_contains((string) ($answer['answer'] ?? ''), 'Quelle:') || str_contains((string) ($answer['answer'] ?? ''), 'Firmendaten'), 'rag answer has content', $answer['answer'] ?? '');
    kr3_assert(isset($answer['rag_facts']) && is_array($answer['rag_facts']), 'rag_facts present');
} else {
    echo "SKIP  assistant rag_used (rag_enabled=false)\n";
}

kr3_assert(is_bool(KichelRetriever::isRagEnabled()), 'rag_enabled readable');

echo $failed === 0 ? "ALL OK\n" : "FAILED {$failed}\n";
exit($failed === 0 ? 0 : 1);
