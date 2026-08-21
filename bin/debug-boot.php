<?php
error_reporting(E_ALL);
ini_set('display_errors', '1');
define('DG_ROOT', dirname(__DIR__));
require DG_ROOT . '/src/autoload.php';
require DG_ROOT . '/src/App.php';

echo "Boot start\n";
try {
    App::boot();
    echo "Boot OK\n";
} catch (Throwable $e) {
    echo "Boot FAIL: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
    echo $e->getTraceAsString() . "\n";
}
