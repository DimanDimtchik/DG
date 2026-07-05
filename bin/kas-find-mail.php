<?php
declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap.php';

sleep(3);
$email = 'd.ganz@ganz-om.de';
$row = KasMailProvisioner::findMailAccountByEmail($email);
var_export($row);
echo PHP_EOL;
