<?php
declare(strict_types=1);

/**
 * FL7c: 2-Maschinen-Linie, Puffer voll → Vorgänger „steht (Puffer voll)“.
 *
 * Ohne DB: reine Regel + Label (immer).
 * Mit --live: Dev/Instanz-DB (Linie anlegen, Puffer füllen, evaluate).
 *
 * Usage:
 *   php bin/production-line-fl7c-smoke.php
 *   php bin/production-line-fl7c-smoke.php --live
 */

$root = dirname(__DIR__);
$live = in_array('--live', $argv ?? [], true);

function assertFl7c(bool $ok, string $msg): void
{
    if (!$ok) {
        fwrite(STDERR, "FEHLER: {$msg}\n");
        exit(1);
    }
    echo "OK: {$msg}\n";
}

if ($live) {
    require $root . '/bootstrap.php';
} else {
    if (!defined('DG_ROOT')) {
        define('DG_ROOT', $root);
    }
    require $root . '/src/Recipe/ProductionLineStatusService.php';
}

// --- Offline: Labels + Regeln (2 Stationen) ---
$stations = [
    ['id' => 1, 'station_order' => 1, 'work_center_id' => 101, 'label' => 'Presse'],
    ['id' => 2, 'station_order' => 2, 'work_center_id' => 102, 'label' => 'Abfüllung'],
];
$eval = ProductionLineStatusService::evaluateFromState(9001, $stations, [
    [
        'after_station_order' => 1,
        'capacity_qty' => 10,
        'min_qty' => 0,
        'current_qty' => 10,
        'unit' => 'Stk',
    ],
]);
assertFl7c(($eval['stations'][0]['status'] ?? '') === 'blocked', 'Offline: Station 1 blocked bei vollem Puffer');
$label = ProductionLineStatusService::stationStatusLabel((string) $eval['stations'][0]['status']);
assertFl7c($label === 'steht (Puffer voll)', 'Offline: Endnutzer-Text „steht (Puffer voll)“');
assertFl7c(($eval['stations'][1]['status'] ?? '') === 'running', 'Offline: Station 2 läuft bei vollem Eingang');

if (!$live) {
    echo "FL7c offline smoke OK (ohne DB).\n";
    echo "Hinweis: Dev-Live mit: php bin/production-line-fl7c-smoke.php --live\n";
    exit(0);
}

assertFl7c(Database::isConfigured(), 'Live: Datenbank verbunden');
ProductionLineStore::ensureReady();
WorkCenterRepository::ensureReady();

$pdo = Database::pdo();
$wcA = WorkCenterRepository::save([
    'name' => 'FL7c-Smoke A',
    'purchase_price' => '1000',
    'life_hours' => '1000',
    'kw' => '1',
    'space_m2' => '1',
    'operators' => '1',
    'is_active' => 1,
], null);
$wcB = WorkCenterRepository::save([
    'name' => 'FL7c-Smoke B',
    'purchase_price' => '1000',
    'life_hours' => '1000',
    'kw' => '1',
    'space_m2' => '1',
    'operators' => '1',
    'is_active' => 1,
], null);
assertFl7c($wcA > 0 && $wcB > 0, 'Live: zwei Maschinen angelegt');

$lineId = ProductionLineStore::saveLine([
    'name' => 'FL7c Smoke Linie',
    'code' => 'FL7C',
    'notes' => 'Smoke automatisiert — kann deaktiviert werden',
    'is_active' => 1,
], null);
ProductionLineStore::saveStations($lineId, [
    ['work_center_id' => $wcA, 'label' => 'A'],
    ['work_center_id' => $wcB, 'label' => 'B'],
]);
ProductionLineStore::saveBuffers($lineId, [
    [
        'after_station_order' => 1,
        'capacity_qty' => '5',
        'min_qty' => '0',
        'current_qty' => '5',
        'unit' => 'Stk',
    ],
]);

$liveEval = ProductionLineStatusService::evaluate($lineId, []);
assertFl7c(is_array($liveEval), 'Live: evaluate liefert Ergebnis');
assertFl7c(($liveEval['stations'][0]['status'] ?? '') === 'blocked', 'Live: Station 1 blocked');
$liveLabel = ProductionLineStatusService::stationStatusLabel((string) $liveEval['stations'][0]['status']);
assertFl7c($liveLabel === 'steht (Puffer voll)', 'Live: Text „steht (Puffer voll)“');

ProductionLineStore::deactivateLine($lineId);
echo "FL7c live smoke OK (line_id={$lineId}, danach deaktiviert).\n";
echo "Detail-UI: /app?page=rezeptur-fliesslinien&id={$lineId}\n";
