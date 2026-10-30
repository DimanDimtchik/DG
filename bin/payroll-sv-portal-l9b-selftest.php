<?php
declare(strict_types=1);

/**
 * Lohn L9b Selftest — Beitragsnachweis-Aggregation, Stammkontrolle, CSV (ohne DB).
 */
if (!defined('DG_ROOT')) {
    define('DG_ROOT', dirname(__DIR__));
}
require_once DG_ROOT . '/src/autoload.php';

function assertL9b(bool $ok, string $msg): void
{
    if (!$ok) {
        fwrite(STDERR, "FEHLER: {$msg}\n");
        exit(1);
    }
    echo "OK: {$msg}\n";
}

assertL9b(class_exists('PayrollSvPortalService'), 'PayrollSvPortalService');
assertL9b(method_exists('PayrollSvPortalService', 'aggregateBeitragsnachweis'), 'aggregate');
assertL9b(str_contains(PayrollSvPortalService::maskSvNumber('12123456A123'), '•'), 'SV maskiert');

$run = [
    'id' => 11,
    'period_year' => 2026,
    'period_month' => 10,
    'kind' => 'regular',
    'status' => 'locked',
];

$empA = [
    'contact_id' => 1,
    'status' => 'ok',
    'path' => 'standard',
    'result_json' => json_encode([
        'kv_an' => 270, 'kv_ag' => 270,
        'rv_an' => 279, 'rv_ag' => 279,
        'av_an' => 39, 'av_ag' => 39,
        'pv_an' => 51, 'pv_ag' => 51,
        'u1' => 10, 'u2' => 5, 'u3' => 1,
        'flat_lst' => 0, 'flat_other' => 0,
        'gross_sv' => 3000,
    ], JSON_UNESCAPED_UNICODE),
    'snapshot_json' => json_encode([
        'employee' => [
            'payroll_employment_type' => 'fulltime',
            'health_insurance' => 'TK',
            'health_insurance_company_number' => '10157551',
            'contribution_group_key' => '1111',
            'person_group_key' => '101',
            'social_security_number' => '12123456A123',
            'social_security_status' => 'received',
            'entry_date' => '2024-01-15',
            'exit_date' => '',
            'tax_id' => '12345678901',
            'social_registration_status' => 'none',
            'display_name' => 'Max Mustermann',
        ],
    ], JSON_UNESCAPED_UNICODE),
];

$empB = [
    'contact_id' => 2,
    'status' => 'ok',
    'path' => 'mini',
    'result_json' => json_encode([
        'kv_an' => 0, 'kv_ag' => 0,
        'rv_an' => 0, 'rv_ag' => 0,
        'av_an' => 0, 'av_ag' => 0,
        'pv_an' => 0, 'pv_ag' => 0,
        'u1' => 0, 'u2' => 0, 'u3' => 0,
        'flat_lst' => 20, 'flat_other' => 13,
        'gross_sv' => 520,
    ], JSON_UNESCAPED_UNICODE),
    'snapshot_json' => json_encode([
        'employee' => [
            'payroll_employment_type' => 'minijob',
            'health_insurance' => '',
            'health_insurance_company_number' => '',
            'contribution_group_key' => '',
            'person_group_key' => '109',
            'social_security_number' => '',
            'social_security_status' => 'pending',
            'entry_date' => '2026-10-01',
            'tax_id' => '10987654321',
            'social_registration_status' => 'none',
            'display_name' => 'Mini Job',
        ],
    ], JSON_UNESCAPED_UNICODE),
];

$agg = PayrollSvPortalService::aggregateBeitragsnachweis($run, [$empA, $empB], '12345678');
assertL9b(count($agg['groups']) === 2, '2 KK-Gruppen (BN + UNKNOWN)');
assertL9b(!$agg['blocked'], 'regular nicht blocked');
$byBn = [];
foreach ($agg['groups'] as $g) {
    $byBn[$g['kk_bn']] = $g;
}
assertL9b(isset($byBn['10157551'], $byBn['UNKNOWN']), 'Keys BN+UNKNOWN');
assertL9b(abs((float) $byBn['10157551']['kv'] - 540.0) < 0.001, 'KV Summe TK');
assertL9b(abs((float) $byBn['10157551']['sv_an'] - 639.0) < 0.001, 'SV-AN TK');
assertL9b(abs((float) $byBn['UNKNOWN']['flat'] - 33.0) < 0.001, 'Flat Mini getrennt');
assertL9b((int) $agg['totals']['employee_count'] === 2, 'TOTAL MA');
assertL9b(
    (bool) array_filter($agg['warnings'], static fn ($w) => str_contains((string) $w, 'UNKNOWN')),
    'Warnung ohne KK-BN'
);

$storno = $run;
$storno['kind'] = 'storno';
$stAgg = PayrollSvPortalService::aggregateBeitragsnachweis($storno, [$empA], '12345678');
assertL9b(!empty($stAgg['blocked']), 'Storno blocked');
assertL9b($stAgg['groups'] === [], 'Storno keine Gruppen');

$stamm = PayrollSvPortalService::buildStammkontrolle($run, [$empA, $empB], '12345678');
assertL9b(count($stamm['rows']) === 2, '2 Stammzeilen');
$max = $stamm['rows'][0]['contact_id'] === 1 ? $stamm['rows'][0] : $stamm['rows'][1];
$mini = $stamm['rows'][0]['contact_id'] === 2 ? $stamm['rows'][0] : $stamm['rows'][1];
assertL9b(($max['ampel'] ?? '') === 'ok', 'Max ok');
assertL9b(($mini['meldehinweis'] ?? '') === 'Anmeldung DEÜV im Portal', 'Eintritt in Periode');
assertL9b(str_contains((string) ($max['social_security_number_masked'] ?? ''), '•'), 'Maske in Stamm');

$gapEmp = $empA;
$gapSnap = json_decode((string) $gapEmp['snapshot_json'], true);
$gapSnap['employee']['person_group_key'] = '';
$gapSnap['employee']['contribution_group_key'] = '';
$gapEmp['snapshot_json'] = json_encode($gapSnap, JSON_UNESCAPED_UNICODE);
$gapStamm = PayrollSvPortalService::buildStammkontrolle($run, [$gapEmp], '');
assertL9b($gapStamm['rows'][0]['ampel'] === 'fail', 'Lücken → fail');
assertL9b($gapStamm['gaps'] !== [], 'gaps Liste');
assertL9b(
    (bool) array_filter($gapStamm['gaps'], static fn ($g) => str_contains((string) $g, 'Betriebsnummer')),
    'AG-BN Lücke'
);

$csvB = PayrollSvPortalService::toCsvBeitragsnachweis($agg);
assertL9b(str_starts_with($csvB, "\xEF\xBB\xBF"), 'BOM');
assertL9b(str_contains($csvB, '10157551'), 'CSV BN');
assertL9b(str_contains($csvB, '540,00') || str_contains($csvB, '639,00'), 'DE-Zahlen');

$csvS = PayrollSvPortalService::toCsvStammkontrolle($stamm);
assertL9b(str_contains($csvS, 'Anmeldung DEÜV'), 'CSV Meldehinweis');

$csvC = PayrollSvPortalService::toCsvCombined($agg, $stamm);
assertL9b(str_contains($csvC, 'KK-BN') && str_contains($csvC, 'Ampel'), 'Combined beide Teile');

$list = PayrollSvPortalService::auditorChecklist();
assertL9b(count($list) >= 5, 'Checkliste');
$joined = implode(' ', $list);
assertL9b(str_contains($joined, 'Portal') || str_contains($joined, 'Meldeversand'), 'Checkliste kein Versand');
assertL9b(str_contains($joined, 'Storno'), 'Checkliste Storno');

$labels = PayrollSvPortalService::kindLabels();
assertL9b(isset($labels[PayrollSvPortalService::KIND_COMBINED]), 'kind labels');

echo "L9b Selftest bestanden.\n";
exit(0);
