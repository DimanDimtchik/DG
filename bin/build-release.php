<?php
declare(strict_types=1);

/**
 * Build a Unix-friendly CRM release ZIP on the server (forward-slash paths).
 * Usage: php bin/build-release.php
 */
$root = dirname(__DIR__);
$version = require $root . '/config/version.php';
$version = (string) $version;

$outDir = $root . '/update/releases';
if (!is_dir($outDir)) {
    mkdir($outDir, 0755, true);
}

$zipPath = $outDir . '/dg-crm-' . $version . '.zip';
if (is_file($zipPath)) {
    unlink($zipPath);
}

$zip = new ZipArchive();
if ($zip->open($zipPath, ZipArchive::CREATE) !== true) {
    fwrite(STDERR, "Cannot create $zipPath\n");
    exit(1);
}

$dirs = ['src', 'views', 'assets', 'database', 'bin'];
$files = ['index.php', 'bootstrap.php', '.htaccess'];
$configFiles = ['version.php', 'app.php', 'database.php'];

$addFile = static function (ZipArchive $zip, string $abs, string $local): void {
    $local = str_replace('\\', '/', $local);
    $zip->addFile($abs, $local);
};

$addDir = static function (ZipArchive $zip, string $absDir, string $localPrefix) use (&$addDir, $addFile): void {
    $items = scandir($absDir) ?: [];
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }
        $abs = $absDir . '/' . $item;
        $local = $localPrefix . '/' . $item;
        if (is_dir($abs)) {
            $addDir($zip, $abs, $local);
        } else {
            $addFile($zip, $abs, $local);
        }
    }
};

foreach ($dirs as $dir) {
    $abs = $root . '/' . $dir;
    if (is_dir($abs)) {
        $addDir($zip, $abs, $dir);
    }
}
foreach ($files as $file) {
    $abs = $root . '/' . $file;
    if (is_file($abs)) {
        $addFile($zip, $abs, $file);
    }
}
foreach ($configFiles as $file) {
    $abs = $root . '/config/' . $file;
    if (is_file($abs)) {
        $addFile($zip, $abs, 'config/' . $file);
    }
}

$zip->close();

$versionJson = [
    'version' => $version,
    'url' => 'https://dg.ganz-om.de/update/releases/dg-crm-' . $version . '.zip',
    'critical' => false,
    'released' => date('Y-m-d'),
    'notes' => 'Release ' . $version,
];
file_put_contents(
    $root . '/update/version.json',
    json_encode($versionJson, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n"
);

echo "Built $zipPath (" . filesize($zipPath) . " bytes)\n";
echo "Published version.json for $version\n";
