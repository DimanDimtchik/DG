<?php
define('DG_ROOT', dirname(__DIR__));
require DG_ROOT . '/src/autoload.php';
require DG_ROOT . '/src/App.php';
echo json_encode(FileIntegrity::generateManifest(), JSON_PRETTY_PRINT) . "\n";
