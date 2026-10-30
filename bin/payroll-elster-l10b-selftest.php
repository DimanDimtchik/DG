<?php
declare(strict_types=1);

/**
 * Lohn L10b Selftest — LStA-Aggregation, Payload, CSV, Live-Block (ohne DB).
 */
if (!defined('DG_ROOT')) {
    define('DG_ROOT', dirname(__DIR__));
}
require_once DG_ROOT . '/src/autoload.php';

function assertL10b(bool $ok, string $msg): void
{
    if (!$ok) {
        fwrite(STDERR, "FEHLER: {$msg}\n");
        exit(1);
    }
    echo "OK: {$msg}\n";
}

assertL10b(class_exists('PayrollElsterService'), 'PayrollElsterService');
assertL10b(method_exists('PayrollRunAccess', 'canSubmitLstaTest'), 'canSubmitLstaTest');
assertL10b(PayrollElsterService::PAYLOAD_SCHEMA === 'DG-LSTA-PREP-1', 'Schema-Konstante');

$lohn = PayrollEmployerSettings::normalizeLohn(['payroll_lsta_live' => false]);
assertL10b($lohn['payroll_lsta_live'] === false, 'lsta live default aus');
$lohnOn = PayrollEmployerSettings::normalizeLohn(['payroll_lsta_live' => '1']);
assertL10b($lohnOn['payroll_lsta_live'] === true, 'lsta live normalize');

$runOk = [
    'id' => 5,
    'status' => 'locked',
    'kind' => 'regular',
    'is_superseded' => 0,
    'period_year' => 2026,
    'period_month' => 10,
];
$runStorno = [
    'id' => 6,
    'status' => 'locked',
    'kind' => 'storno',
    'is_superseded' => 0,
    'period_year' => 2026,
    'period_month' => 10,
];
$emps = [
    5 => [[
        'contact_id' => 1,
        'status' => 'ok',
        'result_json' => json_encode([
            'lst' => 420, 'solz' => 23.10, 'kst' => 0,
            'flat_lst' => 0, 'flat_other' => 0,
            'gross_taxable' => 3000,
        ], JSON_UNESCAPED_UNICODE),
    ], [
        'contact_id' => 2,
        'status' => 'ok',
        'result_json' => json_encode([
            'lst' => 0, 'solz' => 0, 'kst' => 0,
            'flat_lst' => 10.40, 'flat_other' => 0,
            'gross_taxable' => 520,
        ], JSON_UNESCAPED_UNICODE),
    ]],
    6 => [[
        'contact_id' => 1,
        'status' => 'ok',
        'result_json' => json_encode(['lst' => 100, 'solz' => 5, 'kst' => 0], JSON_UNESCAPED_UNICODE),
    ]],
];

$snap = [
    'employer_name' => 'Muster GmbH',
    'tax_number_est' => '123/456/78901',
    'finanzamt' => ['name' => 'FA Test', 'bufo' => '1234'],
];

$agg = PayrollElsterService::aggregateLsta(2026, 10, [$runOk, $runStorno], $emps, $snap);
assertL10b($agg['source_run_ids'] === [5], 'nur regular Run');
assertL10b(abs((float) $agg['amounts']['lst'] - 420.0) < 0.001, 'LSt ohne Storno');
assertL10b(abs((float) $agg['amounts']['flat_lst'] - 10.40) < 0.001, 'Flat getrennt');
assertL10b((int) $agg['amounts']['employee_count'] === 2, '2 MA');
assertL10b(!empty($agg['can_send_structurally']), 'strukturell ok');
assertL10b(
    (bool) array_filter($agg['warnings'], static fn ($w) => str_contains((string) $w, 'Storno')),
    'Storno-Warnung'
);

$badSnap = $snap;
$badSnap['tax_number_est'] = '';
$bad = PayrollElsterService::aggregateLsta(2026, 10, [$runOk], [5 => $emps[5]], $badSnap);
assertL10b(empty($bad['can_send_structurally']), 'ohne Steuernr. block');

$xml = PayrollElsterService::buildLstaPayloadXml($agg);
assertL10b(str_contains($xml, 'DgPayrollLsta'), 'Payload Root');
assertL10b(str_contains($xml, '420.00'), 'Payload LSt');
assertL10b(str_contains($xml, 'FlatLst separate="true"'), 'Flat getrennt im XML');
assertL10b(str_contains($xml, 'kein amtliches ELSTER-Schema'), 'Hinweis kein Amts-Schema');

$csv = PayrollElsterService::toCsvLsta($agg);
assertL10b(str_starts_with($csv, "\xEF\xBB\xBF"), 'CSV BOM');
assertL10b(str_contains($csv, '420,00'), 'CSV DE');

$lstb = PayrollElsterService::aggregateLstb(2026, [
    [
        'contact_id' => 1, 'label' => 'Max', 'tax_id' => '12345678901',
        'tax_class' => '1', 'religion_code' => 'none',
        'lst' => 420, 'solz' => 23.1, 'kst' => 0, 'gross_taxable' => 3000,
    ],
    [
        'contact_id' => 1, 'label' => 'Max', 'tax_id' => '12345678901',
        'lst' => 400, 'solz' => 20, 'kst' => 0, 'gross_taxable' => 3000,
    ],
    [
        'contact_id' => 2, 'label' => 'Ohne Id', 'tax_id' => '12',
        'lst' => 50, 'solz' => 0, 'kst' => 0, 'gross_taxable' => 500,
    ],
]);
assertL10b(count($lstb['rows']) === 2, '2 MA Jahreszeilen');
assertL10b(abs((float) $lstb['rows'][0]['lst'] - 820.0) < 0.001, 'Jahres-LSt summiert');
assertL10b((int) $lstb['eligible_count'] === 1, 'nur 1 eligibel');

$csvB = PayrollElsterService::toCsvLstb($lstb);
assertL10b(str_contains($csvB, 'Eligibel'), 'LStB CSV');

$liveBlocked = false;
try {
    PayrollElsterService::submitLiveLsta(2026, 10, 1, 'should fail always here');
} catch (RuntimeException $e) {
    $liveBlocked = str_contains($e->getMessage(), 'gesperrt') || str_contains($e->getMessage(), 'Freigabe');
}
assertL10b($liveBlocked, 'Live blockiert');

$list = PayrollElsterService::auditorChecklist();
assertL10b(count($list) >= 5, 'Checkliste');
$joined = implode(' ', $list);
assertL10b(str_contains($joined, 'UStVA'), 'Checkliste UStVA-Trennung');
assertL10b(str_contains($joined, 'Freigabe') || str_contains($joined, 'Test'), 'Checkliste Freigabe');

$gate = PayrollElsterService::productionGateChecklist();
assertL10b(count($gate) >= 10, 'Produktiv-Checkliste');
$gateJoined = implode(' ', $gate);
assertL10b(str_contains($gateJoined, 'Lohnsteuer-Anmeldung live') || str_contains($gateJoined, 'Freigabe'), 'Gate Freigabe');
assertL10b(str_contains($gateJoined, 'Bestätigung') || str_contains($gateJoined, 'Recht'), 'Gate Rechte-Hinweis');

echo "L10b Selftest bestanden.\n";
exit(0);
