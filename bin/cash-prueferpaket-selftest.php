<?php
declare(strict_types=1);

/**
 * K18c — Selftest Prüferpaket-ZIP (Struktur laut CASH-PRUEFERPAKET-SPEC).
 * php bin/cash-prueferpaket-selftest.php
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

$expect_true(class_exists('CashPrueferpaketExport'), 'CashPrueferpaketExport');
$expect_true(method_exists('CashPrueferpaketExport', 'buildZip'), 'buildZip');
$expect_true(method_exists('CashPrueferpaketExport', 'expectedArchiveNames'), 'expectedArchiveNames');
$expect_true(CashPrueferpaketExport::EXPORT_TYPE === 'pruefer', 'export_type=pruefer');

if (!class_exists('ZipArchive')) {
    echo "FAIL ZipArchive fehlt\n";
    exit(1);
}

$expected = CashPrueferpaketExport::expectedArchiveNames();
$expect_true(in_array('README.txt', $expected, true), 'erwartet README.txt');
$expect_true(in_array('manifest.json', $expected, true), 'erwartet manifest.json');
$expect_true(in_array('tse/EXPORT_STATUS.txt', $expected, true), 'erwartet tse/EXPORT_STATUS.txt');
$expect_true(in_array('journal/cash_receipts_journal.csv', $expected, true), 'erwartet journal CSV');
$expect_true(in_array('dsfinvk/transactions.csv', $expected, true), 'erwartet dsfinvk/transactions.csv');
$expect_true(in_array('dsfinvk/index.xml', $expected, true), 'erwartet dsfinvk/index.xml');

$dsfinvkCsvCount = 0;
foreach ($expected as $name) {
    if (str_starts_with($name, 'dsfinvk/') && str_ends_with($name, '.csv')) {
        $dsfinvkCsvCount++;
    }
}
$expect_true($dsfinvkCsvCount === 20, '20 CSV unter dsfinvk/');

$pack = CashPrueferpaketExport::buildZip('2026-01-01', '2026-01-31', 0, []);
$expect_true(is_file($pack['path']), 'ZIP erzeugt');
$expect_true(str_starts_with($pack['filename'], 'DG_Cash_Prueferpaket_2026-01-01_2026-01-31'), 'ZIP-Dateiname');
$expect_true($pack['sha256'] !== '', 'SHA-256 gesetzt');

$zip = new ZipArchive();
$opened = $zip->open($pack['path']) === true;
$expect_true($opened, 'ZIP öffnen');
$found = [];
$statusBody = '';
$manifestBody = '';
if ($opened) {
    for ($i = 0; $i < $zip->numFiles; $i++) {
        $name = $zip->getNameIndex($i);
        if (is_string($name) && $name !== '') {
            $found[] = $name;
        }
    }
    $statusBody = (string) $zip->getFromName('tse/EXPORT_STATUS.txt');
    $manifestBody = (string) $zip->getFromName('manifest.json');
    $zip->close();
}
@unlink($pack['path']);

sort($found);
$expect_true($found === $expected, 'ZIP Dateinamen = erwartet');

$expect_true(str_contains($statusBody, 'status=stub'), 'TSE status=stub');
$expect_true(str_contains($statusBody, 'provider_not_implemented'), 'TSE reason');
$expect_true(!preg_match('/\.tar\b/i', implode("\n", $found)), 'kein Fake-TAR im Paket');

$manifest = json_decode($manifestBody, true);
$expect_true(is_array($manifest), 'manifest.json parse');
$expect_true(($manifest['export_type'] ?? '') === 'pruefer', 'manifest export_type');
$expect_true(($manifest['tse']['status'] ?? '') === 'stub', 'manifest tse.status');
$expect_true(
    in_array((string) ($manifest['dsfinvk']['status'] ?? ''), ['stub', 'filled'], true),
    'manifest dsfinvk.status'
);
$expect_true(($manifest['date_from'] ?? '') === '2026-01-01', 'manifest date_from');
$expect_true(($manifest['date_to'] ?? '') === '2026-01-31', 'manifest date_to');

// Kein FiBu-GoBD-Inhalt
foreach ($found as $name) {
    $expect_true(!str_contains(strtolower($name), 'gobd'), 'kein GoBD-Dateiname: ' . $name);
}

if ($fail > 0) {
    fwrite(STDERR, "cash-prueferpaket-selftest FAILED ({$fail})\n");
    exit(1);
}
echo "cash-prueferpaket-selftest OK (K18c)\n";
