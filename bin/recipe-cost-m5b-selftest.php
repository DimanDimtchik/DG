<?php
declare(strict_types=1);

/**
 * M5b: Kapazitätsprofil füllt run_min=0 und landet im Snapshot; Abweichung warnt.
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
require dirname(__DIR__) . '/src/Recipe/RecipeActualRepository.php';

function assertM5b(bool $condition, string $message): void
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
    'target_qty' => 30,
    'labor_minutes' => 0,
    'margin_pct' => 0,
    'version' => 1,
    'status' => 'draft',
];
$bom = [];
$routingZero = [[
    'label' => 'Mischen',
    'work_center_id' => 7,
    'setup_min' => 0,
    'run_min' => 0,
]];
$profiles = [[
    'id' => 3,
    'work_center_id' => 7,
    'recipe_id' => 42,
    'output_qty_per_hour' => 60,
    'yield_percent' => 100,
    'unit' => 'kg',
    'is_active' => 1,
]];

// 30 kg / 60 kg/h = 0,5 h = 30 Min.; loaded 60 €/h → K_fert 30
$filled = RecipeCostService::calculate($recipe, $bom, $routingZero, null, [], $profiles);
assertM5b(abs((float) ($filled['routing_steps'][0]['run_min'] ?? 0) - 30.0) < 0.0001, 'run_min=0 wird aus Kapazität 30 Min.');
assertM5b(($filled['routing_steps'][0]['run_source'] ?? '') === 'capacity', 'run_source=capacity');
assertM5b(count($filled['capacity_lines'] ?? []) === 1, 'capacity_lines im Ergebnis');
assertM5b(abs((float) ($filled['k_fert'] ?? 0) - 30.0) < 0.0001, 'K_fert nutzt Kapazitäts-Laufzeit');

$payload = RecipeCostService::snapshotPayload($recipe, $bom, $routingZero, $filled, []);
assertM5b(count($payload['inputs']['capacity_lines'] ?? []) === 1, 'Snapshot inputs enthalten capacity_lines');
assertM5b(($payload['result']['routing_steps'][0]['run_source'] ?? '') === 'capacity', 'Snapshot result speichert run_source');
assertM5b(abs((float) ($payload['result']['capacity_lines'][0]['run_min_capacity'] ?? 0) - 30.0) < 0.0001, 'Snapshot result capacity run');

$planned = RecipeActualRepository::plannedFromRecipe($recipe, $routingZero, $filled);
assertM5b(abs((float) $planned['planned_run_min'] - 30.0) < 0.0001, 'Soll-Lauf aus capacity-routing_steps');

$routingManual = [[
    'label' => 'Mischen',
    'work_center_id' => 7,
    'setup_min' => 0,
    'run_min' => 10,
]];
$warned = RecipeCostService::calculate($recipe, $bom, $routingManual, null, [], $profiles);
assertM5b(($warned['routing_steps'][0]['run_source'] ?? '') === 'routing', 'manuelle Laufzeit bleibt maßgeblich');
assertM5b(abs((float) ($warned['routing_steps'][0]['run_min'] ?? 0) - 10.0) < 0.0001, 'manuelle 10 Min. unverändert');
$warnText = implode(' ', $warned['warnings'] ?? []);
assertM5b(str_contains($warnText, 'Kapazität'), 'Abweichung >15% erzeugt Kapazitätshinweis');

$genericOnly = [[
    'id' => 9,
    'work_center_id' => 7,
    'recipe_id' => 0,
    'output_qty_per_hour' => 30,
    'yield_percent' => 100,
    'unit' => 'kg',
    'is_active' => 1,
]];
$fallback = RecipeCostService::calculate($recipe, $bom, $routingZero, null, [], $genericOnly);
// 30/30 * 60 = 60 Min.
assertM5b(abs((float) ($fallback['routing_steps'][0]['run_min'] ?? 0) - 60.0) < 0.0001, 'Fallback-Profil ohne recipe_id greift');

echo "Fertig.\n";
