<?php
declare(strict_types=1);

/**
 * Force-run CRM update once (for end-to-end tests).
 * Usage: php bin/force-update.php
 */
require dirname(__DIR__) . '/bootstrap.php';

echo "Current version: " . App::version() . PHP_EOL;

$state = UpdateChecker::getState();
$state['force_pending'] = true;
$stateFile = DG_ROOT . '/storage/update-state.json';
if (!is_dir(dirname($stateFile))) {
    mkdir(dirname($stateFile), 0755, true);
}
file_put_contents($stateFile, json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
echo "force_pending set\n";

UpdateChecker::runIfDue();

$after = UpdateChecker::getState();
echo "After:\n";
echo "  App::version = " . App::version() . PHP_EOL;
echo "  installed   = " . ($after['installed_version'] ?? '-') . PHP_EOL;
echo "  last_update = " . ($after['last_update'] ?? '-') . PHP_EOL;
echo "  last_error  = " . ($after['last_error'] ?? '-') . PHP_EOL;
echo "  force       = " . (!empty($after['force_pending']) ? 'yes' : 'no') . PHP_EOL;
