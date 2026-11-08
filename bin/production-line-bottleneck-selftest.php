<?php
declare(strict_types=1);

/**
 * FL4a: Engpass = geringster Output/h + Hinweistext.
 *
 * Usage: php bin/production-line-bottleneck-selftest.php
 */

require dirname(__DIR__) . '/src/Recipe/ProductionLineStatusService.php';

function assertFl4a(bool $ok, string $msg): void
{
    if (!$ok) {
        fwrite(STDERR, "FEHLER: {$msg}\n");
        exit(1);
    }
    echo "OK: {$msg}\n";
}

$stations = [
    ['id' => 1, 'station_order' => 1, 'work_center_id' => 10, 'label' => 'Mischer'],
    ['id' => 2, 'station_order' => 2, 'work_center_id' => 20, 'label' => 'Ofen'],
    ['id' => 3, 'station_order' => 3, 'work_center_id' => 30, 'label' => 'Packer'],
];

$r = ProductionLineStatusService::evaluateFromState(1, $stations, [], [
    'throughputs' => [
        ['station_order' => 1, 'output_qty_per_hour' => 120, 'unit' => 'kg'],
        ['station_order' => 2, 'output_qty_per_hour' => 80, 'unit' => 'kg'],
        ['station_order' => 3, 'output_qty_per_hour' => 100, 'unit' => 'kg'],
    ],
]);
$bn = $r['bottleneck'];
assertFl4a(is_array($bn), 'Engpass erkannt');
assertFl4a((int) $bn['station_order'] === 2, 'Engpass = Station 2 (80/h)');
assertFl4a((float) $bn['output_qty_per_hour'] === 80.0, 'Output/h 80');
assertFl4a(str_contains((string) $bn['hint'], 'Langsamste Station'), 'Hinweis nennt langsamste Station');
assertFl4a(str_contains((string) $bn['hint'], 'Ofen'), 'Hinweis nennt Station');
assertFl4a(str_contains((string) $bn['hint'], '80'), 'Hinweis nennt Menge');
assertFl4a(!str_contains((string) $bn['hint'], 'FL4'), 'kein Plan-Code im Hinweis');

$none = ProductionLineStatusService::evaluateFromState(1, $stations, [], [
    'throughputs' => [
        ['station_order' => 1, 'output_qty_per_hour' => 0, 'unit' => 'kg'],
    ],
]);
assertFl4a($none['bottleneck'] === null, 'ohne positiven Output/h kein Engpass');

$tie = ProductionLineStatusService::resolveBottleneck(
    [
        ['station_id' => 1, 'station_order' => 1, 'work_center_id' => 10, 'label' => 'A'],
        ['station_id' => 2, 'station_order' => 2, 'work_center_id' => 20, 'label' => 'B'],
    ],
    [
        ['station_order' => 1, 'output_qty_per_hour' => 50, 'unit' => 'Stk'],
        ['station_order' => 2, 'output_qty_per_hour' => 50, 'unit' => 'Stk'],
    ]
);
assertFl4a(is_array($tie) && (int) $tie['station_order'] === 1, 'Gleichstand: erste (kleinste Order) gewinnt');

echo "FL4a Selftest bestanden.\n";
exit(0);
