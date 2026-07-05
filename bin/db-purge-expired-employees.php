<?php
declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap.php';

if (!Database::isConfigured()) {
    fwrite(STDERR, "Datenbank nicht konfiguriert.\n");
    exit(1);
}

$count = EmployeeRetentionService::purgeAllExpired();
echo "Abgelaufene Mitarbeiterdaten bereinigt: {$count}\n";
