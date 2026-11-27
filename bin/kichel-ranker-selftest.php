<?php
declare(strict_types=1);

/**
 * KR5 — Ranking / Leerfall / Top-K.
 * Usage: php bin/kichel-ranker-selftest.php
 */
if (!defined('DG_ROOT')) {
    define('DG_ROOT', dirname(__DIR__));
}
require_once DG_ROOT . '/src/autoload.php';

$failed = 0;
$adminRole = (string) App::config('roles.admin', 'administrator');
$user = new User(1, 'admin', 'Administrator', '', [$adminRole], true);

function kr5_assert(bool $ok, string $label, mixed $detail = null): void
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

// KR5a — unter Minimum → leer
$weak = KichelRanker::select([
    ['kind' => 'topic', 'score' => 4, 'title' => 'Schwach', 'body' => 'x', 'href' => '', 'action_label' => '', 'dedupe' => 'w1'],
    ['kind' => 'topic', 'score' => 2, 'title' => 'Schwächer', 'body' => 'y', 'href' => '', 'action_label' => '', 'dedupe' => 'w2'],
]);
kr5_assert(
    $weak['candidates'] === [] && ($weak['meta']['reason'] ?? '') === 'below_min_score',
    'scores below MIN_ACCEPT → empty',
    $weak['meta']
);

$nonsense = KichelRetriever::retrieve($user, 'asdf qwer zxcv xyz');
kr5_assert(($nonsense['path'] ?? '') === 'empty', 'nonsense query → empty path', $nonsense['path'] ?? null);

$ansNonsense = KichelAssistant::answer($user, 'asdf qwer zxcv xyz');
kr5_assert(
    empty($ansNonsense['rag_facts'])
    && str_contains((string) ($ansNonsense['answer'] ?? ''), 'nichts Passendes')
    && str_contains((string) ($ansNonsense['answer'] ?? ''), 'Kleinunternehmer'),
    'nonsense → notFoundMessage, no rag_facts',
    $ansNonsense['answer'] ?? ''
);

// KR5b — Top-K + relative floor
$mixed = KichelRanker::select([
    ['kind' => 'tax_note', 'score' => 16, 'title' => 'Top', 'body' => 'A', 'href' => '/a', 'action_label' => 'A', 'dedupe' => 't1'],
    ['kind' => 'topic', 'score' => 14, 'title' => 'Nah', 'body' => 'B', 'href' => '/b', 'action_label' => 'B', 'dedupe' => 't2'],
    ['kind' => 'navigation', 'score' => 6, 'title' => 'Rand', 'body' => 'C', 'href' => '/c', 'action_label' => 'C', 'dedupe' => 't3'],
    ['kind' => 'topic', 'score' => 5, 'title' => 'Schwach', 'body' => 'D', 'href' => '/d', 'action_label' => 'D', 'dedupe' => 't4'],
]);
$scores = array_map(static fn (array $c): int => (int) $c['score'], $mixed['candidates']);
kr5_assert(
    count($mixed['candidates']) <= KichelRanker::TOP_K
    && !in_array(5, $scores, true)
    && in_array(16, $scores, true),
    'relative floor drops weak vs top',
    $scores
);

$strong = KichelRetriever::retrieve($user, 'Kleinunternehmerregelung');
kr5_assert(
    ($strong['path'] ?? '') === 'candidates'
    && ($strong['rank_meta']['kept'] ?? 0) >= 1
    && ($strong['rank_meta']['top_score'] ?? 0) >= KichelRanker::MIN_ACCEPT_SCORE,
    'strong tax query ranked',
    $strong['rank_meta'] ?? null
);

$multiAns = KichelAssistant::answer($user, 'Wo trage ich die USt-ID ein?');
if (!empty($multiAns['rag_facts']) && count($multiAns['rag_facts']) > 1) {
    kr5_assert(
        str_contains((string) ($multiAns['answer'] ?? ''), '1.')
        && str_contains((string) ($multiAns['answer'] ?? ''), 'Quelle:'),
        'multi facts labeled in answer',
        $multiAns['answer'] ?? ''
    );
} else {
    kr5_assert(
        !empty($multiAns['rag_facts']) || str_contains((string) ($multiAns['answer'] ?? ''), 'USt'),
        'ust-id still answers',
        ['n' => count($multiAns['rag_facts'] ?? []), 'answer' => $multiAns['answer'] ?? '']
    );
}

kr5_assert(str_contains(KichelRanker::notFoundMessage(), 'Ist-Versteuerung'), 'hint mentions tax examples');

echo $failed === 0 ? "ALL OK\n" : "FAILED {$failed}\n";
exit($failed === 0 ? 0 : 1);
