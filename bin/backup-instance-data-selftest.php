<?php
declare(strict_types=1);

/**
 * Selbsttest fuer Instanzdaten-Backups ohne echte Produktivdatenbank.
 */
$root = sys_get_temp_dir() . '/dg-backup-selftest-' . bin2hex(random_bytes(4));
if (!@mkdir($root . '/storage/vouchers/42', 0770, true)
    || !@mkdir($root . '/storage/complaints/7', 0770, true)
    || !@mkdir($root . '/storage/backups/old-backup', 0770, true)
    || !@mkdir($root . '/storage/cache/tmp', 0770, true)
    || !@mkdir($root . '/config', 0770, true)) {
    fwrite(STDERR, "FEHLER: Testverzeichnis konnte nicht erstellt werden.\n");
    exit(1);
}

define('DG_ROOT', $root);
require dirname(__DIR__) . '/src/Backup/BackupService.php';

function assertBackup(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "FEHLER: {$message}\n");
        cleanupBackupSelftest(DG_ROOT);
        exit(1);
    }
    echo "OK: {$message}\n";
}

function cleanupBackupSelftest(string $dir): void
{
    if (!is_dir($dir)) {
        return;
    }
    $items = scandir($dir);
    if ($items === false) {
        return;
    }
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }
        $path = $dir . '/' . $item;
        is_dir($path) ? cleanupBackupSelftest($path) : @unlink($path);
    }
    @rmdir($dir);
}

file_put_contents($root . '/storage/vouchers/42/rechnung.pdf', 'voucher');
file_put_contents($root . '/storage/complaints/7/foto.jpg', 'complaint');
file_put_contents($root . '/storage/backup-state.json', '{"last_status":"ok"}');
file_put_contents($root . '/storage/backups/old-backup/database.sql', 'old-backup');
file_put_contents($root . '/storage/cache/tmp/cache.txt', 'cache');
file_put_contents($root . '/config/database.local.php', '<?php return ["database" => "kunde"];');
file_put_contents($root . '/config/cron.local.php', '<?php return ["token" => "secret"];');
file_put_contents($root . '/config/mail.local.php.example', '<?php return [];');

$backupPath = $root . '/result';
mkdir($backupPath, 0770, true);
$method = new ReflectionMethod(BackupService::class, 'backupFiles');
$method->setAccessible(true);
$method->invoke(null, $backupPath);

assertBackup(is_file($backupPath . '/files/storage/vouchers/42/rechnung.pdf'), 'Belegdateien aus storage/vouchers werden gesichert');
assertBackup(is_file($backupPath . '/files/storage/complaints/7/foto.jpg'), 'weitere Instanzdateien aus storage werden gesichert');
assertBackup(is_file($backupPath . '/files/storage/backup-state.json'), 'State-Dateien direkt unter storage werden gesichert');
assertBackup(!file_exists($backupPath . '/files/storage/backups/old-backup/database.sql'), 'bestehende Backups werden nicht rekursiv gesichert');
assertBackup(!file_exists($backupPath . '/files/storage/cache/tmp/cache.txt'), 'Cache-Dateien werden nicht gesichert');
assertBackup(is_file($backupPath . '/files/config/database.local.php'), 'database.local.php wird gesichert');
assertBackup(is_file($backupPath . '/files/config/cron.local.php'), 'weitere lokale Configs werden gesichert');
assertBackup(!file_exists($backupPath . '/files/config/mail.local.php.example'), 'Config-Beispiele werden nicht gesichert');

cleanupBackupSelftest($root);
echo "Fertig.\n";
