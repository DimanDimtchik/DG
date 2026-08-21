<?php
declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '1');

$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI'] = '/';
$_SERVER['HTTP_HOST'] = 'ganz-soft.de';
$_SERVER['HTTPS'] = 'on';

require dirname(__DIR__) . '/index.php';
