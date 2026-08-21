<?php
declare(strict_types=1);

/**
 * Daily automatic backup of database and customer-specific files.
 *
 * Backs up:
 * - Full database dump (mysqldump via exec or PDO fallback)
 * - storage/ directory (media, mail archives, contacts, logs)
 * - config/database.local.php, config/app.local.php
 *
 * Does NOT back up general CRM code (src/, views/, assets/) — those come from the update system.
 */
final class BackupService
{
    private const STATE_FILE = '/storage/backup-state.json';
    private const BACKUP_DIR = '/storage/backups';
    private const MAX_BACKUPS = 14; // keep 2 weeks

    /**
     * Run daily backup if due (called from App::boot).
     */
    public static function runIfDue(): void
    {
        if (!Database::isConfigured()) {
            return;
        }

        $state = self::loadState();
        $today = date('Y-m-d');

        if (($state['last_backup'] ?? '') === $today) {
            return;
        }

        try {
            self::createBackup();
            $state['last_backup'] = $today;
            $state['last_status'] = 'ok';
            $state['last_error'] = null;
        } catch (Throwable $e) {
            $state['last_status'] = 'error';
            $state['last_error'] = $e->getMessage();
        }

        self::saveState($state);
    }

    /**
     * Create a full backup (DB + files).
     *
     * @return string Absoluter Pfad zum Backup-Ordner (timestamp).
     * @throws RuntimeException Bei Fehlern beim Anlegen.
     */
    public static function createBackup(): string
    {
        $dir = self::ensureBackupDir();
        $timestamp = date('Y-m-d_His');
        $backupPath = $dir . '/' . $timestamp;

        if (!mkdir($backupPath, 0750, true)) {
            throw new RuntimeException("Backup-Verzeichnis konnte nicht erstellt werden: $backupPath");
        }

        self::backupDatabase($backupPath);
        self::backupFiles($backupPath);
        self::cleanOldBackups($dir);

        return $backupPath;
    }

    /**
     * @return array{last_backup: string|null, last_status: string|null, last_error: string|null, backups: list<array{date: string, size: int}>}
     */
    public static function info(): array
    {
        $state = self::loadState();
        $dir = DG_ROOT . self::BACKUP_DIR;
        $backups = [];

        if (is_dir($dir)) {
            $entries = array_diff(scandir($dir, SCANDIR_SORT_DESCENDING) ?: [], ['.', '..']);
            foreach ($entries as $entry) {
                $path = $dir . '/' . $entry;
                if (is_dir($path)) {
                    $backups[] = [
                        'date' => $entry,
                        'size' => self::dirSize($path),
                    ];
                }
            }
        }

        return [
            'last_backup' => $state['last_backup'] ?? null,
            'last_status' => $state['last_status'] ?? null,
            'last_error' => $state['last_error'] ?? null,
            'backups' => $backups,
        ];
    }

    // ── Database backup ─────────────────────────────────────────────

    /** mysqldump oder PDO-Fallback für database.sql. */
    private static function backupDatabase(string $backupPath): void
    {
        $dbConfig = require DG_ROOT . '/config/database.local.php';
        $host = $dbConfig['host'] ?? 'localhost';
        $name = $dbConfig['name'] ?? '';
        $user = $dbConfig['user'] ?? '';
        $pass = $dbConfig['pass'] ?? '';
        $file = $backupPath . '/database.sql';

        if (self::hasMysqldump()) {
            $cmd = sprintf(
                'mysqldump --host=%s --user=%s --password=%s --single-transaction --routines --triggers %s > %s 2>&1',
                escapeshellarg($host),
                escapeshellarg($user),
                escapeshellarg($pass),
                escapeshellarg($name),
                escapeshellarg($file)
            );
            exec($cmd, $output, $exitCode);
            if ($exitCode === 0 && file_exists($file) && filesize($file) > 0) {
                return;
            }
        }

        // PDO fallback: export all tables
        self::pdoDump($file);
    }

    /** @return bool */
    private static function hasMysqldump(): bool
    {
        $result = @exec('which mysqldump 2>/dev/null || where mysqldump 2>NUL', $output, $code);
        return $code === 0 && !empty($output);
    }

    /**
     * Exportiert alle Tabellen per PDO in eine SQL-Datei.
     *
     * @throws RuntimeException Wenn die Datei nicht geöffnet werden kann.
     */
    private static function pdoDump(string $file): void
    {
        $pdo = Database::pdo();
        $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
        $fp = fopen($file, 'w');
        if ($fp === false) {
            throw new RuntimeException('Backup-Datei konnte nicht erstellt werden.');
        }

        fwrite($fp, "-- DG CRM Backup " . date('Y-m-d H:i:s') . "\n");
        fwrite($fp, "SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS = 0;\n\n");

        foreach ($tables as $table) {
            $create = $pdo->query("SHOW CREATE TABLE `$table`")->fetch(PDO::FETCH_ASSOC);
            fwrite($fp, "DROP TABLE IF EXISTS `$table`;\n");
            fwrite($fp, ($create['Create Table'] ?? '') . ";\n\n");

            $rows = $pdo->query("SELECT * FROM `$table`");
            while ($row = $rows->fetch(PDO::FETCH_ASSOC)) {
                $values = array_map(fn($v) => $v === null ? 'NULL' : $pdo->quote($v), $row);
                fwrite($fp, "INSERT INTO `$table` VALUES (" . implode(',', $values) . ");\n");
            }
            fwrite($fp, "\n");
        }

        fwrite($fp, "SET FOREIGN_KEY_CHECKS = 1;\n");
        fclose($fp);
    }

    // ── File backup ─────────────────────────────────────────────────

    /** Kopiert storage/* und lokale Config-Dateien in files/. */
    private static function backupFiles(string $backupPath): void
    {
        $filesDir = $backupPath . '/files';
        mkdir($filesDir, 0750, true);

        $customerDirs = ['storage/media', 'storage/mail', 'storage/contacts', 'storage/logs'];
        foreach ($customerDirs as $relPath) {
            $src = DG_ROOT . '/' . $relPath;
            if (is_dir($src)) {
                self::copyDir($src, $filesDir . '/' . $relPath);
            }
        }

        $configFiles = ['config/database.local.php', 'config/app.local.php'];
        foreach ($configFiles as $relPath) {
            $src = DG_ROOT . '/' . $relPath;
            if (file_exists($src)) {
                $destDir = $filesDir . '/' . dirname($relPath);
                if (!is_dir($destDir)) {
                    mkdir($destDir, 0750, true);
                }
                copy($src, $filesDir . '/' . $relPath);
            }
        }
    }

    // ── Cleanup ─────────────────────────────────────────────────────

    /** Behält nur die neuesten MAX_BACKUPS Verzeichnisse. */
    private static function cleanOldBackups(string $dir): void
    {
        $entries = array_diff(scandir($dir, SCANDIR_SORT_DESCENDING) ?: [], ['.', '..']);
        $kept = 0;
        foreach ($entries as $entry) {
            $path = $dir . '/' . $entry;
            if (!is_dir($path)) continue;
            $kept++;
            if ($kept > self::MAX_BACKUPS) {
                self::removeDir($path);
            }
        }
    }

    // ── Helpers ─────────────────────────────────────────────────────

    /** @return string Absoluter Backup-Basisordner. */
    private static function ensureBackupDir(): string
    {
        $dir = DG_ROOT . self::BACKUP_DIR;
        if (!is_dir($dir)) {
            mkdir($dir, 0750, true);
            file_put_contents($dir . '/.htaccess', "Deny from all\n");
        }
        return $dir;
    }

    /** Rekursives Kopieren eines Verzeichnisses. */
    private static function copyDir(string $src, string $dst): void
    {
        if (!is_dir($dst)) {
            mkdir($dst, 0750, true);
        }
        $items = scandir($src);
        if ($items === false) return;

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') continue;
            $s = $src . '/' . $item;
            $d = $dst . '/' . $item;
            if (is_dir($s)) {
                self::copyDir($s, $d);
            } else {
                copy($s, $d);
            }
        }
    }

    /** Rekursives Löschen eines Verzeichnisses. */
    private static function removeDir(string $dir): void
    {
        if (!is_dir($dir)) return;
        $items = scandir($dir);
        if ($items === false) return;

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') continue;
            $path = $dir . '/' . $item;
            is_dir($path) ? self::removeDir($path) : unlink($path);
        }
        rmdir($dir);
    }

    /** Gesamtgröße eines Verzeichnisses in Bytes. */
    private static function dirSize(string $dir): int
    {
        $size = 0;
        $items = scandir($dir);
        if ($items === false) return 0;

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') continue;
            $path = $dir . '/' . $item;
            $size += is_dir($path) ? self::dirSize($path) : (int) filesize($path);
        }
        return $size;
    }

    /** @return array<string, mixed> */
    private static function loadState(): array
    {
        $file = DG_ROOT . self::STATE_FILE;
        if (!file_exists($file)) return [];
        $data = json_decode((string) file_get_contents($file), true);
        return is_array($data) ? $data : [];
    }

    /** @param array<string, mixed> $state */
    private static function saveState(array $state): void
    {
        $file = DG_ROOT . self::STATE_FILE;
        file_put_contents($file, json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }
}
