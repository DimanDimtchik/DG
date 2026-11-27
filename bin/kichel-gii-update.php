<?php
declare(strict_types=1);

/**
 * KL4 — GII TOC-Diff Update (Whitelist).
 *
 * Usage:
 *   php bin/kichel-gii-update.php --check
 *   php bin/kichel-gii-update.php --apply
 *   php bin/kichel-gii-update.php --apply --force
 *   php bin/kichel-gii-update.php --check --toc=path/to/gii-toc.xml --laws=AO,UStG
 */
if (!defined('DG_ROOT')) {
    define('DG_ROOT', dirname(__DIR__));
}
require_once DG_ROOT . '/src/autoload.php';

$opts = getopt('', ['check', 'apply', 'force', 'laws:', 'toc:', 'dir:', 'dry-run', 'help']);
if (isset($opts['help']) || (!isset($opts['check']) && !isset($opts['apply']))) {
    echo "KL4 GII TOC-Diff Update\n";
    echo "  --check            Diff gegen Snapshot (kein Import)\n";
    echo "  --apply            Diff + Import geänderter/neuer Gesetze + Snapshot speichern\n";
    echo "  --force            mit --apply: alle Whitelist-Gesetze neu laden\n";
    echo "  --dry-run          mit --apply: Snapshot aktualisieren, kein Download\n";
    echo "  --laws=AO,UStG     Teilmenge\n";
    echo "  --toc=URL|Pfad     gii-toc.xml\n";
    echo "  --dir=Pfad         Zielordner (Default storage/kichel/gii)\n";
    exit(isset($opts['help']) ? 0 : 1);
}

$laws = null;
if (isset($opts['laws'])) {
    $laws = array_values(array_filter(array_map('trim', explode(',', (string) $opts['laws']))));
}
$toc = isset($opts['toc']) ? (string) $opts['toc'] : null;
$dir = isset($opts['dir']) ? (string) $opts['dir'] : null;
$force = isset($opts['force']);

try {
    if (isset($opts['check'])) {
        $report = KichelGiiUpdater::check($laws, $toc, $dir, $force);
        echo json_encode([
            'ok' => true,
            'mode' => 'check',
            'diff' => [
                'changed' => $report['diff']['changed'],
                'unchanged' => $report['diff']['unchanged'],
                'added' => $report['diff']['added'],
                'removed' => $report['diff']['removed'],
            ],
            'to_import' => $report['to_import'],
            'snapshot_path' => $report['snapshot_path'],
            'has_previous' => $report['previous'] !== null,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . "\n";
        exit(0);
    }

    $download = !isset($opts['dry-run']);
    $report = KichelGiiUpdater::apply($laws, $toc, $dir, $force, $download);
    echo json_encode($report, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . "\n";
    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, 'FAIL: ' . $e->getMessage() . "\n");
    exit(1);
}
