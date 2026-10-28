<?php
declare(strict_types=1);

/**
 * Rendert die Backup-Einstellungsmaske ohne Browser und prueft zentrale Texte.
 */
define('DG_ROOT', dirname(__DIR__));
require DG_ROOT . '/src/autoload.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

function assertBackupUi(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "FEHLER: {$message}\n");
        exit(1);
    }
    echo "OK: {$message}\n";
}

$backupInfo = [
    'last_backup' => '2026-10-27',
    'last_status' => 'ok',
    'last_error' => null,
    'backups' => [
        ['date' => '2026-10-27_190000', 'size' => 2048],
    ],
];
$dbConnected = true;

ob_start();
require DG_ROOT . '/views/settings/tab-backup.php';
$html = (string) ob_get_clean();

assertBackupUi(str_contains($html, 'Instanzdaten-Backup'), 'UI erklaert Instanzdaten-Backup');
assertBackupUi(str_contains($html, 'Backup jetzt erstellen'), 'UI bietet manuellen Backup-Start');
assertBackupUi(str_contains($html, '2026-10-27_190000'), 'UI listet vorhandene Backups');
assertBackupUi(!str_contains($html, 'Restore jetzt'), 'UI bietet keinen direkten Restore-Button');

echo "Fertig.\n";
