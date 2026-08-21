<?php
define('DG_ROOT', dirname(__DIR__));
require DG_ROOT . '/src/autoload.php';
require DG_ROOT . '/src/App.php';

$pdo = Database::pdo();
$hash = password_hash('Globus01+', PASSWORD_DEFAULT);

$stmt = $pdo->prepare(
    'INSERT INTO dg_users (username, password_hash, email, display_name, role, employee_active)
     VALUES (:u, :h, :e, :d, :r, 0)
     ON DUPLICATE KEY UPDATE password_hash = :h2'
);

$stmt->execute([
    'u' => 'admin', 'h' => $hash, 'e' => '', 'd' => 'Administrator', 'r' => 'administrator', 'h2' => $hash
]);
echo "admin: OK (id=" . $pdo->lastInsertId() . ")\n";

$stmt->execute([
    'u' => 'info@ganz-om.de', 'h' => $hash, 'e' => 'info@ganz-om.de', 'd' => 'Dietrich Ganz', 'r' => 'administrator', 'h2' => $hash
]);
echo "info@ganz-om.de: OK (id=" . $pdo->lastInsertId() . ")\n";

$count = $pdo->query('SELECT COUNT(*) FROM dg_users')->fetchColumn();
echo "Total users in DB: $count\n";
