<?php
declare(strict_types=1);

/**
 * Lohn L7b Selftest — Aggregation, Buchungszeilen, Invertierung, Konten (ohne DB).
 */
if (!defined('DG_ROOT')) {
    define('DG_ROOT', dirname(__DIR__));
}
require_once DG_ROOT . '/src/autoload.php';

function assertL7b(bool $ok, string $msg): void
{
    if (!$ok) {
        fwrite(STDERR, "FEHLER: {$msg}\n");
        exit(1);
    }
    echo "OK: {$msg}\n";
}

assertL7b(class_exists('PayrollFibuService'), 'PayrollFibuService');
assertL7b(PayrollFibuService::SOURCE === 'payroll', 'source payroll');
assertL7b(PayrollFibuService::markerForRun(12) === 'payroll:run:12', 'marker');

$skr03 = PayrollFibuService::defaultAccounts('skr03');
assertL7b($skr03['expense_wages'] === '4120', 'SKR03 Aufwand');
assertL7b($skr03['liability_sv'] === '1742', 'SKR03 SV');
$skr04 = PayrollFibuService::defaultAccounts('skr04');
assertL7b($skr04['expense_wages'] === '6200' && $skr04['liability_tax'] === '3510', 'SKR04 Defaults');

$resolved = PayrollFibuService::resolveAccounts([
    'expense_wages' => '4121',
    'liability_net' => '',
], 'skr03');
assertL7b($resolved['expense_wages'] === '4121', 'Override Aufwand');
assertL7b($resolved['liability_net'] === '1740', 'Default Netto bleibt');

$empOk = [
    'contact_id' => 1,
    'status' => 'ok',
    'gross_total' => 3000,
    'net_pay' => 1917.90,
    'result_json' => json_encode([
        'gross_total' => 3000.0,
        'lst' => 420.0,
        'solz' => 23.10,
        'kst' => 0.0,
        'kv_an' => 270.0, 'rv_an' => 279.0, 'av_an' => 39.0, 'pv_an' => 51.0,
        'kv_ag' => 270.0, 'rv_ag' => 279.0, 'av_ag' => 39.0, 'pv_ag' => 51.0,
        'u1' => 51.0, 'u2' => 12.0, 'u3' => 1.80,
        'flat_lst' => 0.0, 'flat_other' => 0.0,
        'flat_lst_employee_share' => 0.0,
        'net_deductions' => 0.0,
        'net_pay' => 1917.90,
        'employer_cost' => 3703.80,
    ], JSON_UNESCAPED_UNICODE),
];
$agg = PayrollFibuService::aggregateAmounts([$empOk, ['status' => 'skipped', 'result_json' => '{}']]);
assertL7b((int) $agg['employee_count'] === 1, 'nur ok-MA');
assertL7b(abs($agg['gross_total'] - 3000.0) < 0.001, 'Brutto');
assertL7b(abs($agg['fa'] - 443.10) < 0.001, 'FA = LSt+SolZ+KiSt');
assertL7b(abs($agg['sv_an'] - 639.0) < 0.001, 'SV-AN');
assertL7b($agg['warnings'] === [], 'keine Soft-Warnung bei konsistentem employer_cost');

$lines = PayrollFibuService::buildLines(
    PayrollFibuService::defaultAccounts('skr03'),
    $agg,
    'Lohn 03/2026 Run #7 (Regulär)',
    'payroll:run:7'
);
$debit = 0.0;
$credit = 0.0;
foreach ($lines as $ln) {
    if ($ln['side'] === 'debit') {
        $debit += $ln['amount'];
    } else {
        $credit += $ln['amount'];
    }
    assertL7b(($ln['tax_key'] ?? '') === '', 'keine USt tax_key');
    assertL7b($ln['document_field1'] === 'payroll:run:7', 'Marker auf Zeile');
}
$expectedDebit = round($agg['gross_total'] + $agg['sv_ag'] + $agg['umlagen'] + $agg['flat'], 2);
assertL7b(abs($debit - $credit) < 0.009, 'Soll=Haben');
assertL7b(abs($debit - $expectedDebit) < 0.009, 'Soll = B+SV_AG+U+Flat');

$inv = PayrollFibuService::invertLines($lines, 'Storno', 'payroll:run:8');
$d2 = 0.0;
$c2 = 0.0;
foreach ($inv as $ln) {
    if ($ln['side'] === 'debit') {
        $d2 += $ln['amount'];
    } else {
        $c2 += $ln['amount'];
    }
}
assertL7b(abs($d2 - $c2) < 0.009, 'Storno ausgeglichen');
assertL7b($inv[0]['side'] !== $lines[0]['side'], 'erste Zeile invertiert');

$cs = PayrollFibuService::linesChecksum($lines);
assertL7b(strlen($cs) === 64, 'Checksum SHA-256');
assertL7b(
    PayrollFibuService::defaultPostingDate(2026, 3) === '2026-03-31',
    'Buchungsdatum Monatsende'
);

$lohn = PayrollEmployerSettings::normalizeLohn([
    'payroll_fibu_live' => '1',
    'fibu_accounts' => ['expense_wages' => '4120', 'junk' => '999'],
]);
assertL7b($lohn['payroll_fibu_live'] === true, 'Flag live normalize');
assertL7b(($lohn['fibu_accounts']['expense_wages'] ?? '') === '4120', 'fibu_accounts normalize');
assertL7b(!isset($lohn['fibu_accounts']['junk']), 'unbekannte Rolle verworfen');

// Hard fail Zerlegung
$bad = $empOk;
$bad['result_json'] = json_encode([
    'gross_total' => 3000, 'lst' => 100, 'solz' => 0, 'kst' => 0,
    'kv_an' => 0, 'rv_an' => 0, 'av_an' => 0, 'pv_an' => 0,
    'net_deductions' => 0, 'net_pay' => 1000,
], JSON_UNESCAPED_UNICODE);
$threw = false;
try {
    PayrollFibuService::aggregateAmounts([$bad]);
} catch (InvalidArgumentException) {
    $threw = true;
}
assertL7b($threw, 'Zerlegung Hard-Fail');

echo "L7b Selftest bestanden.\n";
exit(0);
