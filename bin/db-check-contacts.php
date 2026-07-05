<?php
declare(strict_types=1);
require dirname(__DIR__) . '/bootstrap.php';
try {
    $pdo = Database::pdo();
    $count = (int) $pdo->query('SELECT COUNT(*) FROM dg_contacts')->fetchColumn();
    echo "contacts={$count}\n";
    $rows = $pdo->query('SELECT id, login, display_name FROM dg_contacts ORDER BY id DESC LIMIT 5')->fetchAll();
    foreach ($rows as $row) {
        echo $row['id'] . ' ' . $row['login'] . ' ' . $row['display_name'] . "\n";
    }
} catch (Throwable $e) {
    echo 'ERROR: ' . $e->getMessage() . "\n";
    exit(1);
}
