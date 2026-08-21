<?php
$zipPath = '/www/htdocs/w0217246/dg.ganz-om.de/update/releases/dg-crm-1.0.2.zip';
$z = new ZipArchive();
$z->open($zipPath);
echo "Total entries: {$z->numFiles}\n";
for ($i = 0; $i < $z->numFiles; $i++) {
    $name = $z->getNameIndex($i);
    if (stripos($name, 'version') !== false
        || stripos($name, 'index.php') !== false
        || stripos($name, 'config') !== false
        || stripos($name, 'website-public') !== false
    ) {
        echo $name, "\n";
    }
}
echo "\n--- try read ---\n";
foreach (['config/version.php', 'config\\version.php', 'index.php'] as $n) {
    $c = $z->getFromName($n);
    echo $n . ': ' . ($c === false ? 'NO' : ("YES\n" . substr($c, 0, 80))) . "\n\n";
}
$z->close();
