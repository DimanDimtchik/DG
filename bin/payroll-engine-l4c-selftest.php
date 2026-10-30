<?php
declare(strict_types=1);

/**
 * Lohn L4c Selftest — StandardPayrollEngine gegen Fixture-Vektoren V1–V6 (ohne DB).
 */
require_once dirname(__DIR__) . '/src/autoload.php';

function assertL4c(bool $ok, string $msg): void
{
    if (!$ok) {
        fwrite(STDERR, "FEHLER: {$msg}\n");
        exit(1);
    }
    echo "OK: {$msg}\n";
}

function approxEq(float $a, float $b, float $eps = 0.001): bool
{
    return abs($a - $b) < $eps;
}

$params = PayrollRateTableRepository::fixtureParams();
$engine = new StandardPayrollEngine();
$employer = ['umlage_u1' => true, 'umlage_u2' => true, 'umlage_u3' => true];

$baseEmployee = [
    'tax_class' => '1',
    'tax_id' => '12345678901',
    'religion_code' => 'none',
    'contribution_group_key' => '1111',
    'person_group_key' => '101',
    'child_allowances' => '0',
];

$salaryLine = static fn (float $amount): array => [
    'code' => 'SALARY_MONTH',
    'amount' => $amount,
    'direction' => 'earning',
    'taxable' => 1,
    'subject_to_sv' => 1,
    'unit' => 'month',
];

// --- V1 ---
$r1 = $engine->calculate(new PayrollCalcRequest(
    1,
    '2026-03-01',
    $employer,
    $baseEmployee + ['payroll_employment_type' => 'fulltime'],
    [$salaryLine(3000)],
    $params,
    null
));
assertL4c($r1->ok && $r1->path === 'standard', 'V1 ok/path');
assertL4c(approxEq($r1->amount('gross_total'), 3000.0), 'V1 gross_total');
assertL4c(approxEq($r1->amount('lst'), 420.0), 'V1 lst');
assertL4c(approxEq($r1->amount('solz'), 23.10), 'V1 solz');
assertL4c(approxEq($r1->amount('kst'), 0.0), 'V1 kst');
assertL4c(approxEq($r1->amount('kv_an'), 270.0), 'V1 kv_an');
assertL4c(approxEq($r1->amount('rv_an'), 279.0), 'V1 rv_an');
assertL4c(approxEq($r1->amount('av_an'), 39.0), 'V1 av_an');
assertL4c(approxEq($r1->amount('pv_an'), 51.0), 'V1 pv_an');
assertL4c(approxEq($r1->amount('u1'), 51.0) && approxEq($r1->amount('u2'), 12.0) && approxEq($r1->amount('u3'), 1.80), 'V1 umlagen');
assertL4c(approxEq($r1->amount('net_pay'), 1917.90), 'V1 net_pay');
assertL4c(approxEq($r1->amount('employer_cost'), 3652.80), 'V1 employer_cost');

// --- V2 Stundenlohn ---
$r2 = $engine->calculate(new PayrollCalcRequest(
    2,
    '2026-03-01',
    $employer,
    $baseEmployee + ['payroll_employment_type' => 'parttime'],
    [[
        'code' => 'WAGE_HOUR',
        'amount' => 15,
        'hours' => 80,
        'direction' => 'earning',
        'taxable' => 1,
        'subject_to_sv' => 1,
        'unit' => 'hour',
    ]],
    $params
));
assertL4c($r2->ok && approxEq($r2->amount('gross_total'), 1200.0), 'V2 gross');
assertL4c(approxEq($r2->amount('lst'), 168.0) && approxEq($r2->amount('solz'), 9.24), 'V2 lst/solz');
assertL4c(approxEq($r2->amount('net_pay'), 767.16), 'V2 net_pay');
assertL4c(approxEq($r2->amount('employer_cost'), 1461.12), 'V2 employer_cost');

// --- V3 Minijob ---
$r3 = $engine->calculate(new PayrollCalcRequest(
    3,
    '2026-03-01',
    $employer,
    ['payroll_employment_type' => 'minijob', 'tax_class' => '1'],
    [$salaryLine(520)],
    $params,
    null,
    true
));
assertL4c($r3->ok && $r3->path === 'mini', 'V3 ok');
assertL4c(approxEq($r3->amount('lst'), 0.0) && approxEq($r3->amount('kv_an'), 0.0), 'V3 keine individuelle LSt/SV-AN');
assertL4c(approxEq($r3->amount('flat_lst'), 10.40) && approxEq($r3->amount('flat_other'), 67.60), 'V3 Pauschalen');
assertL4c(approxEq($r3->amount('net_pay'), 520.0), 'V3 net = Brutto');
assertL4c(approxEq($r3->amount('employer_cost'), 598.0), 'V3 employer_cost');

// --- V4 Midijob ---
$r4 = $engine->calculate(new PayrollCalcRequest(
    4,
    '2026-03-01',
    $employer,
    $baseEmployee + [
        'payroll_employment_type' => 'midijob',
        'annual_earnings_estimate_eur' => '14000',
    ],
    [$salaryLine(1200)],
    $params
));
assertL4c($r4->ok && $r4->path === 'midi', 'V4 ok');
assertL4c(approxEq($r4->amount('lst'), 168.0), 'V4 lst');
assertL4c(approxEq($r4->amount('kv_an'), 75.60), 'V4 kv_an midi');
assertL4c(approxEq($r4->amount('rv_an'), 78.12), 'V4 rv_an midi');
assertL4c($r4->amount('kv_an') < $r2->amount('kv_an'), 'V4 AN-SV niedriger als Standard V2');
assertL4c(approxEq($r4->amount('net_pay'), 843.84), 'V4 net_pay');

// --- V5 GF sv-frei ---
$r5 = $engine->calculate(new PayrollCalcRequest(
    5,
    '2026-03-01',
    $employer,
    [
        'payroll_employment_type' => 'managing_director',
        'tax_class' => '1',
        'religion_code' => 'none',
        'controlling_managing_director' => '1',
        'sv_exempt' => '1',
        'controlling_md_rationale' => 'Prüfung 2026-01-15: beherrschend',
    ],
    [$salaryLine(5000)],
    $params
));
assertL4c($r5->ok && $r5->path === 'gf', 'V5 ok');
assertL4c(approxEq($r5->amount('lst'), 700.0) && approxEq($r5->amount('solz'), 38.50), 'V5 lst/solz');
assertL4c(approxEq($r5->amount('kv_an'), 0.0) && approxEq($r5->amount('kv_ag'), 0.0), 'V5 SV 0');
assertL4c(approxEq($r5->amount('u1'), 0.0), 'V5 keine Umlagen');
assertL4c(approxEq($r5->amount('net_pay'), 4261.50), 'V5 net');
assertL4c($r5->warnings !== [], 'V5 Warning SV-frei');

// --- V6 Nettolohnabzug ---
$r6 = $engine->calculate(new PayrollCalcRequest(
    6,
    '2026-03-01',
    $employer,
    $baseEmployee + ['payroll_employment_type' => 'fulltime'],
    [
        $salaryLine(3000),
        [
            'code' => 'NET_DEDUCTION',
            'amount' => 100,
            'direction' => 'deduction',
            'taxable' => 0,
            'subject_to_sv' => 0,
            'unit' => 'amount',
        ],
    ],
    $params
));
assertL4c($r6->ok && approxEq($r6->amount('net_deductions'), 100.0), 'V6 deductions');
assertL4c(approxEq($r6->amount('net_pay'), 1817.90), 'V6 net = V1−100');
assertL4c(approxEq($r6->amount('net_pay'), $r1->amount('net_pay') - 100.0), 'V6 vs V1');

assertL4c(StandardPayrollEngine::round2(1.235) === 1.24, 'round2 HALF_UP');
assertL4c((new StandardPayrollEngine()) instanceof PayrollEngineInterface, 'Interface');

echo "L4c Selftest bestanden (V1–V6).\n";
exit(0);
