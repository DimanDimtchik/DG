<?php
declare(strict_types=1);

/**
 * Lohn L2b Selftest — sanitize/Warnungen ohne DB.
 */
require_once dirname(__DIR__) . '/src/autoload.php';

function assertL2b(bool $ok, string $msg): void
{
    if (!$ok) {
        fwrite(STDERR, "FEHLER: {$msg}\n");
        exit(1);
    }
    echo "OK: {$msg}\n";
}

$keys = EmployeeData::payrollFieldKeys();
assertL2b(in_array('tax_id', $keys, true) && in_array('sv_exempt', $keys, true), 'payrollFieldKeys enthält Katalog B');
assertL2b(isset(EmployeeData::sectionLabels()['payroll']), 'section payroll Label');

$clean = EmployeeData::sanitize([
    'tax_id' => '12 345-678901',
    'tax_class' => '3',
    'child_allowances' => '1,5',
    'religion_code' => 'ek',
    'health_insurance_company_number' => '12345678',
    'contribution_group_key' => '1111',
    'person_group_key' => '101',
    'payroll_employment_type' => 'fulltime',
    'payroll_payout_bank_index' => '-2',
    'controlling_managing_director' => '1',
    'sv_exempt' => '1',
    'employment_type' => '',
]);
assertL2b($clean['tax_id'] === '12345678901', 'tax_id nur Ziffern');
assertL2b($clean['child_allowances'] === '1.5', 'Kinderfreibeträge 0,5-Schritt');
assertL2b($clean['payroll_payout_bank_index'] === '0', 'Bank-Index ≥ 0');
assertL2b($clean['controlling_managing_director'] === '', 'GF-Flag ohne managing_director Typ verworfen');
assertL2b($clean['sv_exempt'] === '', 'sv_exempt ohne GF-Flag verworfen');
assertL2b($clean['employment_type'] === 'regular', 'weich sync fulltime → regular');

$gf = EmployeeData::sanitize([
    'payroll_employment_type' => 'managing_director',
    'controlling_managing_director' => '1',
    'sv_exempt' => '1',
    'controlling_md_rationale' => 'kurz',
    'employment_type' => 'intern',
]);
assertL2b($gf['sv_exempt'] === '1', 'sv_exempt mit GF-Flag erlaubt');
assertL2b($gf['employment_type'] === 'intern', 'Konflikt Zeit-Typ intern nicht überschrieben');

$midi = EmployeeData::sanitize([
    'payroll_employment_type' => 'midijob',
    'tax_id' => '12345678901',
    'tax_class' => '1',
    'child_allowances' => '0',
    'religion_code' => 'none',
    'entry_date' => '2024-01-01',
    'annual_earnings_estimate_eur' => '',
]);
$missing = EmployeeData::missingPayrollFieldsForRun($midi);
assertL2b(in_array('Jahresentgelt-Schätzer', $missing, true), 'Midi ohne Schätzer = Lücke');

$okChild = EmployeeData::missingPayrollFieldsForRun(array_merge($midi, [
    'annual_earnings_estimate_eur' => '22000',
    'social_security_number' => '12222222X123',
    'health_insurance' => 'TK',
    'contribution_group_key' => '1111',
    'person_group_key' => '101',
]));
assertL2b(!in_array('Kinderfreibeträge (0 eintragen wenn keine)', $okChild, true), 'Kinderfreibetrag 0 zählt als gesetzt');

$preserved = EmployeeData::preservePayrollIfDenied(
    EmployeeData::sanitize(['tax_id' => '99999999999', 'payroll_employment_type' => 'minijob']),
    EmployeeData::sanitize(['tax_id' => '11111111111', 'payroll_employment_type' => 'fulltime']),
    false
);
assertL2b($preserved['tax_id'] === '11111111111', 'preservePayrollIfDenied behält Bestand');

assertL2b(PayrollEmployeeAccess::moduleRegistered() === false, 'Modul lohn noch nicht in MODULE_LABELS');

echo "L2b Selftest bestanden.\n";
exit(0);
