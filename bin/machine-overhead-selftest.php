<?php
declare(strict_types=1);

/**
 * Guard E1 Maschinen-Gemeinkosten: SettingsStore-only, Service, Snapshot-Felder, UI/POST.
 * Kein Live-FiBu-Rewrite.
 *
 * Usage: php bin/machine-overhead-selftest.php
 */
if (!defined('DG_ROOT')) {
    define('DG_ROOT', dirname(__DIR__));
}
require_once DG_ROOT . '/src/autoload.php';

function assertMoh(bool $ok, string $msg): void
{
    if (!$ok) {
        fwrite(STDERR, "FEHLER: {$msg}\n");
        exit(1);
    }
    echo "OK: {$msg}\n";
}

$overheadMigrations = glob(DG_ROOT . '/database/migrations/*machine_overhead*') ?: [];
assertMoh($overheadMigrations === [], 'Keine machine_overhead-Migration (SettingsStore-only)');

assertMoh(is_readable(DG_ROOT . '/src/Recipe/MachineOverheadSettings.php'), 'MachineOverheadSettings vorhanden');
assertMoh(is_readable(DG_ROOT . '/src/Recipe/MachineOverheadService.php'), 'MachineOverheadService vorhanden');

$autoload = (string) file_get_contents(DG_ROOT . '/src/autoload.php');
assertMoh(str_contains($autoload, 'MachineOverheadSettings.php'), 'Autoload Settings');
assertMoh(str_contains($autoload, 'MachineOverheadService.php'), 'Autoload Service');

$settingsBody = (string) file_get_contents(DG_ROOT . '/src/Recipe/MachineOverheadSettings.php');
assertMoh(str_contains($settingsBody, "STORE_KEY = 'machine_overhead'"), 'STORE_KEY machine_overhead');
assertMoh(str_contains($settingsBody, 'function forForm'), 'forForm vorhanden');
assertMoh(str_contains($settingsBody, 'function saveFromPost'), 'saveFromPost vorhanden');

$svcBody = (string) file_get_contents(DG_ROOT . '/src/Recipe/MachineOverheadService.php');
assertMoh(str_contains($svcBody, 'function compute'), 'compute vorhanden');
assertMoh(str_contains($svcBody, 'opening_balance'), 'schließt Saldenvortrag aus');
assertMoh(!preg_match('/\b(INSERT|UPDATE|DELETE)\b/i', $svcBody), 'Service schreibt nicht ins Ledger');

$costBody = (string) file_get_contents(DG_ROOT . '/src/Recipe/RecipeCostService.php');
assertMoh(str_contains($costBody, 'k_overhead_suggested'), 'RecipeCostService k_overhead_suggested');
assertMoh(str_contains($costBody, "'overhead'"), 'snapshot/calc overhead-Key');
assertMoh(str_contains($costBody, 'self_cost_with_overhead_suggested'), 'self_cost_with_overhead_suggested');

$index = (string) file_get_contents(DG_ROOT . '/index.php');
assertMoh(str_contains($index, "isset(\$_POST['machine_overhead_save'])"), 'POST machine_overhead_save');
assertMoh(
    (bool) preg_match(
        '/machine_overhead_save[\s\S]{0,500}?MenuRegistry::canAccess\(\$user, \'rezeptur-maschinen\'\)/',
        $index
    ) || (bool) preg_match(
        '/MenuRegistry::canAccess\(\$user, \'rezeptur-maschinen\'\)[\s\S]{0,400}?machine_overhead_save/',
        $index
    ),
    'POST hinter rezeptur-maschinen-Gate'
);

$view = (string) file_get_contents(DG_ROOT . '/views/modules/rezeptur-maschinen.php');
assertMoh(str_contains($view, 'id="dg-machine-overhead"'), 'UI-Abschnitt Gemeinkosten');
assertMoh(str_contains($view, 'machine_overhead_save'), 'Speichern-Button Gemeinkosten');
assertMoh(str_contains($view, 'nicht buchungswirksam') || str_contains($view, 'keine automatische Buchung'), 'Hinweis ohne FiBu-Buchung');

$form = (string) file_get_contents(DG_ROOT . '/views/modules/rezeptur-form.php');
assertMoh(str_contains($form, 'dg-recipe-overhead-hint'), 'Kalkulations-Hinweis Gemeinkosten');
assertMoh(str_contains($form, 'k_overhead_suggested'), 'Form zeigt k_overhead_suggested');

$api = (string) file_get_contents(DG_ROOT . '/src/Recipe/RecipeCostApi.php');
assertMoh(str_contains($api, 'overhead_per_machine_hour'), 'API overhead_per_machine_hour');

// Formel / Normalize ohne DB
$cfg = MachineOverheadSettings::normalize([
    'enabled' => true,
    'period_mode' => 'fiscal_year',
    'fiscal_year' => 2026,
    'account_numbers' => '6300, 6400',
    'manual_machine_hours' => '4',
    'label' => 'Test',
]);
assertMoh($cfg['enabled'] === true, 'normalize enabled');
assertMoh($cfg['account_numbers'] === ['6300', '6400'], 'normalize Konten');
assertMoh($cfg['manual_machine_hours'] !== null && abs((float) $cfg['manual_machine_hours'] - 4.0) < 0.0001, 'normalize Manualstunden');

$total = 100.0;
$hours = 4.0;
$rate = round($total / $hours, 4);
assertMoh(abs($rate - 25.0) < 0.00001, 'Formel 100€ / 4h = 25 €/h');

$payload = RecipeCostService::snapshotPayload(
    ['id' => 1, 'title' => 'Selftest', 'target_qty' => 1],
    [],
    [],
    [
        'k_mat' => 0,
        'k_fert' => 0,
        'k_fert_routing' => 0,
        'k_fert_labor_only' => 0,
        'k_maint_suggested' => 0,
        'self_cost' => 10.0,
        'vk' => 12.0,
        'unit_self_cost' => 10.0,
        'unit_vk' => 12.0,
        'material_lines' => [],
        'auxiliary_lines' => [],
        'capacity_lines' => [],
        'maintenance_lines' => [],
        'routing_steps' => [],
        'warnings' => [],
        'rates' => [],
        'overhead' => [
            'enabled' => true,
            'overhead_per_machine_hour' => $rate,
            'total_overhead_cost' => $total,
            'machine_hours' => $hours,
            'computed_at' => 'selftest',
        ],
        'overhead_per_machine_hour' => $rate,
        'k_overhead_suggested' => 50.0,
        'self_cost_with_overhead_suggested' => 60.0,
    ]
);
assertMoh(($payload['inputs']['overhead']['overhead_per_machine_hour'] ?? null) === $rate, 'Snapshot inputs.overhead');
assertMoh(($payload['result']['overhead_per_machine_hour'] ?? null) === $rate, 'Snapshot result rate');
assertMoh((float) ($payload['result']['k_overhead_suggested'] ?? 0) === 50.0, 'Snapshot k_overhead_suggested');
assertMoh((float) ($payload['result']['self_cost'] ?? 0) === 10.0, 'self_cost unverändert im Snapshot');

$computeNoDb = MachineOverheadService::compute([
    'enabled' => false,
    'period_mode' => 'fiscal_year',
    'fiscal_year' => 2026,
    'account_numbers' => [],
    'manual_machine_hours' => null,
    'label' => '',
    'date_from' => '',
    'date_to' => '',
]);
assertMoh(array_key_exists('overhead_per_machine_hour', $computeNoDb), 'compute liefert overhead_per_machine_hour');
assertMoh($computeNoDb['overhead_per_machine_hour'] === null, 'ohne DB/Konten: rate null');

echo "Machine-Overhead Selftest bestanden.\n";
