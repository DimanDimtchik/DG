#!/usr/bin/env php
<?php
/**
 * Firmendaten (Impressum) in SettingsStore setzen.
 * Usage: php bin/set-company-legal.php
 */
declare(strict_types=1);

define('DG_ROOT', dirname(__DIR__));
require_once DG_ROOT . '/src/autoload.php';

if (!Database::isConfigured()) {
    fwrite(STDERR, "ERROR: Datenbank nicht konfiguriert.\n");
    exit(1);
}

MigrationRunner::runPending();

$current = CompanySettings::config();
$email = trim((string) ($current['email'] ?? ''));
if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $email = 'info@ganz-soft.de';
}

CompanySettings::save([
    'name' => 'Dietrich Ganz',
    'company_id' => $current['company_id'] ?? '',
    'email' => $email,
    'phone' => $current['phone'] ?? '',
    'website' => ($current['website'] ?? '') !== '' ? $current['website'] : 'https://ganz-soft.de',
    'street' => 'Auf dem Bühl 1',
    'postal' => '87437',
    'city' => 'Kempten (Allgäu)',
    'country' => 'DE',
    'tax_number' => '127/219/40770',
    'vat_id' => 'DE461693381',
]);

$ext = CompanyExtendedSettings::config();
$ext['tax_numbers']['est'] = '127/219/40770';
$ext['tax_numbers']['ust'] = 'DE461693381';
SettingsStore::set(CompanyExtendedSettings::STORE_KEY, $ext);

$cfg = CompanySettings::config();
echo "OK Firmendaten: {$cfg['name']}, {$cfg['street']}, {$cfg['postal']} {$cfg['city']}\n";
echo "USt-IdNr. {$cfg['vat_id']}, Steuernr. {$cfg['tax_number']}\n";
