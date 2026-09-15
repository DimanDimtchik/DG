<?php
declare(strict_types=1);

define('DG_ROOT', dirname(__DIR__));
require DG_ROOT . '/bootstrap.php';

$user = UserRepository::findById(1);
if (!$user) {
    fwrite(STDERR, "Kein Test-User (id=1).\n");
    exit(1);
}

$query = $argv[1] ?? 'Wo trage ich die USt-ID ein?';
$result = KichelAssistant::answer($user, $query);

echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
