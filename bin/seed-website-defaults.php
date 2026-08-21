<?php
/**
 * Pflichtseiten, Startseite, Kontaktformular und Wartungsmodus anlegen.
 *
 * Usage:
 *   php bin/seed-website-defaults.php
 *   php bin/seed-website-defaults.php --overwrite
 *   php bin/seed-website-defaults.php --no-maintenance
 *   php bin/seed-website-defaults.php --legal-only
 */
declare(strict_types=1);

define('DG_ROOT', dirname(__DIR__));
require_once DG_ROOT . '/src/autoload.php';

if (!Database::isConfigured()) {
    fwrite(STDERR, "ERROR: Datenbank nicht konfiguriert.\n");
    exit(1);
}

$args = array_slice($argv, 1);
$overwrite = in_array('--overwrite', $args, true);
$noMaintenance = in_array('--no-maintenance', $args, true);
$legalOnly = in_array('--legal-only', $args, true);

$options = [
    'overwrite' => $overwrite,
    'enable_maintenance' => !$noMaintenance,
];

if ($legalOnly) {
    $options['homepage'] = false;
    $options['contact'] = false;
    $options['menu'] = false;
    $options['maintenance'] = false;
}

try {
    MigrationRunner::runPending();
    $result = WebsiteBootstrapService::bootstrap(null, $options);

    echo "Website-Bootstrap abgeschlossen.\n\n";

    if ($result['legal'] !== []) {
        echo "Rechtstexte:\n";
        foreach ($result['legal'] as $page) {
            echo "  - {$page['title']} (/{$page['slug']}): {$page['action']} (ID {$page['id']})\n";
        }
        echo "\n";
    }

    if (is_array($result['homepage'])) {
        $h = $result['homepage'];
        echo "Startseite: {$h['action']} (ID {$h['id']})";
        if (!empty($h['kind'])) {
            echo " – Vorlage: {$h['kind']}";
        }
        echo "\n";
    }

    if (is_array($result['contact_page'])) {
        $c = $result['contact_page'];
        echo "Kontaktseite: {$c['action']} (ID {$c['id']})\n";
    }
    if (!empty($result['contact_form_id'])) {
        echo "Kontaktformular-ID: {$result['contact_form_id']}\n";
    }

    if (!empty($result['menu'])) {
        echo "Menü und Fußzeile: konfiguriert\n";
    }

    echo 'Wartungsmodus: ' . (!empty($result['maintenance']) ? 'eingeschaltet' : 'unverändert/aus') . "\n";
} catch (Throwable $e) {
    fwrite(STDERR, 'ERROR: ' . $e->getMessage() . "\n");
    exit(1);
}
