<?php
declare(strict_types=1);

/**
 * K17d — Selftest: DSFinV-K-ZIP enthält genau die erwarteten Dateinamen.
 * php bin/cash-dsfinvk-zip-names-selftest.php
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

$expect_true(class_exists('CashDsfinvkStubExport'), 'CashDsfinvkStubExport');
$expect_true(method_exists('CashDsfinvkStubExport', 'expectedArchiveNames'), 'expectedArchiveNames');
$expect_true(method_exists('CashDsfinvkStubExport', 'buildZip'), 'buildZip');

$requiredCsv = array_keys(CashDsfinvkStubExport::csvCatalog());
sort($requiredCsv);
$expect_true(count($requiredCsv) === 20, 'genau 20 Pflicht-CSV');

$mandatory = [
    'cashpointclosing.csv',
    'location.csv',
    'cashregister.csv',
    'slaves.csv',
    'pa.csv',
    'vat.csv',
    'tse.csv',
    'businesscases.csv',
    'payment.csv',
    'cash_per_currency.csv',
    'transactions.csv',
    'allocation_groups.csv',
    'transactions_vat.csv',
    'datapayment.csv',
    'lines.csv',
    'lines_vat.csv',
    'itemamounts.csv',
    'subitems.csv',
    'references.csv',
    'transactions_tse.csv',
];
sort($mandatory);
$expect_true($requiredCsv === $mandatory, 'CSV-Namen = Spec K17a');

$expected = CashDsfinvkStubExport::expectedArchiveNames();
foreach (['index.xml', 'gdpdu-01-09-2004.dtd', 'README.txt'] as $extra) {
    $expect_true(in_array($extra, $expected, true), 'Archiv enthält ' . $extra);
}
foreach ($mandatory as $csv) {
    $expect_true(in_array($csv, $expected, true), 'Archiv-Liste enthält ' . $csv);
}

if (!class_exists('ZipArchive')) {
    echo "FAIL ZipArchive fehlt — K17d braucht ZIP-Prüfung\n";
    exit(1);
}

$pack = CashDsfinvkStubExport::buildZip('2026-01-01', '2026-01-31');
$expect_true(is_file($pack['path']), 'ZIP erzeugt');

$zip = new ZipArchive();
$opened = $zip->open($pack['path']) === true;
$expect_true($opened, 'ZIP öffnen');
$found = [];
if ($opened) {
    for ($i = 0; $i < $zip->numFiles; $i++) {
        $name = $zip->getNameIndex($i);
        if (is_string($name) && $name !== '') {
            $found[] = $name;
        }
    }
    $zip->close();
}
@unlink($pack['path']);

sort($found);
$expect_true($found === $expected, 'ZIP Dateinamen exakt = erwartet');
$expect_true(count($found) === count($expected), 'keine Extra-/Fehldateien');

// Keine Unterordner / Windows-Pfade
foreach ($found as $name) {
    $expect_true(!str_contains($name, '/') && !str_contains($name, '\\'), 'flach: ' . $name);
}

if ($fail > 0) {
    fwrite(STDERR, "cash-dsfinvk-zip-names-selftest FAILED ({$fail})\n");
    exit(1);
}
echo "cash-dsfinvk-zip-names-selftest OK (K17d)\n";
