<?php
declare(strict_types=1);

/**
 * KL1 — GII-Import Whitelist (AO/UStG/EStG, optional HGB).
 *
 * Usage:
 *   php bin/kichel-gii-import.php --dry-run
 *   php bin/kichel-gii-import.php --laws=AO,UStG,EStG
 *   php bin/kichel-gii-import.php --toc=path/to/gii-toc.xml --laws=UStG
 *   php bin/kichel-gii-import.php --xml=path.xml --law=UStG --slug=ustg_1980
 */
if (!defined('DG_ROOT')) {
    define('DG_ROOT', dirname(__DIR__));
}
require_once DG_ROOT . '/src/autoload.php';

$opts = getopt('', ['dry-run', 'laws:', 'toc:', 'xml:', 'law:', 'slug:', 'help']);
if (isset($opts['help'])) {
    echo "KL1 GII-Import\n";
    echo "  --dry-run          nur TOC auflösen, kein Download\n";
    echo "  --laws=AO,UStG,EStG  Whitelist (Default Pilot ohne HGB)\n";
    echo "  --toc=URL|Pfad     gii-toc.xml (Default Remote)\n";
    echo "  --xml=Pfad --law=UStG --slug=ustg_1980  Offline-Parse einer XML\n";
    exit(0);
}

try {
    if (isset($opts['xml'])) {
        $law = (string) ($opts['law'] ?? '');
        $slug = (string) ($opts['slug'] ?? '');
        if ($law === '' || $slug === '') {
            throw new InvalidArgumentException('--xml braucht --law und --slug');
        }
        $chunks = KichelGiiImporter::importLawFromXmlFile((string) $opts['xml'], $law, $slug);
        $written = KichelGiiImporter::writeChunks($law, $chunks, [
            'slug' => $slug,
            'mode' => 'offline-xml',
            'xml' => (string) $opts['xml'],
        ]);
        echo json_encode([
            'ok' => true,
            'mode' => 'xml',
            'law' => $law,
            'chunk_count' => $written['chunk_count'],
            'jsonl' => $written['jsonl'],
            'manifest' => $written['manifest'],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . "\n";
        exit(0);
    }

    $laws = null;
    if (isset($opts['laws'])) {
        $laws = array_values(array_filter(array_map('trim', explode(',', (string) $opts['laws']))));
    }
    $toc = isset($opts['toc']) ? (string) $opts['toc'] : null;
    $dry = isset($opts['dry-run']);
    $report = KichelGiiImporter::run($laws, $toc, !$dry);
    echo json_encode([
        'ok' => true,
        'dry_run' => $dry,
        'resolved' => $report['resolved'],
        'results' => $report['results'],
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . "\n";
    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, 'FAIL: ' . $e->getMessage() . "\n");
    exit(1);
}
