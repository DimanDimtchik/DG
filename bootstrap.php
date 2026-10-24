<?php
declare(strict_types=1);

define('DG_ROOT', __DIR__);

$composerAutoload = DG_ROOT . '/vendor/autoload.php';
if (is_readable($composerAutoload)) {
    require_once $composerAutoload;
}

require_once DG_ROOT . '/src/autoload.php';
require_once DG_ROOT . '/src/App.php';

App::boot();
