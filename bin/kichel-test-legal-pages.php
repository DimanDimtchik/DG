<?php
declare(strict_types=1);

/**
 * CLI: Kichel Pflichtseiten-Antwort testen (ohne HTTP/Session).
 * Usage: php bin/kichel-test-legal-pages.php [query]
 */
if (!defined('DG_ROOT')) {
    define('DG_ROOT', dirname(__DIR__));
}
require_once DG_ROOT . '/src/autoload.php';

$query = $argv[1] ?? 'Wo finde ich Pflichtseiten?';
$tokens = preg_split('/\s+/u', mb_strtolower(trim($query), 'UTF-8'), -1, PREG_SPLIT_NO_EMPTY) ?: [];

$result = KichelLegalPages::tryAnswer($query, $tokens);

if ($result === null) {
    fwrite(STDERR, "tryAnswer returned null\n");
    exit(1);
}

echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
