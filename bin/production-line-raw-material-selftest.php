<?php
declare(strict_types=1);

/**
 * FL5a: Rohware fehlt → Linie stopped_material, Station 1 starved.
 *
 * Usage: php bin/production-line-raw-material-selftest.php
 */

require dirname(__DIR__) . '/src/Recipe/ProductionLineStatusService.php';

function assertFl5a(bool $ok, string $msg): void
{
    if (!$ok) {
        fwrite(STDERR, "FEHLER: {$msg}\n");
        exit(1);
    }
    echo "OK: {$msg}\n";
}

$stations = [
    ['id' => 1, 'station_order' => 1, 'work_center_id' => 10, 'label' => 'Eingang'],
    ['id' => 2, 'station_order' => 2, 'work_center_id' => 20, 'label' => 'Folge'],
];
$buffers = [
    [
        'after_station_order' => 1,
        'capacity_qty' => 50,
        'min_qty' => 0,
        'current_qty' => 20,
        'unit' => 'kg',
    ],
];

$ok = ProductionLineStatusService::evaluateFromState(1, $stations, $buffers, [
    'raw_material_ok' => true,
]);
assertFl5a($ok['line_status'] === 'running', 'Rohware ok → Linie running');
assertFl5a($ok['raw_material']['ok'] === true, 'raw_material.ok true');
assertFl5a($ok['stations'][0]['status'] === 'running', 'Station 1 running');

$miss = ProductionLineStatusService::evaluateFromState(1, $stations, $buffers, [
    'raw_material_ok' => false,
]);
assertFl5a($miss['line_status'] === 'stopped_material', 'Rohware fehlt → stopped_material');
assertFl5a($miss['raw_material']['ok'] === false, 'raw_material.ok false');
assertFl5a($miss['stations'][0]['status'] === 'starved', 'Station 1 starved');
assertFl5a(
    in_array('raw_material_missing', $miss['stations'][0]['reasons'], true),
    'Reason raw_material_missing'
);
assertFl5a(str_contains((string) $miss['raw_material']['hint'], 'fehlt'), 'Hinweis Rohware');
assertFl5a(!str_contains((string) $miss['raw_material']['hint'], 'FL5'), 'kein Plan-Code');

// down schlägt Rohware an Station 1
$down = ProductionLineStatusService::evaluateFromState(1, $stations, $buffers, [
    'raw_material_ok' => false,
    'down_station_orders' => [1],
]);
assertFl5a($down['stations'][0]['status'] === 'down', 'down > Rohware-starved');
assertFl5a($down['line_status'] === 'stopped_material', 'Linie trotzdem stopped_material');

echo "FL5a Selftest bestanden.\n";
exit(0);
