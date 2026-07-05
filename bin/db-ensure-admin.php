<?php
declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap.php';

$pdo = Database::pdo();
$hash = password_hash('demo', PASSWORD_DEFAULT);

$stmt = $pdo->prepare(
    'INSERT INTO dg_users (username, password_hash, email, display_name, role, employee_active)
     VALUES (:username, :password_hash, :email, :display_name, :role, 1)
     ON DUPLICATE KEY UPDATE role = VALUES(role), employee_active = 1, password_hash = VALUES(password_hash)'
);
$stmt->execute([
    'username' => 'admin',
    'password_hash' => $hash,
    'email' => 'admin@ganz-om.de',
    'display_name' => 'Dietrich Ganz',
    'role' => 'administrator',
]);

echo "Administrator (admin / demo) sichergestellt.\n";
