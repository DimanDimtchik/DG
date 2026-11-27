<?php
declare(strict_types=1);

/**
 * KR4 — Corpus-Chunks + Steuerkurztexte.
 * Usage: php bin/kichel-corpus-selftest.php
 */
if (!defined('DG_ROOT')) {
    define('DG_ROOT', dirname(__DIR__));
}
require_once DG_ROOT . '/src/autoload.php';

$failed = 0;
$adminRole = (string) App::config('roles.admin', 'administrator');
$user = new User(1, 'admin', 'Administrator', '', [$adminRole], true);

function kr4_assert(bool $ok, string $label, mixed $detail = null): void
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

// KR4a — Knowledge → CRM-Hilfe
$topics = KichelKnowledge::matchTopics(KichelKnowledge::tokenize('USt-ID'), 3);
kr4_assert($topics !== [], 'knowledge match ust-id');
$topicChunk = KichelCorpus::chunkFromTopic($topics[0]['topic'], (int) $topics[0]['score']);
kr4_assert(
    $topicChunk !== null
    && ($topicChunk['source_label'] ?? '') === 'CRM-Hilfe'
    && str_starts_with((string) ($topicChunk['source_ref'] ?? ''), 'topic:'),
    'chunkFromTopic CRM-Hilfe',
    $topicChunk
);

// KR4b — Nav / Feld einheitlich
$navChunk = KichelCorpus::chunkFromCandidate([
    'kind' => 'navigation',
    'score' => 10,
    'title' => 'Firmendaten',
    'body' => 'Bereich: Grundlagen',
    'href' => '/app?page=einstellungen&tab=firmendaten',
    'dedupe' => '/app?page=einstellungen&tab=firmendaten',
]);
kr4_assert(($navChunk['source_label'] ?? '') === 'CRM-Menü', 'nav chunk label', $navChunk['source_label'] ?? null);

$fieldChunk = KichelCorpus::chunkFromCandidate([
    'kind' => 'field_help',
    'score' => 5,
    'title' => 'Feldhilfe',
    'body' => 'Beispiel',
    'href' => '/app?page=einstellungen',
    'dedupe' => 'field:x',
]);
kr4_assert(($fieldChunk['source_label'] ?? '') === 'CRM-Feldhilfe', 'field chunk label');

$mediaChunk = KichelCorpus::chunkFromCandidate([
    'kind' => 'media',
    'score' => 6,
    'title' => 'Video',
    'body' => 'Clip',
    'href' => '/app?page=akademie',
    'dedupe' => 'media:1',
]);
kr4_assert(($mediaChunk['source_label'] ?? '') === 'CRM-Medien', 'media chunk label');

// KR4c — Steuerkurztexte
kr4_assert(count(KichelCorpus::taxShorttexts()) >= 8, 'at least 8 tax shorttexts', count(KichelCorpus::taxShorttexts()));

$taxHits = KichelCorpus::matchTaxShorttexts(KichelKnowledge::tokenize('Was bedeutet Kleinunternehmer?'), 3);
kr4_assert($taxHits !== [] && (($taxHits[0]['entry']['id'] ?? '') === 'tax-u19'), 'kleinunternehmer → tax-u19', $taxHits[0]['entry']['id'] ?? null);

$istHits = KichelCorpus::matchTaxShorttexts(KichelKnowledge::tokenize('Ist-Versteuerung einstellen'), 3);
kr4_assert($istHits !== [] && (($istHits[0]['entry']['id'] ?? '') === 'tax-ist-soll'), 'ist-versteuerung → tax-ist-soll', $istHits[0]['entry']['id'] ?? null);

$cand = KichelCorpus::taxEntryToCandidate($taxHits[0]['entry'], (int) $taxHits[0]['score']);
$taxFact = KichelCorpus::chunkFromCandidate($cand);
kr4_assert(
    $taxFact !== null
    && ($taxFact['type'] ?? '') === KichelRagFact::TYPE_TAX_NOTE
    && ($taxFact['source_label'] ?? '') === 'CRM-Steuerkurztext'
    && str_contains((string) ($taxFact['body'] ?? ''), '§ 19'),
    'tax chunk has norm in body',
    $taxFact
);

// Retriever integriert Steuerkurztexte
$hit = KichelRetriever::retrieve($user, 'Erklär mir die Kleinunternehmerregelung');
$refs = array_map(static fn (array $f): string => (string) ($f['source_ref'] ?? ''), $hit['facts'] ?? []);
$types = array_map(static fn (array $f): string => (string) ($f['type'] ?? ''), $hit['facts'] ?? []);
kr4_assert(
    ($hit['path'] ?? '') === 'candidates' && in_array('tax:tax-u19', $refs, true),
    'retrieve includes tax:tax-u19',
    ['path' => $hit['path'] ?? null, 'refs' => $refs, 'types' => $types]
);

$answer = KichelAssistant::answer($user, 'Was ist Ist-Versteuerung?');
kr4_assert(
    str_contains((string) ($answer['answer'] ?? ''), 'Ist') || str_contains((string) ($answer['answer'] ?? ''), 'Zahlung'),
    'assistant answers ist-versteuerung',
    $answer['answer'] ?? ''
);

echo $failed === 0 ? "ALL OK\n" : "FAILED {$failed}\n";
exit($failed === 0 ? 0 : 1);
