<?php
declare(strict_types=1);

/**
 * K19a — Selftest Meldefelder §146a (ohne Senden).
 * php bin/cash-ers-melde-selftest.php
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

$expect_true(is_readable(DG_ROOT . '/database/migrations/154_cash_ers_melde.sql'), 'Migration 154');
$expect_true(class_exists('CashErsTaxpayerRepository'), 'CashErsTaxpayerRepository');
$expect_true(class_exists('CashErsClientRepository'), 'CashErsClientRepository');
$expect_true(method_exists('CashErsClientRepository', 'upsertForRegister'), 'upsertForRegister');
$expect_true(method_exists('CashErsClientRepository', 'isCoreComplete'), 'isCoreComplete');
$expect_true(method_exists('CashErsTaxpayerRepository', 'save'), 'taxpayer save');

$types = CashErsClientRepository::typeLabels();
$expect_true(isset($types['1'], $types['2'], $types['3']), 'ERS-Typen 1–3');

$incomplete = CashErsClientRepository::formDefaults(1);
$expect_true(!CashErsClientRepository::isCoreComplete($incomplete), 'Defaults unvollständig');

$complete = array_merge($incomplete, [
    'manufacturer' => 'BeispielHersteller',
    'model' => 'POS-1',
    'serial_number' => 'SN-001',
    'software' => 'DG CRM Registerkasse',
    'date_acquisition' => '2025-01-15',
    'date_commissioning' => '2025-02-01',
]);
$expect_true(CashErsClientRepository::isCoreComplete($complete), 'Kernfelder vollständig');

$viewList = DG_ROOT . '/views/modules/registerkasse-meldung.php';
$viewForm = DG_ROOT . '/views/modules/registerkasse-meldung-form.php';
$expect_true(is_readable($viewList), 'View Übersicht');
$expect_true(is_readable($viewForm), 'View Formular');
$listSrc = (string) file_get_contents($viewList);
$formSrc = (string) file_get_contents($viewForm);
$expect_true(!str_contains($listSrc, 'ELSTER senden') && !str_contains($formSrc, 'Jetzt senden'), 'kein Senden-Button');
$expect_true(str_contains($formSrc, 'noch nicht') || str_contains($formSrc, 'nichts an ELSTER'), 'Hinweis ohne Versand');

if (Database::isConfigured()) {
    MigrationRunner::runPending();
    $expect_true(CashErsTaxpayerRepository::tableExists(), 'Tabelle taxpayer');
    $expect_true(CashErsClientRepository::tableExists(), 'Tabelle clients');
} else {
    echo "SKIP DB — Klassen/Views geprüft.\n";
}

if ($fail > 0) {
    fwrite(STDERR, "cash-ers-melde-selftest FAILED ({$fail})\n");
    exit(1);
}
echo "cash-ers-melde-selftest OK (K19a)\n";
