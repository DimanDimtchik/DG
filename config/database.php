<?php
declare(strict_types=1);

/**
 * MariaDB/MySQL – Zugangsdaten aus KAS (All-Inkl).
 * Passwort nur in config/database.local.php (nicht in Git).
 */
$defaults = [
    'host' => 'localhost',
    'port' => 3306,
    'database' => 'd0477ae6',
    'username' => 'd0477ae6',
    'password' => '',
    'charset' => 'utf8mb4',
    'collation' => 'utf8mb4_unicode_ci',
];

$local = DG_ROOT . '/config/database.local.php';
if (is_readable($local)) {
    $overrides = require $local;
    if (is_array($overrides)) {
        $defaults = array_merge($defaults, $overrides);
    }
}

return $defaults;
