<?php
declare(strict_types=1);

/**
 * Lohn L1b Selftest — reine Normalisierung/Diff ohne DB/UI.
 */
require dirname(__DIR__) . '/src/Settings/PayrollEmployerSettings.php';

function assertL1b(bool $ok, string $msg): void
{
    if (!$ok) {
        fwrite(STDERR, "FEHLER: {$msg}\n");
        exit(1);
    }
    echo "OK: {$msg}\n";
}

$defaults = PayrollEmployerSettings::lohnDefaults();
assertL1b($defaults['umlage_u1'] === false && $defaults['baulohn_enabled'] === false, 'Defaults U1/Baulohn false');
assertL1b($defaults['payroll_bank_account_index'] === 0, 'Default Bank-Index 0');

$n = PayrollEmployerSettings::normalizeLohn([
    'bundesland' => 'de-by',
    'umlage_u1' => '1',
    'umlage_u2' => 'ja',
    'umlage_u3' => 0,
    'u1_erstattungssatz_key' => '  T80  ',
    'payroll_bank_account_index' => -3,
    'baulohn_enabled' => 'true',
    'notes' => "  Hinweis  \n",
]);
assertL1b($n['bundesland'] === 'DE-BY', 'Bundesland normalisiert DE-BY');
assertL1b($n['umlage_u1'] === true && $n['umlage_u2'] === true && $n['umlage_u3'] === false, 'Umlagen Bool');
assertL1b($n['u1_erstattungssatz_key'] === 'T80', 'U1-Key getrimmt');
assertL1b($n['payroll_bank_account_index'] === 0, 'Negativer Index → 0');
assertL1b($n['baulohn_enabled'] === true, 'Baulohn-Flag nur Bool');
assertL1b($n['notes'] === 'Hinweis', 'Notes getrimmt');

$bad = PayrollEmployerSettings::normalizeLohn(['bundesland' => 'DE-XX']);
assertL1b($bad['bundesland'] === '', 'Unbekanntes Bundesland verworfen');

$diff = PayrollEmployerSettings::diffLohn(
    PayrollEmployerSettings::lohnDefaults(),
    ['umlage_u3' => true, 'bundesland' => 'DE-NW']
);
$fields = array_column($diff, 'field');
assertL1b(in_array('umlage_u3', $fields, true) && in_array('bundesland', $fields, true), 'Diff enthält geänderte Felder');
assertL1b(count(PayrollEmployerSettings::bundeslandLabels()) === 16, '16 Bundesländer');

$warn = PayrollEmployerSettings::collectWarnings([
    'betriebsnummer' => '123',
    'tax_number_est' => '',
    'finanzamt' => ['bufo_nr' => '', 'name' => ''],
    'lohn' => ['bundesland' => ''],
    'payroll_bank' => ['iban' => ''],
    'employer_name' => 'Test GmbH',
]);
assertL1b(count($warn) >= 3, 'L1c Warnungen bei unvollständigen A/B-Feldern');

echo "L1b/L1c Selftest bestanden.\n";
exit(0);
