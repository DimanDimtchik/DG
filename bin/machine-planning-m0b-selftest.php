<?php
declare(strict_types=1);

/**
 * Selbsttest fuer Maschinenblock M0b ohne echte CRM-Datenbank.
 */
final class Database
{
    public static PDO $pdo;

    public static function isConfigured(): bool
    {
        return true;
    }

    public static function pdo(): PDO
    {
        return self::$pdo;
    }
}

final class MigrationRunner
{
    public static function runPending(): void
    {
        // Selftest erstellt das Schema selbst.
    }
}

require dirname(__DIR__) . '/src/Recipe/MachinePlanningRepository.php';

function assertM0b(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "FEHLER: {$message}\n");
        exit(1);
    }
    echo "OK: {$message}\n";
}

Database::$pdo = new PDO('sqlite::memory:');
Database::$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

foreach ([
    'CREATE TABLE dg_machine_capacity_profiles (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        work_center_id INTEGER NOT NULL,
        recipe_id INTEGER NULL,
        input_material_label TEXT NOT NULL DEFAULT "",
        input_article_id INTEGER NULL,
        input_qty_per_hour REAL NOT NULL DEFAULT 0,
        output_product_label TEXT NOT NULL DEFAULT "",
        output_article_id INTEGER NULL,
        output_qty_per_hour REAL NOT NULL DEFAULT 0,
        unit TEXT NOT NULL DEFAULT "",
        yield_percent REAL NOT NULL DEFAULT 100,
        valid_from TEXT NULL,
        is_active INTEGER NOT NULL DEFAULT 1,
        notes TEXT NOT NULL DEFAULT "",
        created_at TEXT DEFAULT CURRENT_TIMESTAMP,
        updated_at TEXT DEFAULT CURRENT_TIMESTAMP
    )',
    'CREATE TABLE dg_recipe_auxiliary_materials (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        recipe_id INTEGER NOT NULL,
        article_id INTEGER NULL,
        material_label TEXT NOT NULL DEFAULT "",
        role TEXT NOT NULL DEFAULT "other",
        qty_per_output_unit REAL NOT NULL DEFAULT 0,
        unit TEXT NOT NULL DEFAULT "",
        cost_per_unit REAL NOT NULL DEFAULT 0,
        is_active INTEGER NOT NULL DEFAULT 1,
        created_at TEXT DEFAULT CURRENT_TIMESTAMP,
        updated_at TEXT DEFAULT CURRENT_TIMESTAMP
    )',
    'CREATE TABLE dg_machine_maintenance_plans (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        work_center_id INTEGER NOT NULL,
        title TEXT NOT NULL DEFAULT "",
        interval_type TEXT NOT NULL,
        interval_value REAL NOT NULL DEFAULT 0,
        planned_downtime_minutes INTEGER NOT NULL DEFAULT 0,
        helper_materials_json TEXT NULL,
        estimated_material_cost REAL NOT NULL DEFAULT 0,
        estimated_service_cost REAL NOT NULL DEFAULT 0,
        next_due_date TEXT NULL,
        is_active INTEGER NOT NULL DEFAULT 1,
        created_at TEXT DEFAULT CURRENT_TIMESTAMP,
        updated_at TEXT DEFAULT CURRENT_TIMESTAMP
    )',
    'CREATE TABLE dg_machine_maintenance_logs (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        maintenance_plan_id INTEGER NULL,
        work_center_id INTEGER NOT NULL,
        performed_at TEXT NOT NULL,
        runtime_hours_at_service REAL NULL,
        actual_material_cost REAL NOT NULL DEFAULT 0,
        actual_service_cost REAL NOT NULL DEFAULT 0,
        notes TEXT NOT NULL DEFAULT "",
        created_at TEXT DEFAULT CURRENT_TIMESTAMP
    )',
] as $sql) {
    Database::pdo()->exec($sql);
}

$capacityId = MachinePlanningRepository::createCapacityProfile([
    'work_center_id' => 1,
    'recipe_id' => 2,
    'input_material_label' => 'Rohstoff A',
    'input_qty_per_hour' => '120,5',
    'output_product_label' => 'Produkt B',
    'output_qty_per_hour' => '95.25',
    'unit' => 'kg',
    'yield_percent' => '97,5',
    'valid_from' => '2026-10-28',
    'is_active' => 1,
]);
$capacities = MachinePlanningRepository::listCapacityProfiles(1, true);
assertM0b($capacityId > 0 && count($capacities) === 1, 'Kapazitaetsprofil anlegen und aktiv listen');
assertM0b((float) $capacities[0]['input_qty_per_hour'] === 120.5, 'Kommazahl fuer Input/h wird normalisiert');
MachinePlanningRepository::deactivateCapacityProfile($capacityId);
assertM0b(MachinePlanningRepository::listCapacityProfiles(1, true) === [], 'Kapazitaetsprofil deaktivieren');

$auxId = MachinePlanningRepository::createAuxiliaryMaterial([
    'recipe_id' => 2,
    'material_label' => 'Karton',
    'role' => 'packaging',
    'qty_per_output_unit' => '1',
    'unit' => 'Stk',
    'cost_per_unit' => '0,42',
    'is_active' => 1,
]);
$aux = MachinePlanningRepository::listAuxiliaryMaterials(2, true);
assertM0b($auxId > 0 && count($aux) === 1 && $aux[0]['role'] === 'packaging', 'Zusatz-/Verpackungsmaterial anlegen und listen');
MachinePlanningRepository::deactivateAuxiliaryMaterial($auxId);
assertM0b(MachinePlanningRepository::listAuxiliaryMaterials(2, true) === [], 'Zusatzmaterial deaktivieren');

$planId = MachinePlanningRepository::createMaintenancePlan([
    'work_center_id' => 1,
    'title' => 'Monatliche Reinigung',
    'interval_type' => 'days',
    'interval_value' => 30,
    'planned_downtime_minutes' => 45,
    'helper_materials_json' => ['Reiniger', 'Dichtung'],
    'estimated_material_cost' => '12,50',
    'estimated_service_cost' => '80',
    'next_due_date' => '2026-11-30',
    'is_active' => 1,
]);
$plans = MachinePlanningRepository::listMaintenancePlans(1, true);
assertM0b($planId > 0 && count($plans) === 1, 'Wartungsplan anlegen und aktiv listen');
assertM0b(str_contains((string) $plans[0]['helper_materials_json'], 'Reiniger'), 'Hilfsmittel werden als JSON gespeichert');

$logId = MachinePlanningRepository::createMaintenanceLog([
    'maintenance_plan_id' => $planId,
    'work_center_id' => 1,
    'performed_at' => '2026-10-28 10:15:00',
    'runtime_hours_at_service' => '1234,5',
    'actual_material_cost' => '10,10',
    'actual_service_cost' => '75',
    'notes' => 'Filter gereinigt.',
]);
$logs = MachinePlanningRepository::listMaintenanceLogs(1);
assertM0b($logId > 0 && count($logs) === 1, 'Wartungslog anlegen und listen');
assertM0b((float) $logs[0]['runtime_hours_at_service'] === 1234.5, 'Laufstunden im Wartungslog werden normalisiert');
$allLogs = MachinePlanningRepository::listMaintenanceLogs();
assertM0b(count($allLogs) === 1, 'Wartungslog global listen');
MachinePlanningRepository::deactivateMaintenancePlan($planId);
assertM0b(MachinePlanningRepository::listMaintenancePlans(1, true) === [], 'Wartungsplan deaktivieren');

echo "Fertig.\n";
