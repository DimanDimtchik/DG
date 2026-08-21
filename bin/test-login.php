<?php
$hash = '$2y$10$u1VUEdM.r8m5ltmaFsyD7.klT/vySRa9py6y6PQjFOIaa2d54uw3q';
$pw = 'Globus01+';
echo 'verify: ' . (password_verify($pw, $hash) ? 'OK' : 'FAIL') . "\n";

define('DG_ROOT', dirname(__DIR__));
require DG_ROOT . '/src/autoload.php';

$users = require DG_ROOT . '/config/users.php';
foreach ($users as $u) {
    $match = password_verify($pw, $u['password']);
    echo 'User: ' . $u['username'] . ' => ' . ($match ? 'OK' : 'FAIL') . "\n";
}

// Check if DB has dg_login_attempts and if IP is blocked
require DG_ROOT . '/src/App.php';
if (Database::isConfigured()) {
    try {
        $pdo = Database::connection();
        $stmt = $pdo->query('SELECT ip, COUNT(*) as cnt FROM dg_login_attempts WHERE success=0 GROUP BY ip');
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo "Blocked IPs:\n";
        foreach ($rows as $r) {
            echo "  {$r['ip']}: {$r['cnt']} failures\n";
        }
    } catch (Throwable $e) {
        echo 'DB: ' . $e->getMessage() . "\n";
    }
} else {
    echo "DB not configured\n";
}
