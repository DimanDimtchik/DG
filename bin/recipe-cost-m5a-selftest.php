<?php
declare(strict_types=1);

/**
 * M5a: Zusatzmaterial muss in Kalkulation und Snapshot-Payload landen.
 */
final class RecipeCostSettings
{
    public static function get(): array
    {
        return [
            'electricity_eur_per_kwh' => 0.3,
            'room_eur_per_m2_h' => 0.0,
            'wage_eur_per_h' => 0.0,
        ];
    }
}

require dirname(__DIR__) . '/src/Recipe/RecipeCostService.php';

function assertM5a(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "FEHLER: {$message}\n");
        exit(1);
    }
    echo "OK: {$message}\n";
}

$recipe = [
    'title' => 'Testcreme',
    'target_qty' => 10,
    'labor_minutes' => 0,
    'margin_pct' => 0,
    'version' => 1,
    'status' => 'draft',
];
$bom = [[
    'material_label' => 'Rohstoff',
    'article_id' => 0,
    'qty' => 1,
    'scrap_pct' => 0,
    'unit' => 'kg',
    'unit_cost' => 2,
]];
$routing = [];
$auxiliary = [[
    'is_active' => 1,
    'material_label' => 'Karton',
    'article_id' => 0,
    'role' => 'packaging',
    'qty_per_output_unit' => 1,
    'unit' => 'Stk',
    'cost_per_unit' => 0.5,
]];

$without = RecipeCostService::calculate($recipe, $bom, $routing);
$with = RecipeCostService::calculate($recipe, $bom, $routing, null, $auxiliary);
assertM5a(abs((float) $without['k_mat'] - 2.0) < 0.0001, 'BOM allein: Menge 1 x 2 EUR = 2');
assertM5a(abs((float) $with['k_mat'] - 7.0) < 0.0001, 'Mit Zusatz: +10 x 0,50 EUR = 7');
assertM5a(count($with['auxiliary_lines'] ?? []) === 1, 'auxiliary_lines im Ergebnis');

$payload = RecipeCostService::snapshotPayload($recipe, $bom, $routing, $with, $auxiliary);
assertM5a(($payload['inputs']['auxiliary_materials'][0]['material_label'] ?? '') === 'Karton', 'Snapshot inputs enthalten Zusatzmaterial');
assertM5a(abs((float) ($payload['result']['auxiliary_lines'][0]['line_cost'] ?? 0) - 5.0) < 0.0001, 'Snapshot result enthaelt Zusatzkosten');
assertM5a(abs((float) ($payload['result']['k_mat'] ?? 0) - 7.0) < 0.0001, 'Snapshot k_mat enthaelt Zusatz');

$whatIf = RecipeCostService::calculateWhatIf($recipe, $bom, $routing, ['charge_factor' => 2], $auxiliary);
// Charge 2x: BOM 2 EUR * 2 + Zusatz 1*20*0,5 = 4 + 10 = 14
assertM5a(abs((float) ($whatIf['k_mat'] ?? 0) - 14.0) < 0.0001, 'What-if Charge 2x skaliert BOM und Zusatz');

echo "Fertig.\n";
