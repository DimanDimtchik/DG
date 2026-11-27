<?php
declare(strict_types=1);

/**
 * KL4 — TOC-Diff / Snapshot (offline Fixtures).
 * Usage: php bin/kichel-gii-update-selftest.php
 */
if (!defined('DG_ROOT')) {
    define('DG_ROOT', dirname(__DIR__));
}
require_once DG_ROOT . '/src/autoload.php';

$failed = 0;
$fixDir = DG_ROOT . '/tests/fixtures/kichel';
$tocA = $fixDir . '/gii-toc-mini.xml';
$workDir = sys_get_temp_dir() . '/kichel-kl4-' . bin2hex(random_bytes(4));
$tocB = $workDir . '/gii-toc-changed.xml';

function kl4_assert(bool $ok, string $label, mixed $detail = null): void
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

    // Erster Stand: Snapshot aus Mini-TOC
    $check1 = KichelGiiUpdater::check(['AO', 'UStG', 'EStG'], $tocA, $workDir, false);
    kl4_assert($check1['previous'] === null, 'no previous snapshot');
    kl4_assert($check1['diff']['added'] === ['AO', 'EStG', 'UStG'], 'first run all added', $check1['diff']['added']);
    kl4_assert($check1['to_import'] === ['AO', 'EStG', 'UStG'] || $check1['to_import'] === ['AO', 'UStG', 'EStG'], 'to_import all', $check1['to_import']);

    $apply1 = KichelGiiUpdater::apply(['AO', 'UStG', 'EStG'], $tocA, $workDir, false, false);
    kl4_assert(is_readable($apply1['snapshot_path']), 'snapshot written', $apply1['snapshot_path']);
    kl4_assert(($apply1['diff']['added'] ?? []) !== [] || ($apply1['to_import'] ?? []) !== [], 'apply1 imported list');

    $check2 = KichelGiiUpdater::check(['AO', 'UStG', 'EStG'], $tocA, $workDir, false);
    kl4_assert($check2['previous'] !== null, 'previous loaded');
    kl4_assert($check2['diff']['changed'] === [], 'unchanged after same toc', $check2['diff']);
    kl4_assert($check2['diff']['added'] === [], 'no added');
    kl4_assert($check2['to_import'] === [], 'nothing to import');

    // TOC ändern: UStG-Titel
    $xml = file_get_contents($tocA);
    if ($xml === false) {
        throw new RuntimeException('tocA');
    }
    $xmlChanged = str_replace('Umsatzsteuergesetz', 'Umsatzsteuergesetz (geändert)', $xml);
    file_put_contents($tocB, $xmlChanged);

    $check3 = KichelGiiUpdater::check(['AO', 'UStG', 'EStG'], $tocB, $workDir, false);
    kl4_assert($check3['diff']['changed'] === ['UStG'], 'UStG changed', $check3['diff']['changed']);
    kl4_assert($check3['to_import'] === ['UStG'], 'only UStG to import', $check3['to_import']);
    kl4_assert(in_array('AO', $check3['diff']['unchanged'], true), 'AO unchanged');

    $apply2 = KichelGiiUpdater::apply(['AO', 'UStG', 'EStG'], $tocB, $workDir, false, false);
    kl4_assert($apply2['to_import'] === ['UStG'], 'apply2 only UStG');

    $check4 = KichelGiiUpdater::check(['AO', 'UStG', 'EStG'], $tocB, $workDir, false);
    kl4_assert($check4['to_import'] === [], 'stable after apply2');

    // force
    $checkForce = KichelGiiUpdater::check(['UStG'], $tocB, $workDir, true);
    kl4_assert($checkForce['to_import'] === ['UStG'], 'force reimports', $checkForce['to_import']);

    // removed: Snapshot hat HGB, TOC ohne
    $snap = KichelGiiUpdater::loadSnapshot(KichelGiiUpdater::snapshotPath($workDir));
    kl4_assert($snap !== null, 'snap load');
    $snap['laws']['HGB'] = [
        'abbr' => 'HGB',
        'slug' => 'hgb',
        'title' => 'Handelsgesetzbuch',
        'link' => 'https://www.gesetze-im-internet.de/hgb/xml.zip',
        'fingerprint' => KichelGiiUpdater::fingerprint([
            'slug' => 'hgb',
            'title' => 'Handelsgesetzbuch',
            'link' => 'https://www.gesetze-im-internet.de/hgb/xml.zip',
        ]),
    ];
    KichelGiiUpdater::saveSnapshot($snap, KichelGiiUpdater::snapshotPath($workDir));
    $checkRem = KichelGiiUpdater::check(['AO', 'UStG', 'EStG'], $tocB, $workDir, false);
    kl4_assert($checkRem['diff']['removed'] === ['HGB'], 'HGB removed from toc view', $checkRem['diff']['removed']);

    // fingerprint stable
    $fp1 = KichelGiiUpdater::fingerprint(['slug' => 'a', 'title' => 't', 'link' => 'l']);
    $fp2 = KichelGiiUpdater::fingerprint(['slug' => 'a', 'title' => 't', 'link' => 'l']);
    $fp3 = KichelGiiUpdater::fingerprint(['slug' => 'a', 'title' => 't2', 'link' => 'l']);
    kl4_assert($fp1 === $fp2 && $fp1 !== $fp3, 'fingerprint differs on title');
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
echo "OK KL4 selftest\n";
exit(0);
