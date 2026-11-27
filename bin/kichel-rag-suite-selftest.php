<?php
declare(strict_types=1);

/**
 * KR8 — Suite: alle KR-Selftests nacheinander.
 * Usage: php bin/kichel-rag-suite-selftest.php
 */
if (!defined('DG_ROOT')) {
    define('DG_ROOT', dirname(__DIR__));
}

$scripts = [
    'kichel-rag-fact-selftest.php',
    'kichel-retriever-selftest.php',
    'kichel-corpus-selftest.php',
    'kichel-ranker-selftest.php',
    'kichel-sources-selftest.php',
    'kichel-pipeline-selftest.php',
    'kichel-rag-smoke-selftest.php',
    'kichel-gii-import-selftest.php',
    'kichel-statute-index-selftest.php',
    'kichel-statute-retriever-selftest.php',
];

$failed = 0;
foreach ($scripts as $script) {
    $path = DG_ROOT . '/bin/' . $script;
    echo "==== {$script} ====\n";
    $cmd = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($path);
    passthru($cmd, $code);
    if ($code !== 0) {
        $failed++;
        echo "SUITE FAIL: {$script} exit {$code}\n";
    }
    echo "\n";
}

echo $failed === 0 ? "SUITE ALL OK\n" : "SUITE FAILED ({$failed})\n";
exit($failed === 0 ? 0 : 1);
