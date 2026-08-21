<?php
error_reporting(E_ALL);
ini_set('display_errors', '1');

define('DG_ROOT', dirname(__DIR__));
require DG_ROOT . '/src/autoload.php';
require DG_ROOT . '/src/App.php';

$_SERVER['REQUEST_METHOD'] = 'POST';
$_SERVER['REQUEST_URI'] = '/login';
$_POST['username'] = 'admin';
$_POST['password'] = 'Globus01+';

echo "Testing login...\n";
try {
    $user = UserRepository::findByEmailOrUsername('admin');
    if ($user) {
        echo "User found: " . $user->username . " (id=" . $user->id . ")\n";
        $ok = UserRepository::verifyPassword($user, 'Globus01+');
        echo "Password verify: " . ($ok ? 'OK' : 'FAIL') . "\n";
    } else {
        echo "User NOT found\n";
    }

    echo "Checking MigrationRunner...\n";
    MigrationRunner::runPending();
    echo "Migrations OK\n";
} catch (Throwable $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
}
