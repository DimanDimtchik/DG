<?php
declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap.php';

$pdo = Database::pdo();
$exists = $pdo->prepare('SELECT id FROM dg_users WHERE username = :u LIMIT 1');
$exists->execute(['u' => 'kunde']);
if ($exists->fetchColumn()) {
    echo "Demo-Kunde bereits vorhanden.\n";
    exit(0);
}

$hash = password_hash('demo', PASSWORD_DEFAULT);
$stmt = $pdo->prepare(
    'INSERT INTO dg_users (username, password_hash, email, display_name, role, employee_active)
     VALUES (:username, :password_hash, :email, :display_name, :role, 0)'
);
$stmt->execute([
    'username' => 'kunde',
    'password_hash' => $hash,
    'email' => 'kunde@example.de',
    'display_name' => 'Karl Kunde',
    'role' => 'dg_kunde',
]);

echo "Demo-Kunde angelegt (kunde / demo).\n";
