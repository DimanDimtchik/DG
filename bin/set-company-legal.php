#!/usr/bin/env php
<?php
/**
 * Firmendaten des Anbieters (Dietrich Ganz) in SettingsStore setzen.
 * Nur für Provider-Instanzen (ganz-soft / dg.ganz-om) – nicht für Kunden-CRMs.
 *
 * Usage: php bin/set-company-legal.php
 *        php bin/set-company-legal.php --force
 */
declare(strict_types=1);

define('DG_ROOT', dirname(__DIR__));
require_once DG_ROOT . '/src/autoload.php';

if (!Database::isConfigured()) {
    fwrite(STDERR, "ERROR: Datenbank nicht konfiguriert.\n");
    exit(1);
}

$force = in_array('--force', $argv, true);
$hostHint = strtolower(basename(DG_ROOT));
$allowed = str_contains($hostHint, 'ganz-soft')
    || str_contains($hostHint, 'dg.ganz-om')
    || str_contains($hostHint, 'ganz-om')
    || $hostHint === 'w0217246';

if (!$force && !$allowed) {
    fwrite(STDERR, "ABBRUCH: Keine Provider-Instanz ($hostHint). Nutze --force nur bewusst.\n");
    exit(2);
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
if (array_key_exists('wirtschafts_id', $ext['tax_numbers'] ?? [])) {
    $ext['tax_numbers']['wirtschafts_id'] = 'DE461693381-00001';
}
if (array_key_exists('company_type', $ext)) {
    $ext['company_type'] = 'einzelunternehmen';
}
if (array_key_exists('legal_name', $ext) && trim((string) ($ext['legal_name'] ?? '')) === '') {
    $ext['legal_name'] = 'Dietrich Ganz';
}
SettingsStore::set(CompanyExtendedSettings::STORE_KEY, $ext);

$cfg = CompanySettings::config();
echo "OK Firmendaten: {$cfg['name']}, {$cfg['street']}, {$cfg['postal']} {$cfg['city']}\n";
echo "USt-IdNr. {$cfg['vat_id']}, Steuernr. {$cfg['tax_number']}\n";
