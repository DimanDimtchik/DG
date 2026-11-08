<?php
declare(strict_types=1);

/**
 * FL3c: Puffer voll → Vorgänger blocked; leer → Nachfolger starved.
 *
 * Usage: php bin/production-line-status-selftest.php
 */

require dirname(__DIR__) . '/src/Recipe/ProductionLineStatusService.php';

function assertFl3c(bool $ok, string $msg): void
{
    if (!$ok) {
        fwrite(STDERR, "FEHLER: {$msg}\n");
        exit(1);
    }
    echo "OK: {$msg}\n";
}

$stations = [
    ['id' => 1, 'station_order' => 1, 'work_center_id' => 10, 'label' => 'A'],
    ['id' => 2, 'station_order' => 2, 'work_center_id' => 20, 'label' => 'B'],
    ['id' => 3, 'station_order' => 3, 'work_center_id' => 30, 'label' => 'C'],
];

// Puffer nach 1 voll → Station 1 blocked; Station 2/3 running (Material da)
$full = ProductionLineStatusService::evaluateFromState(7, $stations, [
    [
        'after_station_order' => 1,
        'capacity_qty' => 100,
        'min_qty' => 0,
        'current_qty' => 100,
        'unit' => 'kg',
    ],
    [
        'after_station_order' => 2,
        'capacity_qty' => 50,
        'min_qty' => 0,
        'current_qty' => 25,
        'unit' => 'kg',
    ],
]);
assertFl3c($full['stations'][0]['status'] === 'blocked', 'Puffer voll → Vorgänger blocked');
assertFl3c(in_array('buffer_full', $full['stations'][0]['reasons'], true), 'Reason buffer_full');
assertFl3c($full['stations'][1]['status'] === 'running', 'Nachfolger bei vollem Eingang nicht starved');
assertFl3c($full['buffers'][0]['blocks_predecessor'] === true, 'blocks_predecessor Flag');
assertFl3c($full['line_status'] === 'constrained', 'Linie constrained bei Blocking');

// Puffer nach 1 leer → Station 2 starved; Station 1 running
$empty = ProductionLineStatusService::evaluateFromState(7, $stations, [
    [
        'after_station_order' => 1,
        'capacity_qty' => 100,
        'min_qty' => 5,
        'current_qty' => 0,
        'unit' => 'kg',
    ],
    [
        'after_station_order' => 2,
        'capacity_qty' => 50,
        'min_qty' => 0,
        'current_qty' => 10,
        'unit' => 'kg',
    ],
]);
assertFl3c($empty['stations'][0]['status'] === 'running', 'Vorgänger bei leerem Ausgang nicht blocked');
assertFl3c($empty['stations'][1]['status'] === 'starved', 'Puffer leer → Nachfolger starved');
assertFl3c(in_array('buffer_empty', $empty['stations'][1]['reasons'], true), 'Reason buffer_empty');
assertFl3c($empty['buffers'][0]['starves_successor'] === true, 'starves_successor Flag');
assertFl3c($empty['line_status'] === 'constrained', 'Linie constrained bei Starving');

// current == min_qty → starved
$atMin = ProductionLineStatusService::evaluateFromState(7, $stations, [
    [
        'after_station_order' => 1,
        'capacity_qty' => 100,
        'min_qty' => 5,
        'current_qty' => 5,
        'unit' => 'kg',
    ],
    [
        'after_station_order' => 2,
        'capacity_qty' => 50,
        'min_qty' => 0,
        'current_qty' => 10,
        'unit' => 'kg',
    ],
]);
assertFl3c($atMin['stations'][1]['status'] === 'starved', 'current == min_qty → starved');

// alle lauffähig
$ok = ProductionLineStatusService::evaluateFromState(7, $stations, [
    [
        'after_station_order' => 1,
        'capacity_qty' => 100,
        'min_qty' => 0,
        'current_qty' => 40,
        'unit' => 'kg',
    ],
    [
        'after_station_order' => 2,
        'capacity_qty' => 50,
        'min_qty' => 0,
        'current_qty' => 20,
        'unit' => 'kg',
    ],
]);
assertFl3c(
    $ok['stations'][0]['status'] === 'running'
    && $ok['stations'][1]['status'] === 'running'
    && $ok['stations'][2]['status'] === 'running'
    && $ok['line_status'] === 'running',
    'mittlere Füllstände → alles running'
);

// down schlägt blocked
$down = ProductionLineStatusService::evaluateFromState(7, $stations, [
    [
        'after_station_order' => 1,
        'capacity_qty' => 100,
        'min_qty' => 0,
        'current_qty' => 100,
        'unit' => 'kg',
    ],
    [
        'after_station_order' => 2,
        'capacity_qty' => 50,
        'min_qty' => 0,
        'current_qty' => 25,
        'unit' => 'kg',
    ],
], ['down_station_orders' => [1]]);
assertFl3c($down['stations'][0]['status'] === 'down', 'down > blocked');
assertFl3c($down['line_status'] === 'degraded', 'Linie degraded bei down + running');

// Rohware fehlt
$mat = ProductionLineStatusService::evaluateFromState(7, $stations, [
    [
        'after_station_order' => 1,
        'capacity_qty' => 100,
        'min_qty' => 0,
        'current_qty' => 40,
        'unit' => 'kg',
    ],
    [
        'after_station_order' => 2,
        'capacity_qty' => 50,
        'min_qty' => 0,
        'current_qty' => 20,
        'unit' => 'kg',
    ],
], ['raw_material_ok' => false]);
assertFl3c($mat['line_status'] === 'stopped_material', 'Rohware fehlt → stopped_material');

echo "FL3c Selftest bestanden.\n";
exit(0);
