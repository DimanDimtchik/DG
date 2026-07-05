<?php
declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap.php';

MigrationRunner::runPending();

$pdo = Database::pdo();

$missing = [
    'dg_settings' => '009_settings.sql',
    'dg_mail_log' => '008_mail_log.sql',
];

foreach ($missing as $table => $migration) {
    $exists = $pdo->query("SHOW TABLES LIKE " . $pdo->quote($table))->fetchColumn();
    if ($exists) {
        echo "OK {$table}\n";
        continue;
    }

    $stmt = $pdo->prepare('DELETE FROM dg_migrations WHERE id = :id');
    $stmt->execute(['id' => $migration]);

    $count = MigrationRunner::runPending();
    echo "Re-applied {$migration} ({$count} migration(s))\n";
}
