<?php
declare(strict_types=1);

/**
 * FL6c: eine Station down → Nachbarn laut Regel (FL6b).
 *
 * Usage: php bin/production-line-down-neighbors-selftest.php
 */

require dirname(__DIR__) . '/src/Recipe/ProductionLineStatusService.php';

function assertFl6c(bool $ok, string $msg): void
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
    ['after_station_order' => 1, 'capacity_qty' => 50, 'min_qty' => 0, 'current_qty' => 20, 'unit' => 'kg'],
    ['after_station_order' => 2, 'capacity_qty' => 50, 'min_qty' => 0, 'current_qty' => 20, 'unit' => 'kg'],
];

// Mitte down
$mid = ProductionLineStatusService::evaluateFromState(1, $stations, $buffers, [
    'down_station_orders' => [2],
]);
assertFl6c($mid['stations'][1]['status'] === 'down', 'Mitte: B down');
assertFl6c($mid['stations'][0]['status'] === 'running' && !empty($mid['stations'][0]['block_risk']), 'Mitte: A block_risk');
assertFl6c($mid['stations'][2]['status'] === 'starved', 'Mitte: C starved');
assertFl6c(
    in_array('upstream_down', $mid['stations'][2]['reasons'], true)
    && in_array('downstream_down_block_risk', $mid['stations'][0]['reasons'], true),
    'Mitte: Reasons Nachbarn'
);

// Erste Station down: kein Vorgänger, Nachfolger starved
$first = ProductionLineStatusService::evaluateFromState(1, $stations, $buffers, [
    'down_station_orders' => [1],
]);
assertFl6c($first['stations'][0]['status'] === 'down', 'Erste: A down');
assertFl6c($first['stations'][1]['status'] === 'starved', 'Erste: B starved');
assertFl6c(empty($first['stations'][1]['block_risk']), 'Erste: B kein block_risk');
assertFl6c(
    !empty($first['buffers'][0]['starve_risk_due_to_upstream_down']),
    'Erste: Puffer nach A starve_risk'
);

// Letzte Station down: Vorgänger block_risk, kein Nachfolger
$last = ProductionLineStatusService::evaluateFromState(1, $stations, $buffers, [
    'down_station_orders' => [3],
]);
assertFl6c($last['stations'][2]['status'] === 'down', 'Letzte: C down');
assertFl6c($last['stations'][1]['status'] === 'running' && !empty($last['stations'][1]['block_risk']), 'Letzte: B block_risk');
assertFl6c($last['stations'][0]['status'] === 'running', 'Letzte: A unberührt running');
assertFl6c(
    !empty($last['buffers'][1]['fill_risk_due_to_downstream_down']),
    'Letzte: Puffer vor C fill_risk'
);

// Gate + Propagation (wie FL6a Merge)
assertFl6c(
    class_exists('ProductionLineStationDownGate')
        || is_file(dirname(__DIR__) . '/src/Recipe/ProductionLineStationDownGate.php'),
    'Down-Gate vorhanden'
);

echo "FL6c Selftest bestanden.\n";
exit(0);
