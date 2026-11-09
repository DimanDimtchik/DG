<?php
declare(strict_types=1);

/**
 * K15a — Mapping Zahlart → Konten (ohne DB).
 */
$root = dirname(__DIR__);
require_once $root . '/bootstrap.php';

$failed = 0;
$expect_true = static function (bool $cond, string $label) use (&$failed): void {
    if ($cond) {
        echo "OK  {$label}\n";
        return;
    }
    echo "FAIL {$label}\n";
    $failed++;
};

$expect_true(class_exists('CashRegisterFiBuSettings'), 'Klasse CashRegisterFiBuSettings');
$expect_true(CashRegisterFiBuSettings::STORE_KEY === 'cash_register_fibu', 'STORE_KEY');
$expect_true(CashRegisterFiBuSettings::PAYMENT_METHODS === ['cash', 'ec', 'card', 'voucher'], 'Zahlarten');

$skr03 = CashRegisterFiBuSettings::defaultsForSkr('skr03');
$expect_true(($skr03['cash'] ?? '') === '1000', 'SKR03 Bar = 1000');
$expect_true(($skr03['ec'] ?? '') === '1200', 'SKR03 EC = 1200');
$expect_true(($skr03['card'] ?? '') === '1200', 'SKR03 Karte = 1200');
$expect_true(($skr03['voucher'] ?? '') === '1796', 'SKR03 Gutschein = 1796');

$skr04 = CashRegisterFiBuSettings::defaultsForSkr('skr04');
$expect_true(($skr04['cash'] ?? '') === '1600', 'SKR04 Bar = 1600');
$expect_true(($skr04['ec'] ?? '') === '1800', 'SKR04 EC = 1800');
$expect_true(($skr04['voucher'] ?? '') === '3786', 'SKR04 Gutschein = 3786');

// Ohne DB: forForm liefert Defaults des aktiven/übergebenen SKR
$form = CashRegisterFiBuSettings::forForm('skr03');
$expect_true(($form['accounts']['cash'] ?? '') === '1000', 'forForm cash');
$expect_true(CashRegisterFiBuSettings::accountForMethod('cash', 'skr03') === '1000', 'accountForMethod cash');
$expect_true(CashRegisterFiBuSettings::accountForMethod('voucher', 'skr04') === '3786', 'accountForMethod voucher skr04');

$threw = false;
try {
    CashRegisterFiBuSettings::accountForMethod('mixed');
} catch (InvalidArgumentException) {
    $threw = true;
}
$expect_true($threw, 'mixed abgewiesen');

$expect_true(is_readable($root . '/views/settings/tab-registerkasse-fibu.php'), 'Settings-Tab View');

if ($failed > 0) {
    fwrite(STDERR, "cash-register-fibu-settings-selftest FAILED ({$failed})\n");
    exit(1);
}
echo "cash-register-fibu-settings-selftest OK (ohne DB)\n";
