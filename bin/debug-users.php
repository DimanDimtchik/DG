<?php
declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap.php';

header('Content-Type: text/plain; charset=utf-8');

$users = Database::pdo()->query('SELECT id, username, email FROM dg_users LIMIT 5')->fetchAll(PDO::FETCH_ASSOC);
print_r($users);
