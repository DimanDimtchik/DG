<?php
declare(strict_types=1);

/**
 * Kostenformeln Rezeptur (Norm laut Spec).
 *
 * K_mat   = Σ ( Menge × EK × (1 + Verschnitt%) )
 * K_masch = (Anschaffung / Nutzungsdauer_h) + (kW × €/kWh) + (m² × Raum€/h)
 * K_fert  = (Rüst + Lauf)/60 × (K_masch + Bediener × Lohn)  [Routing]
 *         + labor_minutes/60 × Lohn                         [Fallback/Zusatz ohne Maschine]
 * Selbstkosten = K_mat + K_fert
 * VK = Selbstkosten × (1 + Marge%)
 */
final class RecipeCostService
{
    /**
     * @param array<string, mixed> $workCenter
     * @param array{electricity_eur_per_kwh?: float, room_eur_per_m2_h?: float, wage_eur_per_h?: float}|null $rates
     */
    public static function machineHourlyRate(array $workCenter, ?array $rates = null): float
    {
        $rates = $rates ?? RecipeCostSettings::get();
        $purchase = self::f($workCenter['purchase_price'] ?? 0);
        $life = self::f($workCenter['life_hours'] ?? 0);
        if ($life <= 0) {
            $life = 1.0;
        }
        $kw = self::f($workCenter['kw'] ?? 0);
        $space = self::f($workCenter['space_m2'] ?? 0);
        $elec = self::f($rates['electricity_eur_per_kwh'] ?? 0);
        $room = self::f($rates['room_eur_per_m2_h'] ?? 0);

        return ($purchase / $life) + ($kw * $elec) + ($space * $room);
    }

    /**
     * @param array<string, mixed> $workCenter
     * @param array{electricity_eur_per_kwh?: float, room_eur_per_m2_h?: float, wage_eur_per_h?: float}|null $rates
     */
    public static function loadedHourlyRate(array $workCenter, ?array $rates = null): float
    {
        $rates = $rates ?? RecipeCostSettings::get();
        $operators = self::f($workCenter['operators'] ?? 0);
        $wage = self::f($rates['wage_eur_per_h'] ?? 0);

        return self::machineHourlyRate($workCenter, $rates) + ($operators * $wage);
    }

    /**
     * @param array<string, mixed> $workCenter
     * @param array{electricity_eur_per_kwh?: float, room_eur_per_m2_h?: float, wage_eur_per_h?: float}|null $rates
     * @return array{
     *   depreciation_per_h: float,
     *   energy_per_h: float,
     *   room_per_h: float,
     *   machine_per_h: float,
     *   labor_per_h: float,
     *   loaded_per_h: float
     * }
     */
    public static function breakdown(array $workCenter, ?array $rates = null): array
    {
        $rates = $rates ?? RecipeCostSettings::get();
        $purchase = self::f($workCenter['purchase_price'] ?? 0);
        $life = self::f($workCenter['life_hours'] ?? 0);
        if ($life <= 0) {
            $life = 1.0;
        }
        $kw = self::f($workCenter['kw'] ?? 0);
        $space = self::f($workCenter['space_m2'] ?? 0);
        $operators = self::f($workCenter['operators'] ?? 0);
        $elec = self::f($rates['electricity_eur_per_kwh'] ?? 0);
        $room = self::f($rates['room_eur_per_m2_h'] ?? 0);
        $wage = self::f($rates['wage_eur_per_h'] ?? 0);

        $depreciation = $purchase / $life;
        $energy = $kw * $elec;
        $roomCost = $space * $room;
        $machine = $depreciation + $energy + $roomCost;
        $labor = $operators * $wage;

        return [
            'depreciation_per_h' => $depreciation,
            'energy_per_h' => $energy,
            'room_per_h' => $roomCost,
            'machine_per_h' => $machine,
            'labor_per_h' => $labor,
            'loaded_per_h' => $machine + $labor,
        ];
    }

    /**
     * Vorkalkulation für ein gespeichertes Rezept.
     *
     * @return array<string, mixed>
     */
    public static function calculateForRecipeId(int $recipeId): array
    {
        $form = RecipeRepository::formForId($recipeId);
        if (($form['title'] ?? '') === '' && RecipeRepository::find($recipeId) === null) {
            throw new InvalidArgumentException('Rezept nicht gefunden.');
        }

        return self::calculate($form, $form['bom'] ?? [], $form['routing'] ?? []);
    }

    /**
     * @param array<string, mixed> $recipe title/target_qty/labor_minutes/margin_pct
     * @param list<array<string, mixed>> $bomLines
     * @param list<array<string, mixed>> $routingLines
     * @param array{electricity_eur_per_kwh?: float, room_eur_per_m2_h?: float, wage_eur_per_h?: float}|null $rates
     * @return array<string, mixed>
     */
    public static function calculate(array $recipe, array $bomLines, array $routingLines, ?array $rates = null): array
    {
        $rates = $rates ?? RecipeCostSettings::get();
        $marginPct = self::f($recipe['margin_pct'] ?? 0);
        $laborMinutes = self::f($recipe['labor_minutes'] ?? 0);
        $targetQty = self::f($recipe['target_qty'] ?? 1);
        if ($targetQty <= 0) {
            $targetQty = 1.0;
        }

        $matLines = [];
        $kMat = 0.0;
        $warnings = [];
        foreach ($bomLines as $line) {
            if (!is_array($line)) {
                continue;
            }
            $label = trim((string) ($line['material_label'] ?? ''));
            $articleId = (int) ($line['article_id'] ?? 0);
            $qty = self::f($line['qty'] ?? 0);
            $scrap = self::f($line['scrap_pct'] ?? 0);
            if ($label === '' && $articleId <= 0 && $qty <= 0) {
                continue;
            }
            $resolved = self::resolveUnitCost($line);
            $ek = $resolved['cost'];
            $ekSource = $resolved['source'];
            $factor = 1.0 + ($scrap / 100.0);
            $lineCost = $qty * $ek * $factor;
            $kMat += $lineCost;
            if ($ek <= 0.0) {
                $warnings[] = 'Kein EK für „' . ($label !== '' ? $label : ('Artikel #' . $articleId)) . '“.';
            }
            $matLines[] = [
                'material_label' => $label,
                'article_id' => $articleId,
                'qty' => $qty,
                'scrap_pct' => $scrap,
                'unit' => (string) ($line['unit'] ?? 'Stk'),
                'unit_cost' => $ek,
                'ek_source' => $ekSource,
                'line_cost' => $lineCost,
            ];
        }

        $routeSteps = [];
        $kFertRouting = 0.0;
        foreach ($routingLines as $step) {
            if (!is_array($step)) {
                continue;
            }
            $wcId = (int) ($step['work_center_id'] ?? 0);
            if ($wcId <= 0) {
                continue;
            }
            $wc = WorkCenterRepository::find($wcId);
            if ($wc === null) {
                $warnings[] = 'Arbeitsplatz #' . $wcId . ' fehlt.';
                continue;
            }
            $setup = self::f($step['setup_min'] ?? 0);
            $run = self::f($step['run_min'] ?? 0);
            $hours = ($setup + $run) / 60.0;
            $loaded = self::loadedHourlyRate($wc, $rates);
            $machine = self::machineHourlyRate($wc, $rates);
            $stepCost = $hours * $loaded;
            $kFertRouting += $stepCost;
            $routeSteps[] = [
                'label' => (string) ($step['label'] ?? ''),
                'work_center_id' => $wcId,
                'work_center_name' => (string) ($wc['name'] ?? ''),
                'setup_min' => $setup,
                'run_min' => $run,
                'hours' => $hours,
                'machine_per_h' => $machine,
                'loaded_per_h' => $loaded,
                'step_cost' => $stepCost,
            ];
        }

        $wage = self::f($rates['wage_eur_per_h'] ?? 0);
        $kFertLaborOnly = 0.0;
        if ($routeSteps === [] && $laborMinutes > 0) {
            $kFertLaborOnly = ($laborMinutes / 60.0) * $wage;
        } elseif ($laborMinutes > 0 && $routeSteps !== []) {
            // Zusatzzeit ohne Maschine (R1-Feld), wenn Routing existiert
            $kFertLaborOnly = ($laborMinutes / 60.0) * $wage;
        }

        $kFert = $kFertRouting + $kFertLaborOnly;
        $selfCost = $kMat + $kFert;
        $vk = $selfCost * (1.0 + ($marginPct / 100.0));
        $unitSelf = $selfCost / $targetQty;
        $unitVk = $vk / $targetQty;

        return [
            'rates' => $rates,
            'target_qty' => $targetQty,
            'margin_pct' => $marginPct,
            'labor_minutes' => $laborMinutes,
            'material_lines' => $matLines,
            'routing_steps' => $routeSteps,
            'k_mat' => $kMat,
            'k_fert_routing' => $kFertRouting,
            'k_fert_labor_only' => $kFertLaborOnly,
            'k_fert' => $kFert,
            'self_cost' => $selfCost,
            'vk' => $vk,
            'unit_self_cost' => $unitSelf,
            'unit_vk' => $unitVk,
            'warnings' => $warnings,
            'formula' => 'K_mat + K_fert; VK = Selbstkosten × (1 + Marge%)',
        ];
    }

    /**
     * @param array<string, mixed> $calcResult from calculate()
     * @return array{inputs: array<string, mixed>, result: array<string, mixed>}
     */
    public static function snapshotPayload(array $recipe, array $bomLines, array $routingLines, array $calcResult): array
    {
        return [
            'inputs' => [
                'recipe' => [
                    'title' => (string) ($recipe['title'] ?? ''),
                    'target_qty' => self::f($recipe['target_qty'] ?? 1),
                    'labor_minutes' => self::f($recipe['labor_minutes'] ?? 0),
                    'margin_pct' => self::f($recipe['margin_pct'] ?? 0),
                    'version' => (int) ($recipe['version'] ?? 1),
                    'status' => (string) ($recipe['status'] ?? ''),
                ],
                'bom' => $bomLines,
                'routing' => $routingLines,
                'rates' => $calcResult['rates'] ?? RecipeCostSettings::get(),
            ],
            'result' => [
                'k_mat' => $calcResult['k_mat'] ?? 0,
                'k_fert' => $calcResult['k_fert'] ?? 0,
                'k_fert_routing' => $calcResult['k_fert_routing'] ?? 0,
                'k_fert_labor_only' => $calcResult['k_fert_labor_only'] ?? 0,
                'self_cost' => $calcResult['self_cost'] ?? 0,
                'vk' => $calcResult['vk'] ?? 0,
                'unit_self_cost' => $calcResult['unit_self_cost'] ?? 0,
                'unit_vk' => $calcResult['unit_vk'] ?? 0,
                'material_lines' => $calcResult['material_lines'] ?? [],
                'routing_steps' => $calcResult['routing_steps'] ?? [],
                'warnings' => $calcResult['warnings'] ?? [],
            ],
        ];
    }

    /**
     * Was-wäre-wenn (R5): Anzeige-Overrides, Stammdaten unverändert bis „Übernehmen“.
     *
     * @param array<string, mixed> $raw
     * @return array{electricity_eur_per_kwh: ?float, charge_factor: float, setup_factor: float}
     */
    public static function normalizeWhatIf(array $raw): array
    {
        $elec = null;
        if (isset($raw['electricity_eur_per_kwh']) && (string) $raw['electricity_eur_per_kwh'] !== '') {
            $elec = max(0.0, self::f($raw['electricity_eur_per_kwh']));
        }
        $charge = self::f($raw['charge_factor'] ?? 1);
        if ($charge <= 0) {
            $charge = 1.0;
        }
        $charge = max(0.25, min(4.0, $charge));
        $setup = self::f($raw['setup_factor'] ?? 1);
        if ($setup <= 0) {
            $setup = 1.0;
        }
        $setup = max(0.25, min(4.0, $setup));

        return [
            'electricity_eur_per_kwh' => $elec,
            'charge_factor' => $charge,
            'setup_factor' => $setup,
        ];
    }

    /**
     * @param array<string, mixed> $recipe
     * @param list<array<string, mixed>> $bomLines
     * @param list<array<string, mixed>> $routingLines
     * @param array{electricity_eur_per_kwh?: float, room_eur_per_m2_h?: float, wage_eur_per_h?: float} $rates
     * @param array{electricity_eur_per_kwh: ?float, charge_factor: float, setup_factor: float} $whatIf
     * @return array{
     *   recipe: array<string, mixed>,
     *   bom: list<array<string, mixed>>,
     *   routing: list<array<string, mixed>>,
     *   rates: array{electricity_eur_per_kwh: float, room_eur_per_m2_h: float, wage_eur_per_h: float}
     * }
     */
    public static function applyWhatIf(array $recipe, array $bomLines, array $routingLines, array $rates, array $whatIf): array
    {
        $whatIf = self::normalizeWhatIf($whatIf);
        $ratesOut = [
            'electricity_eur_per_kwh' => self::f($rates['electricity_eur_per_kwh'] ?? 0),
            'room_eur_per_m2_h' => self::f($rates['room_eur_per_m2_h'] ?? 0),
            'wage_eur_per_h' => self::f($rates['wage_eur_per_h'] ?? 0),
        ];
        if ($whatIf['electricity_eur_per_kwh'] !== null) {
            $ratesOut['electricity_eur_per_kwh'] = $whatIf['electricity_eur_per_kwh'];
        }

        $charge = $whatIf['charge_factor'];
        $setupF = $whatIf['setup_factor'];
        $recipeOut = $recipe;
        $recipeOut['target_qty'] = self::f($recipe['target_qty'] ?? 1) * $charge;

        $bomOut = [];
        foreach ($bomLines as $line) {
            if (!is_array($line)) {
                continue;
            }
            $copy = $line;
            $copy['qty'] = self::f($line['qty'] ?? 0) * $charge;
            $bomOut[] = $copy;
        }

        $routingOut = [];
        foreach ($routingLines as $step) {
            if (!is_array($step)) {
                continue;
            }
            $copy = $step;
            $copy['setup_min'] = self::f($step['setup_min'] ?? 0) * $setupF;
            $copy['run_min'] = self::f($step['run_min'] ?? 0) * $charge;
            $routingOut[] = $copy;
        }

        return [
            'recipe' => $recipeOut,
            'bom' => $bomOut,
            'routing' => $routingOut,
            'rates' => $ratesOut,
        ];
    }

    /**
     * @param array<string, mixed> $recipe
     * @param list<array<string, mixed>> $bomLines
     * @param list<array<string, mixed>> $routingLines
     * @param array<string, mixed> $whatIfRaw
     * @return array<string, mixed>
     */
    public static function calculateWhatIf(array $recipe, array $bomLines, array $routingLines, array $whatIfRaw = []): array
    {
        $baseRates = RecipeCostSettings::get();
        $whatIf = self::normalizeWhatIf($whatIfRaw);
        $applied = self::applyWhatIf($recipe, $bomLines, $routingLines, $baseRates, $whatIf);
        $calc = self::calculate($applied['recipe'], $applied['bom'], $applied['routing'], $applied['rates']);
        $baseline = self::calculate($recipe, $bomLines, $routingLines, $baseRates);
        $calc['what_if'] = $whatIf;
        $calc['baseline'] = [
            'k_mat' => $baseline['k_mat'] ?? 0,
            'k_fert' => $baseline['k_fert'] ?? 0,
            'self_cost' => $baseline['self_cost'] ?? 0,
            'vk' => $baseline['vk'] ?? 0,
            'unit_self_cost' => $baseline['unit_self_cost'] ?? 0,
            'unit_vk' => $baseline['unit_vk'] ?? 0,
        ];
        $calc['delta_self_cost'] = self::f($calc['self_cost'] ?? 0) - self::f($baseline['self_cost'] ?? 0);
        $calc['delta_vk'] = self::f($calc['vk'] ?? 0) - self::f($baseline['vk'] ?? 0);
        $calc['applied_target_qty'] = self::f($applied['recipe']['target_qty'] ?? 0);
        $calc['base_electricity'] = self::f($baseRates['electricity_eur_per_kwh'] ?? 0);

        return $calc;
    }

    public static function formatEur(float $amount, int $decimals = 4): string
    {
        return number_format($amount, $decimals, ',', '.') . ' €';
    }

    /**
     * @param array<string, mixed> $line
     */
    private static function resolveUnitCost(array $line): array
    {
        $override = self::f($line['unit_cost'] ?? 0);
        if ($override > 0) {
            return ['cost' => $override, 'source' => 'override'];
        }
        $articleId = (int) ($line['article_id'] ?? 0);
        if ($articleId > 0) {
            $pref = ArticlePurchaseSourceRepository::preferredForArticle($articleId);
            if ($pref !== null) {
                $price = self::f($pref['purchase_price'] ?? 0);
                if ($price > 0) {
                    return ['cost' => $price, 'source' => 'preferred'];
                }
            }
        }

        return ['cost' => 0.0, 'source' => 'none'];
    }

    private static function f(mixed $v): float
    {
        if (is_int($v) || is_float($v)) {
            return (float) $v;
        }
        $s = trim(str_replace([' ', ','], ['', '.'], (string) $v));

        return is_numeric($s) ? (float) $s : 0.0;
    }
}
