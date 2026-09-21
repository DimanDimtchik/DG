<?php
declare(strict_types=1);

/**
 * Was-wäre-wenn-Kalkulation für Rezeptur (R5).
 */
final class RecipeCostApi
{
    public static function handle(): void
    {
        header('Content-Type: application/json; charset=utf-8');

        $user = AuthService::user();
        if ($user === null || !MenuRegistry::canAccess($user, 'rezeptur')) {
            self::fail('Keine Berechtigung.', 403);
        }
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            self::fail('Nur POST erlaubt.', 405);
        }
        if (!Csrf::verify($_POST['_csrf'] ?? null)) {
            self::fail('Ungültiges Formular (CSRF).', 403);
        }
        if (!Database::isConfigured()) {
            self::fail('Datenbank nicht verbunden.');
        }

        $action = (string) ($_POST['action'] ?? 'whatif');
        try {
            if ($action === 'whatif') {
                self::whatIf();
            }
            if ($action === 'apply_electricity') {
                self::applyElectricity();
            }
            self::fail('Unbekannte Aktion.', 400);
        } catch (Throwable $e) {
            self::fail($e->getMessage());
        }
    }

    private static function whatIf(): void
    {
        $payload = self::payloadFromRequest();
        $whatIf = [
            'electricity_eur_per_kwh' => $_POST['electricity_eur_per_kwh'] ?? null,
            'charge_factor' => $_POST['charge_factor'] ?? 1,
            'setup_factor' => $_POST['setup_factor'] ?? 1,
        ];
        $calc = RecipeCostService::calculateWhatIf(
            $payload['recipe'],
            $payload['bom'],
            $payload['routing'],
            $whatIf
        );
        echo json_encode([
            'success' => true,
            'data' => [
                'k_mat' => $calc['k_mat'] ?? 0,
                'k_fert' => $calc['k_fert'] ?? 0,
                'self_cost' => $calc['self_cost'] ?? 0,
                'vk' => $calc['vk'] ?? 0,
                'unit_self_cost' => $calc['unit_self_cost'] ?? 0,
                'unit_vk' => $calc['unit_vk'] ?? 0,
                'warnings' => $calc['warnings'] ?? [],
                'what_if' => $calc['what_if'] ?? [],
                'baseline' => $calc['baseline'] ?? [],
                'delta_self_cost' => $calc['delta_self_cost'] ?? 0,
                'delta_vk' => $calc['delta_vk'] ?? 0,
                'applied_target_qty' => $calc['applied_target_qty'] ?? 0,
                'base_electricity' => $calc['base_electricity'] ?? 0,
                'formatted' => [
                    'k_mat' => RecipeCostService::formatEur((float) ($calc['k_mat'] ?? 0), 2),
                    'k_fert' => RecipeCostService::formatEur((float) ($calc['k_fert'] ?? 0), 2),
                    'self_cost' => RecipeCostService::formatEur((float) ($calc['self_cost'] ?? 0), 2),
                    'vk' => RecipeCostService::formatEur((float) ($calc['vk'] ?? 0), 2),
                    'unit_self_cost' => RecipeCostService::formatEur((float) ($calc['unit_self_cost'] ?? 0), 4),
                    'unit_vk' => RecipeCostService::formatEur((float) ($calc['unit_vk'] ?? 0), 4),
                    'delta_self_cost' => RecipeCostService::formatEur((float) ($calc['delta_self_cost'] ?? 0), 2),
                    'delta_vk' => RecipeCostService::formatEur((float) ($calc['delta_vk'] ?? 0), 2),
                ],
            ],
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    private static function applyElectricity(): void
    {
        if (!RoleResolver::canEdit(AuthService::user())) {
            self::fail('Keine Berechtigung.', 403);
        }
        $rates = RecipeCostSettings::get();
        $elec = trim(str_replace([' ', ','], ['', '.'], (string) ($_POST['electricity_eur_per_kwh'] ?? '')));
        if ($elec === '' || !is_numeric($elec) || (float) $elec < 0) {
            self::fail('Ungültiger Strompreis.');
        }
        RecipeCostSettings::saveFromPost([
            'electricity_eur_per_kwh' => $elec,
            'room_eur_per_m2_h' => (string) $rates['room_eur_per_m2_h'],
            'wage_eur_per_h' => (string) $rates['wage_eur_per_h'],
        ]);
        echo json_encode([
            'success' => true,
            'message' => 'Strompreis übernommen (Kostensatz gespeichert).',
            'data' => ['electricity_eur_per_kwh' => (float) $elec],
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    /**
     * @return array{recipe: array<string, mixed>, bom: list<array<string, mixed>>, routing: list<array<string, mixed>>}
     */
    private static function payloadFromRequest(): array
    {
        $raw = (string) ($_POST['payload'] ?? '');
        if ($raw !== '') {
            $decoded = json_decode($raw, true);
            if (!is_array($decoded)) {
                throw new InvalidArgumentException('Ungültige Payload.');
            }
            $recipe = is_array($decoded['recipe'] ?? null) ? $decoded['recipe'] : [];
            $bom = is_array($decoded['bom'] ?? null) ? $decoded['bom'] : [];
            $routing = is_array($decoded['routing'] ?? null) ? $decoded['routing'] : [];

            return ['recipe' => $recipe, 'bom' => $bom, 'routing' => $routing];
        }

        $recipeId = (int) ($_POST['recipe_id'] ?? 0);
        if ($recipeId <= 0) {
            throw new InvalidArgumentException('Rezept oder Payload erforderlich.');
        }
        $form = RecipeRepository::formForId($recipeId);
        if (RecipeRepository::find($recipeId) === null) {
            throw new InvalidArgumentException('Rezept nicht gefunden.');
        }

        return [
            'recipe' => $form,
            'bom' => $form['bom'] ?? [],
            'routing' => $form['routing'] ?? [],
        ];
    }

    private static function fail(string $message, int $code = 400): void
    {
        http_response_code($code);
        echo json_encode(['success' => false, 'message' => $message], JSON_UNESCAPED_UNICODE);
        exit;
    }
}
