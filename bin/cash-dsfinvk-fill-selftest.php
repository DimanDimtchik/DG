<?php
declare(strict_types=1);

/**
 * Selftest K17c: DSFinV-K Befüllung (Dateinamen + Datenzeilen, ohne zwingend DB-Bons).
 * php bin/cash-dsfinvk-fill-selftest.php
 */
require dirname(__DIR__) . '/bootstrap.php';

$fail = 0;
$expect = static function (bool $cond, string $msg) use (&$fail): void {
    if ($cond) {
        echo "OK  {$msg}\n";
        return;
    }
    echo "FAIL {$msg}\n";
    $fail++;
};

$expect(class_exists('CashDsfinvkExport'), 'CashDsfinvkExport');
$map = CashDsfinvkExport::buildFileMap('2026-01-01', '2026-01-31');
$expect(isset($map['transactions.csv']), 'transactions.csv');
$expect(isset($map['index.xml']), 'index.xml');
$expect(str_contains($map['README.txt'] ?? '', 'K17c'), 'README K17c');

foreach (array_keys(CashDsfinvkStubExport::csvCatalog()) as $name) {
    $expect(isset($map[$name]), 'Datei ' . $name);
    $expect(str_starts_with($map[$name], "\xEF\xBB\xBF"), 'BOM ' . $name);
}

// Dateinamen wie Stub
$names = array_keys($map);
sort($names);
$expect($names === CashDsfinvkStubExport::expectedArchiveNames(), 'gleiche Archiv-Namen wie Stub');

if ($fail > 0) {
    fwrite(STDERR, "cash-dsfinvk-fill-selftest: {$fail} Fehler\n");
    exit(1);
}
echo "cash-dsfinvk-fill-selftest OK\n";
exit(0);
