<?php
define('DG_ROOT', dirname(__DIR__));
require DG_ROOT . '/src/autoload.php';
require DG_ROOT . '/src/App.php';
try {
    $pdo = Database::pdo();
    $pdo->exec('DELETE FROM dg_login_attempts');
    echo "Login attempts cleared\n";
} catch (Throwable $e) {
    echo 'Error: ' . $e->getMessage() . "\n";
}
