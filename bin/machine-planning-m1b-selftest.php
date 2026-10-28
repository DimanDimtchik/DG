<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/autoload.php';

$recipe = [
    'title' => 'M1b Testrezept',
    'target_qty' => 10,
    'labor_minutes' => 0,
    'margin_pct' => 0,
];

$bom = [
    [
        'material_label' => 'Grundstoff',
        'article_id' => 0,
        'qty' => 2,
        'scrap_pct' => 0,
        'unit' => 'kg',
        'unit_cost' => 3,
    ],
];

$auxiliary = [
    [
        'id' => 1,
        'material_label' => 'Flasche',
        'article_id' => 0,
        'role' => 'packaging',
        'qty_per_output_unit' => 1,
        'unit' => 'Stk',
        'cost_per_unit' => 0.50,
        'is_active' => 1,
    ],
    [
        'id' => 2,
        'material_label' => 'Altes Etikett',
        'article_id' => 0,
        'role' => 'packaging',
        'qty_per_output_unit' => 1,
        'unit' => 'Stk',
        'cost_per_unit' => 99,
        'is_active' => 0,
    ],
];

$rates = [
    'electricity_eur_per_kwh' => 0.30,
    'room_eur_per_m2_h' => 0.0,
    'wage_eur_per_h' => 0.0,
];

$calc = RecipeCostService::calculate($recipe, $bom, [], $rates, $auxiliary);

$expectedKmat = 11.0; // 2 kg * 3 EUR + 10 Flaschen * 0,50 EUR; inaktive Zeile ignoriert.
if (abs(((float) $calc['k_mat']) - $expectedKmat) > 0.0001) {
    fwrite(STDERR, 'K_mat falsch: ' . (string) $calc['k_mat'] . PHP_EOL);
    exit(1);
}
if (count($calc['auxiliary_lines'] ?? []) !== 1) {
    fwrite(STDERR, 'Aktive Zusatzmaterial-Zeilen falsch gezählt.' . PHP_EOL);
    exit(1);
}
if (($calc['auxiliary_lines'][0]['line_cost'] ?? null) !== 5.0) {
    fwrite(STDERR, 'Zusatzmaterial-Kosten falsch.' . PHP_EOL);
    exit(1);
}

echo 'OK: M1b Zusatzmaterial wird in K_mat gerechnet und inaktive Zeilen werden ignoriert.' . PHP_EOL;
