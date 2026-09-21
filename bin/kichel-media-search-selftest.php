<?php
declare(strict_types=1);

/**
 * Schnelltest Kichel-Media-Suche.
 * php bin/kichel-media-search-selftest.php "Video lager"
 */
if (!defined('DG_ROOT')) {
    define('DG_ROOT', dirname(__DIR__));
}
require_once DG_ROOT . '/src/autoload.php';
MigrationRunner::runPending();

$query = trim((string) ($argv[1] ?? 'Video lager'));
$userId = (int) (Database::pdo()->query(
    "SELECT id FROM dg_users WHERE role IN ('administrator','admin') ORDER BY id LIMIT 1"
)->fetchColumn() ?: 0);
$user = UserRepository::findById($userId);
if ($user === null) {
    fwrite(STDERR, "Kein Admin.\n");
    exit(1);
}

$res = KichelAssistant::answer($user, $query);
echo json_encode([
    'query' => $query,
    'kind' => $res['kind'] ?? null,
    'answer' => $res['answer'] ?? '',
    'action_links' => $res['action_links'] ?? [],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
