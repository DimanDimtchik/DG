<?php
declare(strict_types=1);

/**
 * KL5 — RII-Import Pilot (BFH + Themenfilter).
 *
 * Usage:
 *   php bin/kichel-rii-import.php --dry-run
 *   php bin/kichel-rii-import.php --max=10
 *   php bin/kichel-rii-import.php --toc=path/to/rii-toc.xml --max=5
 *   php bin/kichel-rii-import.php --xml=path.xml
 */
if (!defined('DG_ROOT')) {
    define('DG_ROOT', dirname(__DIR__));
}
require_once DG_ROOT . '/src/autoload.php';

$opts = getopt('', ['dry-run', 'max:', 'toc:', 'xml:', 'dir:', 'help']);
if (isset($opts['help'])) {
    echo "KL5 RII-Import (BFH Pilot)\n";
    echo "  --dry-run       nur TOC-Kandidaten (BFH), kein Download\n";
    echo "  --max=N         max. behaltene Chunks nach Themenfilter (Default 15)\n";
    echo "  --toc=URL|Pfad  rii-toc.xml\n";
    echo "  --xml=Pfad      Offline eine Entscheidungs-XML importieren\n";
    echo "  --dir=Pfad      Zielordner (Default storage/kichel/rii)\n";
    exit(0);
}

try {
    if (isset($opts['xml'])) {
        $chunks = KichelRiiImporter::importFromXmlFile((string) $opts['xml'], null, null, true);
        $dir = isset($opts['dir']) ? (string) $opts['dir'] : null;
        $written = KichelRiiImporter::writeChunks($chunks, ['mode' => 'offline-xml'], $dir);
        echo json_encode([
            'ok' => true,
            'mode' => 'xml',
            'chunk_count' => $written['chunk_count'],
            'jsonl' => $written['jsonl'],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . "\n";
        exit(0);
    }

    $max = isset($opts['max']) ? (int) $opts['max'] : KichelRiiImporter::DEFAULT_MAX;
    $toc = isset($opts['toc']) ? (string) $opts['toc'] : null;
    $dir = isset($opts['dir']) ? (string) $opts['dir'] : null;
    $dry = isset($opts['dry-run']);
    $report = KichelRiiImporter::run($toc, $max, !$dry, $dir);
    echo json_encode(['ok' => true, 'mode' => $dry ? 'dry-run' : 'import'] + $report, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . "\n";
    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, 'FAIL: ' . $e->getMessage() . "\n");
    exit(1);
}
