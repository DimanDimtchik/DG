<?php
declare(strict_types=1);

/**
 * KL5 — RII BFH-Pilot: TOC-Filter, Parser, Index, Retriever (offline).
 * Usage: php bin/kichel-rii-import-selftest.php
 */
if (!defined('DG_ROOT')) {
    define('DG_ROOT', dirname(__DIR__));
}
require_once DG_ROOT . '/src/autoload.php';

$failed = 0;
$fix = DG_ROOT . '/tests/fixtures/kichel';
$tocMini = $fix . '/rii-toc-mini.xml';
$xmlMini = $fix . '/rii-bfh-mini.xml';
$workDir = sys_get_temp_dir() . '/kichel-kl5-' . bin2hex(random_bytes(4));
$adminRole = (string) App::config('roles.admin', 'administrator');
$user = new User(1, 'admin', 'Administrator', '', [$adminRole], true);

function kl5_assert(bool $ok, string $label, mixed $detail = null): void
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

try {
    if (!is_dir($workDir) && !mkdir($workDir, 0775, true) && !is_dir($workDir)) {
        throw new RuntimeException('workDir');
    }

    kl5_assert(is_readable($tocMini) && is_readable($xmlMini), 'fixtures readable');

    $list = KichelRiiImporter::listFromTocFile($tocMini, 'BFH', 10);
    kl5_assert(count($list) === 1, 'TOC only BFH', count($list));
    kl5_assert(str_starts_with($list[0]['gericht'], 'BFH'), 'gericht BFH', $list[0]['gericht']);

    $chunks = KichelRiiImporter::importFromXmlFile($xmlMini, '2026-11-27T12:00:00+00:00', null, true);
    kl5_assert($chunks !== [], 'topic filter keeps EStG decision', count($chunks));
    kl5_assert(($chunks[0]['type'] ?? '') === 'caselaw', 'type caselaw');
    kl5_assert(str_starts_with((string) ($chunks[0]['source_ref'] ?? ''), 'rii:bfh:'), 'ref rii:bfh', $chunks[0]['source_ref'] ?? null);
    kl5_assert(str_contains((string) ($chunks[0]['body'] ?? ''), 'Leitsatz'), 'has leitsatz');
    kl5_assert(str_contains((string) ($chunks[0]['body'] ?? ''), 'Quelle: RII'), 'standhinweis');

    $noTopic = KichelRiiXmlParser::matchesTopicFilter([
        'norm' => '§ 1 BGB',
        'title' => 'Sonstiges',
        'body' => 'Kein Steuerbezug',
    ]);
    kl5_assert(!$noTopic, 'topic filter rejects BGB-only');

    $written = KichelRiiImporter::writeChunks($chunks, ['mode' => 'selftest'], $workDir);
    kl5_assert(is_readable($written['jsonl']), 'jsonl written');

    KichelCaselawIndex::setDirOverride($workDir);
    kl5_assert(KichelCaselawIndex::count() === 1, 'index count 1');
    $hits = KichelCaselawIndex::searchQuery('BFH Abzinsung Gesellschafterdarlehen EStG', 3);
    kl5_assert($hits !== [], 'search hits');
    kl5_assert(($hits[0]['chunk']['gertyp'] ?? '') === 'BFH', 'top BFH');

    $facts = KichelCaselawIndex::factsFromHits($hits);
    kl5_assert(($facts[0]['type'] ?? '') === 'caselaw', 'facts caselaw');

    $pack = KichelRetriever::retrieve($user, 'BFH Urteil Abzinsung Gesellschafterdarlehen EStG');
    $types = array_map(static fn (array $f): string => (string) ($f['type'] ?? ''), $pack['facts'] ?? []);
    kl5_assert(in_array('caselaw', $types, true), 'retriever facts caselaw', $types);

    $mapped = KichelCorpus::chunkFromCandidate([
        'kind' => 'caselaw',
        'score' => 20,
        'title' => 'BFH Test',
        'body' => 'Body',
        'href' => 'https://example.test',
        'dedupe' => 'rii:bfh:test',
        'source_label' => 'RII BFH',
    ]);
    kl5_assert(($mapped['type'] ?? '') === 'caselaw' && ($mapped['source_label'] ?? '') === 'RII BFH', 'corpus caselaw');
} finally {
    KichelCaselawIndex::setDirOverride(null);
    foreach (glob($workDir . '/*') ?: [] as $f) {
        @unlink($f);
    }
    @rmdir($workDir);
}

if ($failed > 0) {
    echo "FAILED: {$failed}\n";
    exit(1);
}
echo "OK KL5 selftest\n";
exit(0);
