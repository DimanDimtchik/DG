<?php
declare(strict_types=1);

/**
 * HTTP-Cron für Kasserver KAS (Tools → Cronjobs).
 * Aufruf: https://dg.ganz-om.de/cron.php?job=employee-retention&token=…
 */
require __DIR__ . '/bootstrap.php';

header('Content-Type: text/plain; charset=utf-8');

if (($_GET['job'] ?? '') === 'employee-retention') {
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
    exit;
}

if (($_GET['job'] ?? '') === 'dunning-auto') {
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

    $result = DunningService::runAutomatic();
    echo date('c') . " dunning-auto: gesendet={$result['sent']}, übersprungen={$result['skipped']}\n";
    foreach ($result['errors'] as $error) {
        echo "FEHLER: {$error}\n";
    }
    exit;
}

if (($_GET['job'] ?? '') === 'overtime-reminder') {
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

    $result = OvertimeReminderService::runAutomatic();
    echo date('c') . " overtime-reminder: gesendet={$result['sent']}, übersprungen={$result['skipped']}\n";
    foreach ($result['errors'] as $error) {
        echo "FEHLER: {$error}\n";
    }
    exit;
}

if (($_GET['job'] ?? '') === 'tld-list-update') {
    if (!CronSettings::isAuthorized($_GET['token'] ?? null)) {
        http_response_code(403);
        echo "Forbidden.\n";
        exit;
    }

    try {
        $force = isset($_GET['force']) && (string) $_GET['force'] === '1';
        $result = WebsiteTldListUpdater::refresh($force);
        echo date('c') . ' tld-list-update: ' . $result['message'] . "\n";
        echo 'version=' . ($result['remote_version'] ?? '') . ' path=' . $result['path'] . "\n";
    } catch (Throwable $e) {
        http_response_code(500);
        echo date('c') . ' tld-list-update FEHLER: ' . $e->getMessage() . "\n";
    }
    exit;
}

http_response_code(404);
echo "Unbekannter Job.\n";
