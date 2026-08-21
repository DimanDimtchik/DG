<?php
declare(strict_types=1);

$root = '/www/htdocs/w0217246';
$master = $root . '/dg.ganz-om.de';
$transfer = $root . '/cursor-transfer';
$zipSrc = $transfer . '/dg-crm-1.0.2.zip';
$zipDst = $master . '/update/releases/dg-crm-1.0.2.zip';
$versionJson = $master . '/update/version.json';

if (!is_file($zipSrc)) {
    fwrite(STDERR, "ZIP missing: $zipSrc\n");
    exit(1);
}

if (!is_dir(dirname($zipDst))) {
    mkdir(dirname($zipDst), 0755, true);
}

if (!copy($zipSrc, $zipDst)) {
    fwrite(STDERR, "Failed to copy ZIP\n");
    exit(1);
}

$payload = [
    'version' => '1.0.2',
    'url' => 'https://dg.ganz-om.de/update/releases/dg-crm-1.0.2.zip',
    'critical' => false,
    'released' => date('Y-m-d'),
    'notes' => 'End-to-end Update-Test: Website-Vorschau, Menü-Vorschläge, öffentliche Ausgabe, Sync 1.0.2',
];
file_put_contents($versionJson, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n");

// Master gets the new version marker (and force-update helper)
copy($transfer . '/version.php', $master . '/config/version.php');
copy($transfer . '/force-update.php', $master . '/bin/force-update.php');
copy($transfer . '/force-update.php', $root . '/kontur-cosmetics.de/bin/force-update.php');

echo "Published:\n";
echo "  ZIP: " . $zipDst . ' (' . filesize($zipDst) . " bytes)\n";
echo "  version.json: " . file_get_contents($versionJson);
echo "  master version: " . (require $master . '/config/version.php') . "\n";
echo "  kontur version: " . (require $root . '/kontur-cosmetics.de/config/version.php') . "\n";
