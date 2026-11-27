<?php
declare(strict_types=1);

/**
 * KL1 — GII TOC-Auflösung + XML→§-Chunks (offline Fixtures).
 * Usage: php bin/kichel-gii-import-selftest.php
 */
if (!defined('DG_ROOT')) {
    define('DG_ROOT', dirname(__DIR__));
}
require_once DG_ROOT . '/src/autoload.php';

$failed = 0;
$fixDir = DG_ROOT . '/tests/fixtures/kichel';
$tocMini = $fixDir . '/gii-toc-mini.xml';
$xmlMini = $fixDir . '/gii-ustg-mini.xml';

function kl1_assert(bool $ok, string $label, mixed $detail = null): void
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

kl1_assert(is_readable($tocMini), 'fixture toc readable', $tocMini);
kl1_assert(is_readable($xmlMini), 'fixture xml readable', $xmlMini);

// KL1a — Whitelist / TOC
$resolved = KichelGiiImporter::resolveFromTocFile($tocMini, ['AO', 'UStG', 'EStG']);
kl1_assert(count($resolved) === 3, 'toc resolves pilot 3', array_column($resolved, 'abbr'));
kl1_assert(($resolved[0]['slug'] ?? '') === 'ao_1977', 'AO slug ao_1977');
kl1_assert(str_contains((string) ($resolved[1]['link'] ?? ''), '/ustg_1980/xml.zip'), 'UStG link', $resolved[1]['link'] ?? null);
kl1_assert(str_starts_with((string) ($resolved[1]['link'] ?? ''), 'https://'), 'links https');

$withHgb = KichelGiiImporter::resolveFromTocFile($tocMini, ['HGB']);
kl1_assert(($withHgb[0]['slug'] ?? '') === 'hgb', 'optional HGB');

$threw = false;
try {
    KichelGiiImporter::normalizeLawList(['BGB']);
} catch (InvalidArgumentException $e) {
    $threw = true;
}
kl1_assert($threw, 'unknown law rejected');

// KL1b — Parser
$fetched = '2026-11-27T12:00:00+00:00';
$chunks = KichelGiiXmlParser::parseFile($xmlMini, 'UStG', 'ustg_1980', $fetched);
kl1_assert($chunks !== [], 'chunks non-empty', count($chunks));

$refs = array_column($chunks, 'source_ref');
kl1_assert(in_array('gii:ustg:§1b', $refs, true) || (bool) array_filter($refs, static fn ($r) => str_starts_with((string) $r, 'gii:ustg:§1b')), 'has §1b ref', $refs);

$oneB = null;
foreach ($chunks as $c) {
    if (($c['enbez'] ?? '') === '§ 1b') {
        $oneB = $c;
        break;
    }
}
kl1_assert($oneB !== null, '§ 1b chunk exists');
kl1_assert(($oneB['type'] ?? '') === KichelRagFact::TYPE_STATUTE, 'type statute');
kl1_assert(($oneB['source_label'] ?? '') === 'GII UStG', 'source_label');
kl1_assert(str_contains((string) ($oneB['body'] ?? ''), 'innergemeinschaftlicher Erwerb') || str_contains((string) ($oneB['body'] ?? ''), 'Innergemeinschaftlicher Erwerb') || str_contains(mb_strtolower((string) ($oneB['body'] ?? ''), 'UTF-8'), 'fahrzeug'), '§1b body has content');
kl1_assert(($oneB['href'] ?? null) === 'https://www.gesetze-im-internet.de/ustg_1980/__1b.html', 'href §1b', $oneB['href'] ?? null);
kl1_assert(str_contains((string) ($oneB['body'] ?? ''), 'konsolidiert GII'), 'standhinweis in body');

$hasAbsSplit = false;
foreach ($chunks as $c) {
    if (($c['enbez'] ?? '') === '§ 1b' && ($c['absatz'] ?? null) !== null) {
        $hasAbsSplit = true;
        break;
    }
}
kl1_assert($hasAbsSplit, '§ 1b split into Absätze');

kl1_assert(KichelGiiXmlParser::isChunkableEnbez('§ 19'), 'chunkable §');
kl1_assert(KichelGiiXmlParser::isChunkableEnbez('Anlage 1'), 'chunkable Anlage');
kl1_assert(!KichelGiiXmlParser::isChunkableEnbez('Inhaltsübersicht'), 'skip Inhaltsübersicht');
kl1_assert(!KichelGiiXmlParser::isChunkableEnbez(''), 'skip empty enbez');

// KL1c — writeChunks + CLI offline
$written = KichelGiiImporter::writeChunks('UStG', $chunks, [
    'slug' => 'ustg_1980',
    'mode' => 'selftest',
    'fetched_at' => $fetched,
]);
kl1_assert(is_readable($written['jsonl']), 'jsonl written', $written['jsonl']);
kl1_assert(is_readable($written['manifest']), 'manifest written');
$lines = file($written['jsonl'], FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
kl1_assert(count($lines) === count($chunks), 'jsonl line count', count($lines));
$first = json_decode((string) $lines[0], true);
kl1_assert(is_array($first) && ($first['type'] ?? '') === 'statute', 'jsonl first statute');

$manifest = json_decode((string) file_get_contents($written['manifest']), true);
kl1_assert(is_array($manifest) && (int) ($manifest['chunk_count'] ?? 0) === count($chunks), 'manifest count');

// dry-run gegen Mini-TOC
$dry = KichelGiiImporter::run(['UStG'], $tocMini, false);
kl1_assert(($dry['resolved'][0]['abbr'] ?? '') === 'UStG', 'dry-run resolve');
kl1_assert(!empty($dry['results'][0]['skipped_download']), 'dry-run skips download');

if ($failed > 0) {
    echo "FAILED: {$failed}\n";
    exit(1);
}
echo "OK KL1 selftest\n";
exit(0);
