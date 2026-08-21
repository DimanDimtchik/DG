#!/usr/bin/env php
<?php
declare(strict_types=1);
/**
 * Stellt Kontur-Cosmetics-Firmendaten wieder her (nach versehentlichem Überschreiben).
 */
define('DG_ROOT', dirname(__DIR__));
require_once DG_ROOT . '/src/autoload.php';

if (!Database::isConfigured()) {
    fwrite(STDERR, "ERROR: DB\n");
    exit(1);
}
MigrationRunner::runPending();

$current = CompanySettings::config();
$email = 'info@kontur-cosmetics.de';
if (trim((string) ($current['email'] ?? '')) !== '' && str_contains((string) $current['email'], 'kontur')) {
    $email = $current['email'];
}

CompanySettings::save([
    'name' => 'Kontur Cosmetics',
    'company_id' => $current['company_id'] ?? '',
    'email' => $email,
    'phone' => $current['phone'] ?? '',
    'website' => 'https://kontur-cosmetics.de',
    'street' => '',
    'postal' => '',
    'city' => '',
    'country' => 'DE',
    'tax_number' => '',
    'vat_id' => '',
]);

echo "OK Kontur wiederhergestellt: Kontur Cosmetics / $email\n";
echo "Hinweis: Anschrift bitte im CRM unter Einstellungen erneut eintragen, falls vorhanden.\n";
