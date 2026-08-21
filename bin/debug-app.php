<?php
error_reporting(E_ALL);
ini_set('display_errors', '1');

define('DG_ROOT', dirname(__DIR__));
require DG_ROOT . '/src/autoload.php';
require DG_ROOT . '/src/App.php';

echo "DB configured: " . (Database::isConfigured() ? 'yes' : 'no') . "\n";

try {
    $pdo = Database::pdo();
    echo "DB connected OK\n";
    
    // Check what tables exist
    $tables = $pdo->query("SHOW TABLES LIKE 'dg_%'")->fetchAll(PDO::FETCH_COLUMN);
    echo "Tables: " . implode(', ', $tables) . "\n";
    
    // Try to load the app page
    $_SERVER['REQUEST_URI'] = '/app';
    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_SERVER['HTTP_HOST'] = 'ganz-soft.de';
    
    // Simulate logged in user
    $_SESSION['dg_user_id'] = 1;
    
    $user = AuthService::user();
    echo "User: " . ($user ? $user->username : 'null') . "\n";
    
    if ($user) {
        echo "Home path: " . RoleResolver::homePath($user) . "\n";
        echo "Menu items: ";
        $items = MenuRegistry::forUser($user);
        echo count($items) . " items\n";
    }
} catch (Throwable $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
    echo $e->getTraceAsString() . "\n";
}
