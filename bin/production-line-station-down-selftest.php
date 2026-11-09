<?php
declare(strict_types=1);

/**
 * FL6a: Wartung/Stör-Flag → Station down.
 *
 * Usage: php bin/production-line-station-down-selftest.php
 */

require dirname(__DIR__) . '/src/Recipe/ProductionLineStationDownGate.php';
require dirname(__DIR__) . '/src/Recipe/ProductionLineStatusService.php';

function assertFl6a(bool $ok, string $msg): void
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

$orders = ProductionLineStationDownGate::resolveDownStationOrders($stations, [2], []);
assertFl6a($orders === [2], 'manuelles Stör-Flag Station 2');

$ordersWc = ProductionLineStationDownGate::resolveDownStationOrders($stations, [], [20, 30]);
assertFl6a($ordersWc === [2, 3], 'Wartung/Sperre über Work Center');

$both = ProductionLineStationDownGate::resolveDownStationOrders($stations, [1], [20]);
assertFl6a($both === [1, 2], 'Flag + Wartung zusammen');

$merged = ProductionLineStationDownGate::mergeIntoEvaluateOptions(
    $stations,
    [],
    [2],
    [30]
);
assertFl6a($merged['options']['down_station_orders'] === [2, 3], 'Merge down_station_orders');
assertFl6a(str_contains((string) $merged['gate']['hint'], 'Außer Betrieb'), 'Hinweis');
assertFl6a(!str_contains((string) $merged['gate']['hint'], 'FL6'), 'kein Plan-Code');

$buffers = [
    ['after_station_order' => 1, 'capacity_qty' => 50, 'min_qty' => 0, 'current_qty' => 20, 'unit' => 'kg'],
    ['after_station_order' => 2, 'capacity_qty' => 50, 'min_qty' => 0, 'current_qty' => 20, 'unit' => 'kg'],
];
$status = ProductionLineStatusService::evaluateFromState(1, $stations, $buffers, $merged['options']);
assertFl6a($status['stations'][1]['status'] === 'down', 'Station 2 down');
assertFl6a($status['stations'][2]['status'] === 'down', 'Station 3 down');
assertFl6a($status['stations'][0]['status'] === 'running', 'Station 1 running');
assertFl6a($status['line_status'] === 'degraded', 'Linie degraded');

echo "FL6a Selftest bestanden.\n";
exit(0);
