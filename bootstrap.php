<?php
declare(strict_types=1);

define('DG_ROOT', __DIR__);

require_once DG_ROOT . '/src/autoload.php';
require_once DG_ROOT . '/src/App.php';

App::boot();
