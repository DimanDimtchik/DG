<?php
declare(strict_types=1);

/**
 * FL4b: grober Linien-Durchsatz = min(Station) unter Puffer-Annahmen.
 *
 * Usage: php bin/production-line-throughput-selftest.php
 */

require dirname(__DIR__) . '/src/Recipe/ProductionLineStatusService.php';

function assertFl4b(bool $ok, string $msg): void
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
];
$throughputs = [
    ['station_order' => 1, 'output_qty_per_hour' => 100, 'unit' => 'kg'],
    ['station_order' => 2, 'output_qty_per_hour' => 60, 'unit' => 'kg'],
];

$ok = ProductionLineStatusService::evaluateFromState(1, $stations, [
    [
        'after_station_order' => 1,
        'capacity_qty' => 50,
        'min_qty' => 0,
        'current_qty' => 20,
        'unit' => 'kg',
    ],
], ['throughputs' => $throughputs]);
$lt = $ok['line_throughput'];
assertFl4b(is_array($lt), 'line_throughput gesetzt');
assertFl4b((float) $lt['qty_per_hour'] === 60.0, 'Durchsatz = Engpass 60/h');
assertFl4b($lt['assumption'] === 'bottleneck_with_adequate_buffers', 'Annahme ausreichende Puffer');
assertFl4b(str_contains((string) $lt['hint'], '60'), 'Hinweis nennt Menge');
assertFl4b(!str_contains((string) $lt['hint'], 'FL4'), 'kein Plan-Code');

$constrained = ProductionLineStatusService::evaluateFromState(1, $stations, [
    [
        'after_station_order' => 1,
        'capacity_qty' => 50,
        'min_qty' => 0,
        'current_qty' => 50,
        'unit' => 'kg',
    ],
], ['throughputs' => $throughputs]);
assertFl4b($constrained['line_status'] === 'constrained', 'Linie constrained');
assertFl4b(
    is_array($constrained['line_throughput'])
    && (float) $constrained['line_throughput']['qty_per_hour'] === 60.0
    && $constrained['line_throughput']['assumption'] === 'bottleneck_rate_if_buffers_recover',
    'bei Blocking: Rate Engpass, Annahme nach Erholung'
);

$stopped = ProductionLineStatusService::evaluateFromState(1, $stations, [
    [
        'after_station_order' => 1,
        'capacity_qty' => 50,
        'min_qty' => 0,
        'current_qty' => 20,
        'unit' => 'kg',
    ],
], [
    'throughputs' => $throughputs,
    'raw_material_ok' => false,
]);
assertFl4b(
    is_array($stopped['line_throughput'])
    && (float) $stopped['line_throughput']['qty_per_hour'] === 0.0
    && $stopped['line_throughput']['assumption'] === 'zero_while_line_stopped',
    'bei Materialstopp Durchsatz 0'
);

$direct = ProductionLineStatusService::resolveLineThroughput('running', [
    'output_qty_per_hour' => 42.5,
    'unit' => 'Stk',
], []);
assertFl4b(is_array($direct) && (float) $direct['qty_per_hour'] === 42.5, 'resolveLineThroughput direkt');

echo "FL4b Selftest bestanden.\n";
exit(0);
