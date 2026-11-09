<?php
declare(strict_types=1);

/**
 * FL5c: Materialbedarf vs. Lager — nur lesen (Fixture, kein Buchen).
 *
 * Usage: php bin/production-line-material-stock-selftest.php
 */

require dirname(__DIR__) . '/src/Recipe/ProductionLineMaterialStockGate.php';
require dirname(__DIR__) . '/src/Recipe/ProductionLineStatusService.php';

function assertFl5c(bool $ok, string $msg): void
{
    if (!$ok) {
        fwrite(STDERR, "FEHLER: {$msg}\n");
        exit(1);
    }
    echo "OK: {$msg}\n";
}

/** @var array<int, float> $fixture */
$fixture = [
    101 => 50.0,  // Rohware
    201 => 10.0,  // Hilfsstoff Station 2
    202 => 0.0,   // Hilfsstoff Station 3 leer
];

$resolver = static function (int $articleId) use ($fixture): ?float {
    return array_key_exists($articleId, $fixture) ? $fixture[$articleId] : null;
};

$gateOk = ProductionLineMaterialStockGate::evaluateRequirements([
    ['article_id' => 101, 'required_qty' => 20, 'role' => 'raw', 'label' => 'Mehl'],
    ['article_id' => 201, 'required_qty' => 5, 'role' => 'aux', 'station_order' => 2, 'label' => 'Öl'],
], $resolver);
assertFl5c($gateOk['raw_material_ok'] === true, 'Rohware reicht');
assertFl5c($gateOk['missing_aux_from_station_order'] === null, 'Hilfsstoff ok');
assertFl5c($gateOk['booked'] === false, 'kein Buchen (booked=false)');

$gateRaw = ProductionLineMaterialStockGate::evaluateRequirements([
    ['article_id' => 101, 'required_qty' => 80, 'role' => 'raw'],
], $resolver);
assertFl5c($gateRaw['raw_material_ok'] === false, 'Rohware zu wenig');

$gateAux = ProductionLineMaterialStockGate::evaluateRequirements([
    ['article_id' => 101, 'required_qty' => 10, 'role' => 'raw'],
    ['article_id' => 201, 'required_qty' => 5, 'role' => 'aux', 'station_order' => 2],
    ['article_id' => 202, 'required_qty' => 1, 'role' => 'aux', 'station_order' => 3],
], $resolver);
assertFl5c((int) $gateAux['missing_aux_from_station_order'] === 3, 'erster fehlender Hilfsstoff = Station 3');

$untracked = ProductionLineMaterialStockGate::evaluateRequirements([
    ['article_id' => 999, 'required_qty' => 100, 'role' => 'raw'],
], $resolver);
assertFl5c($untracked['raw_material_ok'] === true, 'ohne Fixture-Bestand nicht blockieren');

$stations = [
    ['id' => 1, 'station_order' => 1, 'work_center_id' => 10, 'label' => 'A'],
    ['id' => 2, 'station_order' => 2, 'work_center_id' => 20, 'label' => 'B'],
    ['id' => 3, 'station_order' => 3, 'work_center_id' => 30, 'label' => 'C'],
];
$buffers = [
    ['after_station_order' => 1, 'capacity_qty' => 50, 'min_qty' => 0, 'current_qty' => 20, 'unit' => 'kg'],
    ['after_station_order' => 2, 'capacity_qty' => 50, 'min_qty' => 0, 'current_qty' => 20, 'unit' => 'kg'],
];

$merged = ProductionLineMaterialStockGate::mergeIntoEvaluateOptions([
    ['article_id' => 101, 'required_qty' => 10, 'role' => 'raw'],
    ['article_id' => 202, 'required_qty' => 1, 'role' => 'aux', 'station_order' => 2],
], [], $resolver);
$status = ProductionLineStatusService::evaluateFromState(1, $stations, $buffers, $merged['options']);
assertFl5c($status['line_status'] === 'constrained', 'Status mit Hilfsstoff-Mangel constrained');
assertFl5c($status['stations'][0]['status'] === 'running', 'Station 1 running');
assertFl5c($status['stations'][1]['status'] === 'starved', 'ab Station 2 starved');
assertFl5c($merged['gate']['booked'] === false, 'Gate booked bleibt false');

echo "FL5c Selftest bestanden.\n";
exit(0);
