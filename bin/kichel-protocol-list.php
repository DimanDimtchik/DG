<?php
declare(strict_types=1);

/**
 * Kichel-Protokoll als JSON (CLI auf dem CRM-Server).
 *
 *   php bin/kichel-protocol-list.php [limit] [offset]
 */
if (!defined('DG_ROOT')) {
    define('DG_ROOT', dirname(__DIR__));
}
require_once DG_ROOT . '/src/autoload.php';
MigrationRunner::runPending();

$limit = isset($argv[1]) ? max(1, min(500, (int) $argv[1])) : 50;
$offset = isset($argv[2]) ? max(0, (int) $argv[2]) : 0;

if (!Database::isConfigured()) {
    fwrite(STDERR, "Datenbank nicht konfiguriert.\n");
    exit(1);
}

$rows = KichelProtocolRepository::recent($limit, $offset);

echo json_encode([
    'success' => true,
    'instance' => (string) ($_SERVER['HTTP_HOST'] ?? gethostname() ?: 'cli'),
    'count' => count($rows),
    'limit' => $limit,
    'offset' => $offset,
    'rows' => array_map(static function (array $row): array {
        return [
            'id' => (int) ($row['id'] ?? 0),
            'created_at' => (string) ($row['created_at'] ?? ''),
            'user_id' => isset($row['user_id']) ? (int) $row['user_id'] : null,
            'username' => (string) ($row['username'] ?? ''),
            'display_name' => (string) ($row['display_name'] ?? ''),
            'query' => (string) ($row['query_text'] ?? ''),
            'answer' => (string) ($row['answer_text'] ?? ''),
        ];
    }, $rows),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
