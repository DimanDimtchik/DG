<?php
declare(strict_types=1);

/**
 * Migrationen aus database/migrations/*.sql ausführen.
 * php bin/db-migrate.php
 */
require dirname(__DIR__) . '/bootstrap.php';

$count = MigrationRunner::runPending();
echo $count > 0
    ? "{$count} ausstehende Migration(en) ausgeführt.\n"
    : "Keine ausstehenden Migrationen.\n";
