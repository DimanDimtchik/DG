<?php
declare(strict_types=1);

/**
 * FL6b: down bei i → Blocking-Risiko davor, Starving danach.
 *
 * Usage: php bin/production-line-down-propagation-selftest.php
 */

require dirname(__DIR__) . '/src/Recipe/ProductionLineStatusService.php';

function assertFl6b(bool $ok, string $msg): void
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

$r = ProductionLineStatusService::evaluateFromState(1, $stations, $buffers, [
    'down_station_orders' => [2],
]);
assertFl6b($r['stations'][1]['status'] === 'down', 'Station 2 down');
assertFl6b($r['stations'][0]['status'] === 'running', 'Vorgänger bleibt running');
assertFl6b(!empty($r['stations'][0]['block_risk']), 'Vorgänger block_risk');
assertFl6b(
    in_array('downstream_down_block_risk', $r['stations'][0]['reasons'], true),
    'Reason downstream_down_block_risk'
);
assertFl6b($r['stations'][2]['status'] === 'starved', 'Nachfolger starved');
assertFl6b(
    in_array('upstream_down', $r['stations'][2]['reasons'], true),
    'Reason upstream_down'
);
assertFl6b(!empty($r['buffers'][0]['fill_risk_due_to_downstream_down']), 'Puffer davor fill_risk');
assertFl6b(!empty($r['buffers'][1]['starve_risk_due_to_upstream_down']), 'Puffer danach starve_risk');
assertFl6b($r['line_status'] === 'degraded', 'Linie degraded');
assertFl6b($r['propagation']['events'] !== [], 'propagation.events');
assertFl6b(!str_contains((string) $r['propagation']['hint'], 'FL6'), 'kein Plan-Code im Hint');

echo "FL6b Selftest bestanden.\n";
exit(0);
