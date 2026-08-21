<?php
declare(strict_types=1);
define('DG_ROOT', dirname(__DIR__));
require_once DG_ROOT . '/src/autoload.php';
MigrationRunner::runPending();
echo "MIGRATIONS DONE\n";
