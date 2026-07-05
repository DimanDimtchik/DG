<?php
declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap.php';

$pdo = Database::pdo();
echo "Migrations:\n";
foreach ($pdo->query('SELECT id FROM dg_migrations ORDER BY id') as $row) {
    echo '  ' . $row['id'] . "\n";
}
echo "Tables:\n";
foreach ($pdo->query("SHOW TABLES LIKE 'dg_%'") as $row) {
    echo '  ' . array_values($row)[0] . "\n";
}
