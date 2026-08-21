#!/usr/bin/env php
<?php
/**
 * Wartungsmodus auf einer CRM-Instanz aus- oder einschalten.
 * Usage: php bin/set-website-maintenance.php [--on|--off]
 */
declare(strict_types=1);

define('DG_ROOT', dirname(__DIR__));
require_once DG_ROOT . '/src/autoload.php';

if (!Database::isConfigured()) {
    fwrite(STDERR, "ERROR: Datenbank nicht konfiguriert.\n");
    exit(1);
}

$on = in_array('--on', $argv, true);
$off = in_array('--off', $argv, true);
if ($on && $off) {
    fwrite(STDERR, "Nur --on oder --off angeben.\n");
    exit(1);
}
if (!$on && !$off) {
    $off = true;
}

$enabled = $on;
MigrationRunner::runPending();
$current = WebsiteMaintenanceSettings::config();
WebsiteMaintenanceSettings::save([
    'enabled' => $enabled ? '1' : '',
    'headline' => $current['headline'],
    'message' => $current['message'],
    'email' => $current['email'],
]);

echo ($enabled ? 'Wartungsmodus EIN' : 'Wartungsmodus AUS') . ' auf ' . DG_ROOT . PHP_EOL;
