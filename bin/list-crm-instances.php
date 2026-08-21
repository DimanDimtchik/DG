<?php
declare(strict_types=1);

$root = '/www/htdocs/w0217246';

$known = [
    '__ROOT__' => [
        'label' => 'ganz-soft.de (Live-Root)',
        'urls' => ['https://ganz-soft.de'],
        'path' => $root,
        'note' => 'Document-Root von ganz-soft.de',
    ],
    'dg.ganz-om.de' => [
        'label' => 'dg.ganz-om.de (Master / Update-Quelle)',
        'urls' => ['https://dg.ganz-om.de'],
        'path' => $root . '/dg.ganz-om.de',
        'note' => 'Zentrale CRM-Quelle + Update-Server unter /update/',
    ],
    'ganz-soft.de' => [
        'label' => 'ganz-soft.de (Unterordner)',
        'urls' => ['(nicht Document-Root – Kopie unter Ordner ganz-soft.de)'],
        'path' => $root . '/ganz-soft.de',
        'note' => 'Parallel-Kopie; Live-Traffic geht auf den Root',
    ],
    'kontur-cosmetics.de' => [
        'label' => 'kontur-cosmetics.de',
        'urls' => ['https://kontur-cosmetics.de'],
        'path' => $root . '/kontur-cosmetics.de',
        'note' => 'Kunden-Instanz',
    ],
];

function readVersion(string $path): string
{
    $file = $path . '/config/version.php';
    if (!is_readable($file)) {
        return '–';
    }
    $v = include $file;
    return is_string($v) ? $v : '–';
}

function isCrm(string $path): bool
{
    return is_file($path . '/index.php')
        && is_dir($path . '/src')
        && is_dir($path . '/views');
}

echo "CRM-INSTANZEN\n";
echo str_repeat('=', 72) . "\n";

$rows = [];

foreach ($known as $key => $info) {
    if (!isCrm($info['path'])) {
        continue;
    }
    $rows[] = [
        'key' => $key,
        'label' => $info['label'],
        'urls' => $info['urls'],
        'path' => $info['path'],
        'version' => readVersion($info['path']),
        'note' => $info['note'],
    ];
}

// Discover any other CRM folders not in known list
foreach (glob($root . '/*', GLOB_ONLYDIR) ?: [] as $dir) {
    $name = basename($dir);
    if (isset($known[$name])) {
        continue;
    }
    if (!isCrm($dir)) {
        continue;
    }
    $rows[] = [
        'key' => $name,
        'label' => $name,
        'urls' => ['https://' . $name],
        'path' => $dir,
        'version' => readVersion($dir),
        'note' => 'automatisch gefunden',
    ];
}

foreach ($rows as $i => $row) {
    echo ($i + 1) . ") {$row['label']}\n";
    echo "   Version : {$row['version']}\n";
    echo "   URL(s)  : " . implode(', ', $row['urls']) . "\n";
    echo "   Pfad    : {$row['path']}\n";
    echo "   Hinweis : {$row['note']}\n\n";
}

echo "Update-Server: https://dg.ganz-om.de/update/version.json\n";
if (is_readable($root . '/dg.ganz-om.de/update/version.json')) {
    $info = json_decode((string) file_get_contents($root . '/dg.ganz-om.de/update/version.json'), true);
    if (is_array($info)) {
        echo "Aktuelles Release: " . ($info['version'] ?? '?') . "\n";
        echo "Download: " . ($info['url'] ?? '?') . "\n";
    }
}
