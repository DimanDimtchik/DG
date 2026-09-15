#!/usr/bin/env php
<?php
declare(strict_types=1);

/**
 * Akademie: Module ohne MP4 deaktivieren, Dauer aus *.meta.json aktualisieren.
 *
 * php bin/academy-sync-module-media.php
 */

if (!defined('DG_ROOT')) {
    define('DG_ROOT', dirname(__DIR__));
}
require_once DG_ROOT . '/src/autoload.php';

MigrationRunner::runPending();

$result = AcademyRepository::syncModuleMediaFromDisk(true);
echo 'Deaktiviert (keine MP4): ' . $result['deactivated'] . PHP_EOL;
echo 'Dauer aktualisiert: ' . $result['duration_updated'] . PHP_EOL;
