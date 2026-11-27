<?php

declare(strict_types=1);

/**
 * KH1 — Qualitäts-Draft, Overrides, Knowledge-Merge (ohne Live-DB).
 * Usage: php bin/kichel-quality-selftest.php
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

$draft = KichelQualityService::draftFromLog([
    'query_text' => 'Wo finde ich die USt-ID?',
    'answer_text' => 'Unter Einstellungen → Firmendaten.',
    'response_json' => json_encode([
        'rag_used' => true,
        'sources' => [
            ['label' => 'CRM-Hilfe', 'ref' => 'topic:ust-id', 'href' => '/app?page=einstellungen&tab=firmendaten'],
        ],
    ], JSON_THROW_ON_ERROR),
]);
$assert(
    str_contains($draft['title'], 'USt-ID')
    && $draft['answer'] !== ''
    && $draft['href'] === '/app?page=einstellungen&tab=firmendaten'
    && $draft['keywords'] !== [],
    'draftFromLog from query/answer/sources',
    $draft
);

$note = KichelQualityService::encodeNote($draft);
$decoded = KichelQualityService::decodeNote($note);
$assert(
    ($decoded['title'] ?? '') === $draft['title'] && ($decoded['href'] ?? '') === $draft['href'],
    'encode/decode note roundtrip'
);

$assert(KichelQualityService::statusLabel('bad') === 'Schlecht', 'statusLabel bad');
$assert(KichelQualityService::statusLabel(null) === '—', 'statusLabel empty');

$dir = sys_get_temp_dir() . '/dg-kichel-kh1-' . bin2hex(random_bytes(4));
if (!mkdir($dir, 0775, true) && !is_dir($dir)) {
    fwrite(STDERR, "FAIL: temp dir\n");
    exit(1);
}

// Overrides path is fixed under DG_ROOT — write/read via upsert then restore.
$overridePath = KichelKnowledgeOverrides::path();
$backup = is_file($overridePath) ? (string) file_get_contents($overridePath) : null;
$overrideDir = dirname($overridePath);
if (!is_dir($overrideDir)) {
    mkdir($overrideDir, 0775, true);
}

$topicId = 'override-selftest-kh1';
$assert(
    KichelKnowledgeOverrides::upsert([
        'id' => $topicId,
        'keywords' => ['selftest-kh1-xyz'],
        'title' => 'Selftest KH1',
        'answer' => 'Nur Test — Instanz-Override.',
        'href' => '/app?page=einstellungen',
        'from_log_id' => 999001,
    ]),
    'upsert override'
);

KichelKnowledge::resetTopicsCache();
$matched = KichelKnowledge::matchTopics(['selftest-kh1-xyz'], 3);
$assert(
    $matched !== [] && (($matched[0]['topic']['id'] ?? '') === $topicId),
    'Knowledge matchTopics sees override',
    $matched[0]['topic']['id'] ?? null
);

$all = KichelKnowledgeOverrides::all();
$found = false;
foreach ($all as $row) {
    if (($row['id'] ?? '') === $topicId) {
        $found = true;
        break;
    }
}
$assert($found, 'overrides all() contains selftest id');

// Aufräumen: Selftest-Eintrag entfernen
$cleaned = [];
foreach ($all as $row) {
    if (($row['id'] ?? '') === $topicId) {
        continue;
    }
    $cleaned[] = $row;
}
if ($backup !== null) {
    file_put_contents($overridePath, $backup, LOCK_EX);
} elseif ($cleaned === []) {
    if (is_file($overridePath)) {
        unlink($overridePath);
    }
} else {
    file_put_contents(
        $overridePath,
        json_encode($cleaned, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n",
        LOCK_EX
    );
}
KichelKnowledge::resetTopicsCache();

$widget = (string) file_get_contents(DG_ROOT . '/views/modules/kichel-protokoll.php');
$assert(str_contains($widget, 'kichel_quality_action'), 'protokoll has quality form actions');
$assert(str_contains($widget, 'In Hilfe übernehmen'), 'protokoll apply label');

$api = (string) file_get_contents(DG_ROOT . '/src/Kichel/KichelApi.php');
$assert(str_contains($api, "action === 'feedback'"), 'API feedback action');
$assert(str_contains($api, 'log_id'), 'API returns/handles log_id');

$mig = DG_ROOT . '/database/migrations/161_kichel_quality.sql';
$assert(is_file($mig) && str_contains((string) file_get_contents($mig), 'quality_status'), 'migration 161');

@rmdir($dir);

if ($failed > 0) {
    fwrite(STDERR, "FAILED: {$failed}\n");
    exit(1);
}
echo "OK KH1 quality selftest\n";
exit(0);
