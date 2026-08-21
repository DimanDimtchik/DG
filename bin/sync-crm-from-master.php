<?php
declare(strict_types=1);

/**
 * Sync CRM code from dg.ganz-om.de master to other installs.
 * ROOT (ganz-soft.de live) gets a safe partial sync (never deletes sibling domains).
 * Dedicated CRM folders get a full code sync with local-config preservation.
 */
$root = '/www/htdocs/w0217246';
$master = $root . '/dg.ganz-om.de';

$localConfigNames = [
    'database.local.php',
    'app.local.php',
    'users.php',
    'license.php',
    'mail.local.php',
    'kas.local.php',
    'cron.local.php',
    'company.local.php',
];

$codeDirs = ['src', 'views', 'assets', 'database', 'admin', 'update', 'plugins'];
$codeFiles = [
    'index.php',
    'bootstrap.php',
    'install.php',
    'autoload.php',
    'cron.php',
    'MailboxProvisioner.php',
    '.htaccess',
    '.user.ini',
];

function run(string $cmd): void
{
    echo "> $cmd\n";
    passthru($cmd, $code);
    if ($code !== 0) {
        throw new RuntimeException("Command failed ($code): $cmd");
    }
}

function ensureDir(string $path): void
{
    if (!is_dir($path) && !mkdir($path, 0755, true) && !is_dir($path)) {
        throw new RuntimeException("Cannot create $path");
    }
}

function syncDir(string $from, string $to, bool $delete = true): void
{
    if (!is_dir($from)) {
        echo "SKIP dir missing: $from\n";
        return;
    }
    ensureDir($to);
    $deleteFlag = $delete ? '--delete' : '';
    run("rsync -a $deleteFlag " . escapeshellarg($from . '/') . ' ' . escapeshellarg($to . '/'));
}

function syncFile(string $from, string $to): void
{
    if (!is_file($from)) {
        echo "SKIP file missing: $from\n";
        return;
    }
    ensureDir(dirname($to));
    run('cp -a ' . escapeshellarg($from) . ' ' . escapeshellarg($to));
}

function backupLocalConfigs(string $target, array $names): array
{
    $backup = [];
    foreach ($names as $name) {
        $path = $target . '/config/' . $name;
        if (is_file($path)) {
            $backup[$name] = file_get_contents($path);
            echo "backup $target/config/$name\n";
        }
    }
    return $backup;
}

function restoreLocalConfigs(string $target, array $backup): void
{
    ensureDir($target . '/config');
    foreach ($backup as $name => $contents) {
        file_put_contents($target . '/config/' . $name, $contents);
        echo "restore $target/config/$name\n";
    }
}

function syncConfigNonLocal(string $master, string $target, array $localNames): void
{
    ensureDir($target . '/config');
    $localLookup = array_fill_keys($localNames, true);
    foreach (glob($master . '/config/*.php') ?: [] as $file) {
        $base = basename($file);
        if (isset($localLookup[$base]) || str_ends_with($base, '.local.php')) {
            continue;
        }
        syncFile($file, $target . '/config/' . $base);
    }
}

function md5fileOrMissing(string $path): string
{
    return is_file($path) ? md5_file($path) : 'MISSING';
}

echo "Master version: " . (require $master . '/config/version.php') . PHP_EOL;

// --- Dedicated CRM folder sync ---
$dedicatedTargets = [
    $root . '/ganz-soft.de' => 'ganz-soft.de (subdir)',
    $root . '/kontur-cosmetics.de' => 'kontur-cosmetics.de',
];

foreach ($dedicatedTargets as $target => $label) {
    echo "=== Full sync -> $label ===\n";
    if (!is_file($target . '/index.php')) {
        echo "SKIP: no CRM at $target\n";
        continue;
    }
    $backup = backupLocalConfigs($target, $localConfigNames);
    // Sync code dirs + selected files + non-local config
    foreach ($codeDirs as $dir) {
        syncDir($master . '/' . $dir, $target . '/' . $dir, true);
    }
    foreach ($codeFiles as $file) {
        syncFile($master . '/' . $file, $target . '/' . $file);
    }
    syncConfigNonLocal($master, $target, $localConfigNames);
    // Keep bin helper scripts in sync too (non-destructive)
    syncDir($master . '/bin', $target . '/bin', false);
    restoreLocalConfigs($target, $backup);
    echo "OK: $label\n";
}

// --- Safe ROOT sync (ganz-soft.de live) ---
echo "=== Safe partial sync -> __ROOT__ (ganz-soft.de live) ===\n";
$rootBackup = backupLocalConfigs($root, $localConfigNames);
foreach ($codeDirs as $dir) {
    syncDir($master . '/' . $dir, $root . '/' . $dir, true);
}
foreach ($codeFiles as $file) {
    syncFile($master . '/' . $file, $root . '/' . $file);
}
syncConfigNonLocal($master, $root, $localConfigNames);
syncDir($master . '/bin', $root . '/bin', false);
restoreLocalConfigs($root, $rootBackup);
echo "OK: __ROOT__\n";

echo "=== MD5 index.php ===\n";
foreach ([
    'master' => $master . '/index.php',
    'root' => $root . '/index.php',
    'ganz-soft.de' => $root . '/ganz-soft.de/index.php',
    'kontur' => $root . '/kontur-cosmetics.de/index.php',
] as $label => $path) {
    echo $label . ': ' . md5fileOrMissing($path) . PHP_EOL;
}

echo "DONE\n";
