<?php
declare(strict_types=1);
require dirname(__DIR__) . '/bootstrap.php';
$pdo = Database::pdo();
echo "USERS\n";
foreach ($pdo->query('SELECT id, username, email, role FROM dg_users ORDER BY username') as $row) {
    echo (int)$row['id'] . '|' . $row['username'] . '|' . $row['role'] . "\n";
}
$username = $argv[1] ?? 'admin';
$temp = $argv[2] ?? bin2hex(random_bytes(8));
$hash = password_hash($temp, PASSWORD_DEFAULT);
$stmt = $pdo->prepare('UPDATE dg_users SET password_hash = :hash WHERE username = :username');
$stmt->execute(['hash' => $hash, 'username' => $username]);
if ($stmt->rowCount() < 1) {
    fwrite(STDERR, "User not found: $username\n");
    exit(1);
}
echo "RESET|$username|$temp\n";