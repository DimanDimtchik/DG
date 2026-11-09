<?php
declare(strict_types=1);

/**
 * K21b — Selftest Verfahrensdokumentation-Export (ZIP + Dateien).
 * php bin/verfahrensdoku-export-selftest.php
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

$expect_true(class_exists('VerfahrensdokumentationExport'), 'VerfahrensdokumentationExport');
$expect_true(method_exists('VerfahrensdokumentationExport', 'buildZip'), 'buildZip');
$expect_true(method_exists('VerfahrensdokumentationExport', 'appendToZip'), 'appendToZip');

$expect_true(
    is_readable(DG_ROOT . '/docs/' . VerfahrensdokumentationExport::FILE_FIBU),
    'FiBu-Vorlage lesbar'
);
$expect_true(
    is_readable(DG_ROOT . '/docs/' . VerfahrensdokumentationExport::FILE_CASH),
    'Registerkasse-Baustein lesbar'
);

$fibu = VerfahrensdokumentationExport::readMarkdown(VerfahrensdokumentationExport::FILE_FIBU);
$cash = VerfahrensdokumentationExport::readMarkdown(VerfahrensdokumentationExport::FILE_CASH);
$expect_true(str_contains($fibu, 'Verfahrensdokumentation'), 'FiBu-Inhalt');
$expect_true(str_contains($cash, 'Registerkasse'), 'Kassen-Inhalt');
$expect_true(str_contains($cash, '§ 146a') || str_contains($cash, '146a'), '§146a erwähnt');

if (!class_exists('ZipArchive')) {
    echo "FAIL ZipArchive fehlt\n";
    exit(1);
}

$pack = VerfahrensdokumentationExport::buildZip();
$expect_true(is_file($pack['path']), 'ZIP erzeugt');
$expect_true(str_starts_with($pack['filename'], 'DG_Verfahrensdokumentation_'), 'ZIP-Name');

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
$expected = VerfahrensdokumentationExport::expectedZipNames();
$expect_true($found === $expected, 'ZIP Dateinamen = erwartet');
$expect_true(
    in_array('verfahrensdokumentation/' . VerfahrensdokumentationExport::FILE_CASH, $found, true),
    'Baustein im ZIP'
);

$view = (string) file_get_contents(DG_ROOT . '/views/modules/buchhaltung-steuerberater-export.php');
$expect_true(str_contains($view, 'download=verfahrensdoku'), 'UI ZIP-Link');
$expect_true(str_contains($view, 'download=verfahrensdoku-kasse'), 'UI Kassen-Link');

if ($fail > 0) {
    fwrite(STDERR, "verfahrensdoku-export-selftest FAILED ({$fail})\n");
    exit(1);
}
echo "verfahrensdoku-export-selftest OK (K21b)\n";
