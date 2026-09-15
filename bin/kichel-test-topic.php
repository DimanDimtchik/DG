<?php
declare(strict_types=1);

/**
 * CLI: Kichel-Antwort für eine Beispielfrage testen.
 * Usage: php bin/kichel-test-topic.php "Wo trage ich die USt-ID ein?"
 */
if (!defined('DG_ROOT')) {
    define('DG_ROOT', dirname(__DIR__));
}
require_once DG_ROOT . '/src/autoload.php';

$query = $argv[1] ?? 'Wo trage ich die USt-ID ein?';
$adminRole = (string) App::config('roles.admin', 'administrator');
$user = new User(1, 'admin', 'Administrator', '', [$adminRole], true);
$result = KichelAssistant::answer($user, $query);

echo json_encode([
    'query' => $query,
    'answer' => $result['answer'] ?? '',
    'action_links' => $result['action_links'] ?? [],
    'follow_up' => $result['follow_up'] ?? '',
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
