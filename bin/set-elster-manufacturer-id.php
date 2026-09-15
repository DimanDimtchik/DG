<?php
declare(strict_types=1);

/**
 * ELSTER-Hersteller-ID in CRM-Einstellungen speichern (CLI auf dem Server).
 *
 *   php bin/set-elster-manufacturer-id.php 34573
 */
if (!defined('DG_ROOT')) {
    define('DG_ROOT', dirname(__DIR__));
}
require_once DG_ROOT . '/src/autoload.php';
MigrationRunner::runPending();

if (!Database::isConfigured()) {
    fwrite(STDERR, "Datenbank nicht konfiguriert.\n");
    exit(1);
}

$id = trim((string) ($argv[1] ?? ''));
if ($id === '' || !preg_match('/^\d{4,8}$/', $id)) {
    fwrite(STDERR, "Usage: php bin/set-elster-manufacturer-id.php <Hersteller-ID>\n");
    exit(1);
}

$stored = SettingsStore::get(ElsterSettings::STORE_KEY, ElsterSettings::defaults());
$stored['manufacturer_id'] = $id;
SettingsStore::set(ElsterSettings::STORE_KEY, $stored);

echo json_encode([
    'success' => true,
    'manufacturer_id' => $id,
    'readiness' => ElsterEricClient::readiness(),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
