<?php
declare(strict_types=1);

/**
 * FL5b: Hilfsstoff fehlt ab Station k → ab k starved.
 *
 * Usage: php bin/production-line-aux-material-selftest.php
 */

require dirname(__DIR__) . '/src/Recipe/ProductionLineStatusService.php';

function assertFl5b(bool $ok, string $msg): void
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
$buffers = [
    [
        'after_station_order' => 1,
        'capacity_qty' => 100,
        'min_qty' => 0,
        'current_qty' => 40,
        'unit' => 'kg',
    ],
    [
        'after_station_order' => 2,
        'capacity_qty' => 100,
        'min_qty' => 0,
        'current_qty' => 40,
        'unit' => 'kg',
    ],
];

$ok = ProductionLineStatusService::evaluateFromState(1, $stations, $buffers, []);
assertFl5b($ok['auxiliary_material']['ok'] === true, 'Hilfsstoff ok (Default)');
assertFl5b($ok['line_status'] === 'running', 'Linie running');

$r = ProductionLineStatusService::evaluateFromState(1, $stations, $buffers, [
    'missing_aux_from_station_order' => 2,
]);
assertFl5b($r['stations'][0]['status'] === 'running', 'Station 1 weiter running');
assertFl5b($r['stations'][1]['status'] === 'starved', 'Station 2 starved');
assertFl5b($r['stations'][2]['status'] === 'starved', 'Station 3 starved');
assertFl5b(
    in_array('aux_material_missing', $r['stations'][1]['reasons'], true),
    'Reason aux_material_missing'
);
assertFl5b($r['line_status'] === 'constrained', 'Linie constrained (A läuft noch)');
assertFl5b($r['auxiliary_material']['ok'] === false, 'auxiliary_material.ok false');
assertFl5b((int) $r['auxiliary_material']['from_station_order'] === 2, 'from_station_order 2');
assertFl5b(str_contains((string) $r['auxiliary_material']['hint'], 'Station 2'), 'Hinweis Station');
assertFl5b(!str_contains((string) $r['auxiliary_material']['hint'], 'FL5'), 'kein Plan-Code');

$all = ProductionLineStatusService::evaluateFromState(1, $stations, $buffers, [
    'missing_aux_from_station_order' => 1,
]);
assertFl5b($all['line_status'] === 'stopped', 'ab Station 1: Linie stopped');

$prio = ProductionLineStatusService::evaluateFromState(1, $stations, $buffers, [
    'missing_aux_from_station_order' => 2,
    'down_station_orders' => [2],
]);
assertFl5b($prio['stations'][1]['status'] === 'down', 'down > aux-starved');
assertFl5b($prio['stations'][2]['status'] === 'starved', 'Station 3 weiter aux-starved');

echo "FL5b Selftest bestanden.\n";
exit(0);
