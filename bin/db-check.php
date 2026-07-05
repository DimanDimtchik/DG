<?php
declare(strict_types=1);

/**
 * Verbindung testen: php bin/db-check.php
 */
require dirname(__DIR__) . '/bootstrap.php';

try {
    $pdo = Database::pdo();
    $version = $pdo->query('SELECT VERSION()')->fetchColumn();
    echo "OK – verbunden mit MariaDB/MySQL {$version}\n";
    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, 'FEHLER: ' . $e->getMessage() . "\n");
    exit(1);
}
