<?php
declare(strict_types=1);

/**
 * HTTP-Cron für Kasserver KAS (Tools → Cronjobs).
 * Aufruf: https://dg.ganz-om.de/cron.php?job=employee-retention&token=…
 */
require __DIR__ . '/bootstrap.php';

header('Content-Type: text/plain; charset=utf-8');

if (($_GET['job'] ?? '') !== 'employee-retention') {
    http_response_code(404);
    echo "Unbekannter Job.\n";
    exit;
}

if (!CronSettings::isAuthorized($_GET['token'] ?? null)) {
    http_response_code(403);
    echo "Forbidden.\n";
    exit;
}

if (!Database::isConfigured()) {
    http_response_code(500);
    echo "Datenbank nicht konfiguriert.\n";
    exit;
}

$count = EmployeeRetentionService::purgeAllExpired();
echo date('c') . " employee-retention bereinigt: {$count}\n";
