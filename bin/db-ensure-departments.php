<?php
declare(strict_types=1);

/**
 * Fehlende Standard-Abteilungen in eine bestehende Installation ergänzen.
 * Bestehende Abteilungen, Mitglieder und Einstellungen bleiben unverändert.
 *
 * php bin/db-ensure-departments.php
 */
require dirname(__DIR__) . '/bootstrap.php';

MigrationRunner::runPending();

if (!Database::isConfigured()) {
    fwrite(STDERR, "Datenbank nicht konfiguriert.\n");
    exit(1);
}

$before = [];
foreach (Database::pdo()->query('SELECT id FROM dg_departments')->fetchAll(PDO::FETCH_COLUMN) as $id) {
    $before[(string) $id] = true;
}

$added = DepartmentRepository::ensureMissingDefaults();

if ($added === 0) {
    echo "Alle Standard-Abteilungen sind bereits vorhanden.\n";
    exit(0);
}

echo "{$added} Standard-Abteilung(en) ergänzt:\n";
foreach (DefaultDepartments::definitions() as $definition) {
    if (isset($before[$definition['id']])) {
        continue;
    }
    echo '  - ' . $definition['name'] . ' (' . $definition['id'] . ")\n";
}
