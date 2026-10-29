<?php
declare(strict_types=1);

/**
 * M5c: Wartungskosten-Vorschlag in Kalkulation/Snapshot, ohne SK/VK/FiBu.
 */
final class RecipeCostSettings
{
    public static function get(): array
    {
        return [
            'electricity_eur_per_kwh' => 0.0,
            'room_eur_per_m2_h' => 0.0,
            'wage_eur_per_h' => 60.0,
        ];
    }
}

final class WorkCenterRepository
{
    public static function find(int $id): ?array
    {
        if ($id !== 7) {
            return null;
        }

        return [
            'id' => 7,
            'name' => 'Mischer A',
            'purchase_price' => 0,
            'life_hours' => 1,
            'kw' => 0,
            'space_m2' => 0,
            'operators' => 1,
        ];
    }
}

require dirname(__DIR__) . '/src/Recipe/RecipeCostService.php';

function assertM5c(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "FEHLER: {$message}\n");
        exit(1);
    }
    echo "OK: {$message}\n";
}

$recipe = [
    'id' => 42,
    'title' => 'Testcreme',
    'target_qty' => 1,
    'labor_minutes' => 0,
    'margin_pct' => 0,
    'version' => 1,
    'status' => 'draft',
];
$routing = [[
    'label' => 'Mischen',
    'work_center_id' => 7,
    'setup_min' => 0,
    'run_min' => 60, // 1 h bei loaded 60 €/h → K_fert 60
]];
$plans = [
    [
        'id' => 1,
        'work_center_id' => 7,
        'title' => 'Ölwechsel',
        'interval_type' => 'runtime_hours',
        'interval_value' => 100,
        'planned_downtime_minutes' => 30,
        'estimated_material_cost' => 40,
        'estimated_service_cost' => 60,
        'is_active' => 1,
    ],
    [
        'id' => 2,
        'work_center_id' => 7,
        'title' => 'Filter je 10 Chargen',
        'interval_type' => 'cycles',
        'interval_value' => 10,
        'planned_downtime_minutes' => 10,
        'estimated_material_cost' => 20,
        'estimated_service_cost' => 0,
        'is_active' => 1,
    ],
    [
        'id' => 3,
        'work_center_id' => 7,
        'title' => 'Monatscheck',
        'interval_type' => 'days',
        'interval_value' => 30,
        'planned_downtime_minutes' => 60,
        'estimated_material_cost' => 5,
        'estimated_service_cost' => 95,
        'is_active' => 1,
    ],
];

// runtime: 1/100 * 100 = 1 €; cycles: 1/10 * 20 = 2 €; days: 0 → k_maint = 3
$calc = RecipeCostService::calculate($recipe, [], $routing, null, [], [], $plans);
assertM5c(count($calc['maintenance_lines'] ?? []) === 3, 'drei Wartungszeilen');
assertM5c(abs((float) ($calc['k_maint_suggested'] ?? 0) - 3.0) < 0.0001, 'k_maint_suggested = 1 + 2');
assertM5c(abs((float) ($calc['self_cost'] ?? 0) - 60.0) < 0.0001, 'SK bleibt ohne Wartung (nur Routing)');
assertM5c(abs((float) ($calc['vk'] ?? 0) - 60.0) < 0.0001, 'VK bleibt ohne Wartung');
assertM5c(abs((float) ($calc['self_cost_with_maint_suggested'] ?? 0) - 63.0) < 0.0001, 'SK inkl. Wartungsvorschlag separat');

$runtimeLine = null;
$daysLine = null;
foreach ($calc['maintenance_lines'] as $line) {
    if (($line['maintenance_plan_id'] ?? 0) === 1) {
        $runtimeLine = $line;
    }
    if (($line['maintenance_plan_id'] ?? 0) === 3) {
        $daysLine = $line;
    }
}
assertM5c(($runtimeLine['allocation'] ?? '') === 'runtime_hours', 'Ölwechsel allocation runtime_hours');
assertM5c(abs((float) ($runtimeLine['suggested_share'] ?? 0) - 1.0) < 0.0001, 'Ölwechsel Anteil 1 EUR');
assertM5c(($daysLine['allocation'] ?? '') === 'none', 'Tage-Turnus nicht anteilig');
assertM5c(abs((float) ($daysLine['suggested_share'] ?? -1) - 0.0) < 0.0001, 'Tage-Anteil 0');
assertM5c(abs((float) ($daysLine['estimated_total'] ?? 0) - 100.0) < 0.0001, 'Tage-Plankosten trotzdem sichtbar');

$payload = RecipeCostService::snapshotPayload($recipe, [], $routing, $calc, []);
assertM5c(count($payload['inputs']['maintenance_lines'] ?? []) === 3, 'Snapshot inputs Wartung');
assertM5c(abs((float) ($payload['result']['k_maint_suggested'] ?? 0) - 3.0) < 0.0001, 'Snapshot result k_maint');
assertM5c(abs((float) ($payload['result']['self_cost'] ?? 0) - 60.0) < 0.0001, 'Snapshot SK unverändert');

$shareOnly = RecipeCostService::maintenanceSuggestedShare([
    'interval_type' => 'runtime_hours',
    'interval_value' => 50,
    'estimated_material_cost' => 10,
    'estimated_service_cost' => 40,
], 2.5);
assertM5c(abs($shareOnly['suggested_share'] - 2.5) < 0.0001, 'Helper: 2,5h/50h × 50 EUR = 2,5');

echo "Fertig.\n";
