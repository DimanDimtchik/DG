<?php
declare(strict_types=1);

/**
 * K17b — DSFinV-K Export-Stub (Inhalt/Struktur).
 * Dateinamen im ZIP: zusätzlich bin/cash-dsfinvk-zip-names-selftest.php (K17d).
 * php bin/cash-dsfinvk-stub-selftest.php
 */
require dirname(__DIR__) . '/bootstrap.php';

$fail = 0;
$expect_true = static function (bool $cond, string $label) use (&$fail): void {
    if ($cond) {
        echo "OK  {$label}\n";
        return;
    }
    echo "FAIL {$label}\n";
    $fail++;
};

$expect_true(class_exists('CashDsfinvkStubExport'), 'Klasse StubExport');
$catalog = CashDsfinvkStubExport::csvCatalog();
$expect_true(count($catalog) === 20, '20 CSV-Dateien');
$expect_true(isset($catalog['transactions.csv'], $catalog['cashregister.csv'], $catalog['tse.csv']), 'Kern-CSV vorhanden');

$map = CashDsfinvkStubExport::buildFileMap();
$expected = CashDsfinvkStubExport::expectedArchiveNames();
$expect_true(count($map) === count($expected), 'Datei-Map Größe');
foreach ($expected as $name) {
    $expect_true(isset($map[$name]), 'Map hat ' . $name);
}

$expect_true(str_starts_with($map['cashregister.csv'], "\xEF\xBB\xBF"), 'CSV BOM');
$expect_true(str_contains($map['cashregister.csv'], 'Z_KASSE_ID'), 'CSV Kopf');
$expect_true(str_contains($map['index.xml'], 'DataSet'), 'index.xml DataSet');
$expect_true(str_contains($map['index.xml'], 'transactions.csv'), 'index nennt transactions.csv');
$expect_true(str_contains($map['gdpdu-01-09-2004.dtd'], 'ELEMENT DataSet'), 'DTD Platzhalter');

if (class_exists('ZipArchive')) {
    $pack = CashDsfinvkStubExport::buildZip('2026-11-01', '2026-11-07');
    $expect_true(is_file($pack['path']), 'ZIP Datei');
    $zip = new ZipArchive();
    $expect_true($zip->open($pack['path']) === true, 'ZIP öffnen');
    $found = [];
    for ($i = 0; $i < $zip->numFiles; $i++) {
        $found[] = $zip->getNameIndex($i);
    }
    $zip->close();
    @unlink($pack['path']);
    sort($found);
    $expect_true($found === $expected, 'ZIP Dateinamen = erwartet');
} else {
    echo "SKIP ZipArchive\n";
}

if ($fail > 0) {
    fwrite(STDERR, "cash-dsfinvk-stub-selftest FAILED ({$fail})\n");
    exit(1);
}
echo "cash-dsfinvk-stub-selftest OK\n";
