<?php
declare(strict_types=1);

/**
 * KL2 — JSONL laden + Volltext-Suche (offline Fixtures).
 * Usage: php bin/kichel-statute-index-selftest.php
 */
if (!defined('DG_ROOT')) {
    define('DG_ROOT', dirname(__DIR__));
}
require_once DG_ROOT . '/src/autoload.php';

$failed = 0;
$xmlMini = DG_ROOT . '/tests/fixtures/kichel/gii-ustg-mini.xml';
$workDir = sys_get_temp_dir() . '/kichel-kl2-' . bin2hex(random_bytes(4));

function kl2_assert(bool $ok, string $label, mixed $detail = null): void
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

    KichelStatuteIndex::reset();
    kl2_assert(KichelStatuteIndex::count($workDir) === 0, 'empty dir → 0 chunks');

    $chunks = KichelGiiXmlParser::parseFile($xmlMini, 'UStG', 'ustg_1980', '2026-11-27T12:00:00+00:00');
    // writeChunks schreibt nach storage/… — hier manuell JSONL in Temp
    $jsonl = $workDir . '/ustg.jsonl';
    $fh = fopen($jsonl, 'wb');
    if ($fh === false) {
        throw new RuntimeException('jsonl open');
    }
    foreach ($chunks as $c) {
        fwrite($fh, json_encode($c, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n");
    }
    fclose($fh);
    file_put_contents($workDir . '/ustg.manifest.json', json_encode([
        'law' => 'UStG',
        'chunk_count' => count($chunks),
        'source' => 'fixture',
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

    KichelStatuteIndex::reset();
    $loaded = KichelStatuteIndex::loadFromDir($workDir);
    kl2_assert(count($loaded) === count($chunks), 'load count', [count($loaded), count($chunks)]);
    kl2_assert(KichelStatuteIndex::count($workDir) === count($chunks), 'count cache');
    kl2_assert(KichelStatuteIndex::manifests($workDir) !== [], 'manifest loaded');

    // § 19 UStG Kleinunternehmer
    $hits19 = KichelStatuteIndex::searchQuery('§ 19 UStG Kleinunternehmer', 5, $workDir);
    kl2_assert($hits19 !== [], 'search §19 hits', array_map(static fn ($h) => $h['chunk']['source_ref'] ?? '', $hits19));
    $top19 = $hits19[0]['chunk']['enbez'] ?? '';
    kl2_assert($top19 === '§ 19', 'top hit enbez § 19', $top19);
    kl2_assert(str_starts_with((string) ($hits19[0]['chunk']['source_ref'] ?? ''), 'gii:ustg:§19'), 'top ref §19');

    $facts = KichelStatuteIndex::factsFromHits($hits19);
    kl2_assert($facts !== [] && ($facts[0]['type'] ?? '') === 'statute', 'facts type statute');
    kl2_assert(($facts[0]['source_label'] ?? '') === 'GII UStG', 'facts source_label');

    // Inhalts-Suche ohne §
    $hitsFahr = KichelStatuteIndex::searchQuery('neuer Fahrzeuge innergemeinschaftlicher Erwerb UStG', 5, $workDir);
    kl2_assert($hitsFahr !== [], 'search fahrzeug hits');
    $topFahr = (string) ($hitsFahr[0]['chunk']['enbez'] ?? '');
    kl2_assert($topFahr === '§ 1b', 'fahrzeug → § 1b', $topFahr);

    // Anderes Gesetz in Query → UStG-Chunks abgewertet / keine Fantasie
    $emptyAo = KichelStatuteIndex::searchQuery('§ 19 AO', 5, $workDir);
    // Index hat nur UStG — bei AO-Hint Score negativ/0
    $aoTopIs19 = $emptyAo !== [] && (($emptyAo[0]['chunk']['enbez'] ?? '') === '§ 19');
    kl2_assert(!$aoTopIs19 || ($emptyAo[0]['score'] ?? 0) < ($hits19[0]['score'] ?? 0), 'AO query does not prefer UStG §19 strongly', $emptyAo[0]['score'] ?? null);

    // Bad JSONL line ignored
    file_put_contents($workDir . '/noise.jsonl', "not-json\n");
    KichelStatuteIndex::reset();
    $again = KichelStatuteIndex::loadFromDir($workDir);
    kl2_assert(count($again) === count($chunks), 'bad lines skipped', count($again));

    // Dedup by source_ref
    file_put_contents($workDir . '/dup.jsonl', json_encode($chunks[0], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n");
    KichelStatuteIndex::reset();
    $deduped = KichelStatuteIndex::loadFromDir($workDir);
    kl2_assert(count($deduped) === count($chunks), 'dedupe source_ref', count($deduped));

    KichelStatuteIndex::reset();
} finally {
    foreach (glob($workDir . '/*') ?: [] as $f) {
        @unlink($f);
    }
    @rmdir($workDir);
}

if ($failed > 0) {
    echo "FAILED: {$failed}\n";
    exit(1);
}
echo "OK KL2 selftest\n";
exit(0);
