<?php
declare(strict_types=1);

/**
 * KL3 — Retriever liefert type=statute aus KichelStatuteIndex.
 * Usage: php bin/kichel-statute-retriever-selftest.php
 */
if (!defined('DG_ROOT')) {
    define('DG_ROOT', dirname(__DIR__));
}
require_once DG_ROOT . '/src/autoload.php';

$failed = 0;
$adminRole = (string) App::config('roles.admin', 'administrator');
$user = new User(1, 'admin', 'Administrator', '', [$adminRole], true);
$xmlMini = DG_ROOT . '/tests/fixtures/kichel/gii-ustg-mini.xml';
$workDir = sys_get_temp_dir() . '/kichel-kl3-' . bin2hex(random_bytes(4));

function kl3_assert(bool $ok, string $label, mixed $detail = null): void
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
    $chunks = KichelGiiXmlParser::parseFile($xmlMini, 'UStG', 'ustg_1980', '2026-11-27T12:00:00+00:00');
    $fh = fopen($workDir . '/ustg.jsonl', 'wb');
    if ($fh === false) {
        throw new RuntimeException('jsonl');
    }
    foreach ($chunks as $c) {
        fwrite($fh, json_encode($c, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n");
    }
    fclose($fh);

    KichelStatuteIndex::setDirOverride($workDir);
    kl3_assert(KichelStatuteIndex::count() === count($chunks), 'index via override', KichelStatuteIndex::count());
    kl3_assert(KichelRetriever::isStatuteEnabled(), 'statute enabled default');

    $pack = KichelRetriever::retrieve($user, 'Was steht in § 19 UStG zum Kleinunternehmer?');
    kl3_assert(($pack['path'] ?? '') === 'candidates', 'path candidates', $pack['path'] ?? null);

    $types = array_map(static fn (array $f): string => (string) ($f['type'] ?? ''), $pack['facts'] ?? []);
    kl3_assert(in_array(KichelRagFact::TYPE_STATUTE, $types, true), 'facts contain statute', $types);

    $statuteFact = null;
    foreach ($pack['facts'] as $f) {
        if (($f['type'] ?? '') === KichelRagFact::TYPE_STATUTE) {
            $statuteFact = $f;
            break;
        }
    }
    kl3_assert($statuteFact !== null, 'statute fact present');
    kl3_assert(str_starts_with((string) ($statuteFact['source_ref'] ?? ''), 'gii:ustg:§19'), 'ref §19', $statuteFact['source_ref'] ?? null);
    kl3_assert(str_starts_with((string) ($statuteFact['source_label'] ?? ''), 'GII'), 'label GII', $statuteFact['source_label'] ?? null);
    kl3_assert(str_contains((string) ($statuteFact['href'] ?? ''), 'ustg_1980'), 'href gii', $statuteFact['href'] ?? null);

    // CRM-Frage ohne Norm → kein Fantasy-§ (Index hat nur UStG; USt-ID sollte nicht Top-statute erzwingen)
    $ustId = KichelRetriever::retrieve($user, 'Wo trage ich die USt-ID ein?');
    $ustTypes = array_map(static fn (array $f): string => (string) ($f['type'] ?? ''), $ustId['facts'] ?? []);
    $topIsStatute = ($ustId['facts'][0]['type'] ?? '') === KichelRagFact::TYPE_STATUTE;
    kl3_assert(!$topIsStatute, 'USt-ID top is not statute', $ustTypes);

    // Corpus-Mapping
    $mapped = KichelCorpus::chunkFromCandidate([
        'kind' => 'statute',
        'score' => 30,
        'title' => 'UStG § 19',
        'body' => 'Testbody',
        'href' => 'https://www.gesetze-im-internet.de/ustg_1980/__19.html',
        'dedupe' => 'gii:ustg:§19:abs1',
        'source_label' => 'GII UStG',
    ]);
    kl3_assert(($mapped['type'] ?? '') === 'statute' && ($mapped['source_label'] ?? '') === 'GII UStG', 'corpus statute map', $mapped);
} finally {
    KichelStatuteIndex::setDirOverride(null);
    foreach (glob($workDir . '/*') ?: [] as $f) {
        @unlink($f);
    }
    @rmdir($workDir);
}

if ($failed > 0) {
    echo "FAILED: {$failed}\n";
    exit(1);
}
echo "OK KL3 selftest\n";
exit(0);
