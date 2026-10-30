<?php
declare(strict_types=1);

/**
 * Lohn L6c Selftest — Lohnkonto-Normalisierung, Summen, CSV (ohne DB).
 */
if (!defined('DG_ROOT')) {
    define('DG_ROOT', dirname(__DIR__));
}
require_once DG_ROOT . '/src/autoload.php';

function assertL6c(bool $ok, string $msg): void
{
    if (!$ok) {
        fwrite(STDERR, "FEHLER: {$msg}\n");
        exit(1);
    }
    echo "OK: {$msg}\n";
}

assertL6c(class_exists('PayrollLohnkontoService'), 'PayrollLohnkontoService');
assertL6c(PayrollLohnkontoService::kindLabel('regular') === 'Regulär', 'kind regular');
assertL6c(PayrollLohnkontoService::kindLabel('storno') === 'Storno', 'kind storno');
assertL6c(
    PayrollLohnkontoService::svAnSum([
        'kv_an' => 270.0, 'rv_an' => 279.0, 'av_an' => 39.0, 'pv_an' => 51.0,
    ]) === 639.0,
    'SV-AN Summe'
);

$row = PayrollLohnkontoService::normalizeRow([
    'run_id' => 7,
    'contact_id' => 42,
    'period_year' => 2026,
    'period_month' => 3,
    'kind' => 'regular',
    'run_status' => 'locked',
    'emp_status' => 'ok',
    'is_superseded' => 0,
    'label' => 'März',
    'path' => 'standard',
    'gross_total' => 3000,
    'net_pay' => 1917.90,
    'result_json' => json_encode([
        'gross_total' => 3000,
        'lst' => 420,
        'kv_an' => 270, 'rv_an' => 279, 'av_an' => 39, 'pv_an' => 51,
        'net_pay' => 1917.90,
    ], JSON_UNESCAPED_UNICODE),
]);
assertL6c((int) $row['run_id'] === 7, 'normalize run_id');
assertL6c((float) $row['lst'] === 420.0, 'normalize lst');
assertL6c((float) $row['sv_an'] === 639.0, 'normalize sv_an');
assertL6c($row['kind_label'] === 'Regulär', 'normalize kind_label');
assertL6c(empty($row['is_draft_run']), 'locked ist kein Entwurf');

$draft = PayrollLohnkontoService::normalizeRow([
    'run_id' => 8,
    'contact_id' => 42,
    'period_year' => 2026,
    'period_month' => 4,
    'kind' => 'correction',
    'run_status' => 'calculated',
    'emp_status' => 'ok',
    'is_superseded' => 0,
    'gross_total' => 100,
    'net_pay' => 80,
    'result_json' => '{"lst":10,"kv_an":1,"rv_an":1,"av_an":1,"pv_an":1}',
]);
assertL6c(!empty($draft['is_draft_run']), 'calculated = Entwurf');
assertL6c($draft['kind_label'] === 'Korrektur', 'correction label');

$superseded = $row;
$superseded['is_superseded'] = true;
$totals = PayrollLohnkontoService::yearTotals([$row, $draft, $superseded]);
assertL6c($totals['count'] === 2, 'Summe zählt ohne ersetzt');
assertL6c(abs($totals['gross_total'] - 3100.0) < 0.001, 'Summe Brutto');
assertL6c(abs($totals['lst'] - 430.0) < 0.001, 'Summe LSt');
assertL6c(abs($totals['sv_an'] - 643.0) < 0.001, 'Summe SV-AN');

$csv = PayrollLohnkontoService::toCsv(42, 'Max Mustermann', 2026, [$row, $superseded], $totals);
assertL6c(str_starts_with($csv, "\xEF\xBB\xBF"), 'CSV BOM');
assertL6c(str_contains($csv, 'Kontakt-ID;Name;Jahr'), 'CSV Kopf');
assertL6c(str_contains($csv, 'Max Mustermann'), 'CSV Name');
assertL6c(str_contains($csv, '3000,00'), 'CSV Brutto DE (Komma-Dezimal, ohne Tausender)');
assertL6c(str_contains($csv, 'SUMME*'), 'CSV Summenzeile');
assertL6c(
    PayrollLohnkontoService::csvFilename(42, 2026) === 'lohnkonto-42-2026.csv',
    'CSV Dateiname'
);

echo "L6c Selftest bestanden.\n";
exit(0);
