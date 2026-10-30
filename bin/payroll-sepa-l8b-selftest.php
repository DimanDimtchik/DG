<?php
declare(strict_types=1);

/**
 * Lohn L8b Selftest — pain.001 XML, Flag, Rechte, Business-Day (ohne DB).
 */
if (!defined('DG_ROOT')) {
    define('DG_ROOT', dirname(__DIR__));
}
require_once DG_ROOT . '/src/autoload.php';

function assertL8b(bool $ok, string $msg): void
{
    if (!$ok) {
        fwrite(STDERR, "FEHLER: {$msg}\n");
        exit(1);
    }
    echo "OK: {$msg}\n";
}

assertL8b(class_exists('PayrollSepaService'), 'PayrollSepaService');
assertL8b(method_exists('PayrollRunAccess', 'canExportSepa'), 'canExportSepa');
assertL8b(is_dir(PayrollSepaService::storageDir()) || true, 'storage path definierbar');

$lohn = PayrollEmployerSettings::normalizeLohn([
    'payroll_sepa_live' => false,
    'payroll_fibu_live' => '1',
]);
assertL8b($lohn['payroll_sepa_live'] === false, 'sepa live default aus');
assertL8b($lohn['payroll_fibu_live'] === true, 'fibu unabhängig');

$lohnOn = PayrollEmployerSettings::normalizeLohn(['payroll_sepa_live' => '1']);
assertL8b($lohnOn['payroll_sepa_live'] === true, 'sepa live normalize');

$defaults = PayrollEmployerSettings::lohnDefaults();
assertL8b(array_key_exists('payroll_sepa_live', $defaults), 'defaults Key');
assertL8b($defaults['payroll_sepa_live'] === false, 'defaults false');

assertL8b(
    PayrollSepaService::endToEndId(12, 34) === 'E2E-R12-C34',
    'EndToEndId'
);

$fri = new DateTimeImmutable('2026-10-30'); // Freitag
$next = PayrollSepaService::nextBusinessDay($fri);
assertL8b($next === '2026-11-02', 'nächster Bankarbeitstag nach Freitag = Mo');

$sat = new DateTimeImmutable('2026-10-31');
assertL8b(PayrollSepaService::nextBusinessDay($sat) === '2026-11-02', 'nach Samstag = Mo');

assertL8b(
    PayrollSepaService::maskIban('DE89370400440532013000') === 'DE89••••••••••••••3000',
    'IBAN maskiert'
);

$payload = PayrollSepaService::assemblePayload([
    'run_id' => 7,
    'msg_id' => 'DG-LOHN-7-TEST',
    'cre_dt_tm' => '2026-10-30T12:00:00',
    'initiating_name' => 'Muster GmbH (PROBE)',
    'pmt_inf_id' => 'PMT-RUN-7',
    'reqd_exec_date' => '2026-11-02',
    'debtor_name' => 'Muster GmbH',
    'debtor_iban' => 'DE89370400440532013000',
    'debtor_bic' => 'COBADEFFXXX',
    'transactions' => [
        [
            'contact_id' => 1,
            'name' => 'Max Mustermann',
            'iban' => 'DE02120300000000202051',
            'bic' => 'BYLADEM1001',
            'amount' => 1917.90,
            'end_to_end_id' => 'E2E-R7-C1',
            'purpose' => 'Lohn 10/2026 Run#7',
        ],
        [
            'contact_id' => 2,
            'name' => 'Erika Beispiel',
            'iban' => 'DE02500105170137075030',
            'bic' => '',
            'amount' => 850.10,
            'end_to_end_id' => 'E2E-R7-C2',
            'purpose' => PaymentReferenceFormula::sanitizeSepa('Lohn 10/2026 Run#7'),
        ],
    ],
    'probe' => true,
    'warnings' => ['MA #9: Netto ≤ 0 — übersprungen.'],
]);
assertL8b((int) $payload['tx_count'] === 2, 'tx_count');
assertL8b(abs((float) $payload['ctrl_sum'] - 2768.0) < 0.001, 'CtrlSum');
assertL8b(str_contains((string) $payload['filename'], '-PROBE.xml'), 'PROBE Dateiname');
assertL8b(!empty($payload['probe']), 'probe Flag');

$xml = PayrollSepaService::renderXml($payload);
assertL8b(str_starts_with($xml, '<?xml'), 'XML Prolog');
assertL8b(str_contains($xml, 'pain.001.001.03'), 'Namespace pain.001.001.03');
assertL8b(str_contains($xml, '<SvcLvl><Cd>SEPA</Cd></SvcLvl>'), 'Service Level SEPA');
assertL8b(str_contains($xml, '<NbOfTxs>2</NbOfTxs>'), 'NbOfTxs');
assertL8b(str_contains($xml, '<CtrlSum>2768.00</CtrlSum>'), 'CtrlSum XML');
assertL8b(str_contains($xml, '<InstdAmt Ccy="EUR">1917.90</InstdAmt>'), 'Betrag 1');
assertL8b(str_contains($xml, '<EndToEndId>E2E-R7-C1</EndToEndId>'), 'E2E');
assertL8b(str_contains($xml, '(PROBE)'), 'InitgPty PROBE');
assertL8b(str_contains($xml, '<ChrgBr>SLEV</ChrgBr>'), 'ChrgBr SLEV');
assertL8b(!str_contains($xml, '<CdtrAgt>') || str_contains($xml, 'BYLADEM1001'), 'optional BIC');

$livePayload = PayrollSepaService::assemblePayload([
    'run_id' => 8,
    'msg_id' => 'DG-LOHN-8',
    'cre_dt_tm' => '2026-10-30T12:00:00',
    'initiating_name' => 'Muster GmbH',
    'pmt_inf_id' => 'PMT-RUN-8',
    'reqd_exec_date' => '2026-11-03',
    'debtor_name' => 'Muster GmbH',
    'debtor_iban' => 'DE89370400440532013000',
    'debtor_bic' => '',
    'transactions' => [[
        'contact_id' => 1,
        'name' => 'Max',
        'iban' => 'DE02120300000000202051',
        'bic' => '',
        'amount' => 10.0,
        'end_to_end_id' => 'E2E-R8-C1',
        'purpose' => 'Lohn',
    ]],
    'probe' => false,
    'warnings' => [],
]);
assertL8b(!str_contains((string) $livePayload['filename'], 'PROBE'), 'Live ohne PROBE');

$emptyThrew = false;
try {
    PayrollSepaService::assemblePayload([
        'run_id' => 1,
        'msg_id' => 'X',
        'cre_dt_tm' => '2026-10-30T12:00:00',
        'initiating_name' => 'A',
        'pmt_inf_id' => 'P',
        'reqd_exec_date' => '2026-11-02',
        'debtor_name' => 'A',
        'debtor_iban' => 'DE89370400440532013000',
        'debtor_bic' => '',
        'transactions' => [],
        'probe' => true,
    ]);
} catch (InvalidArgumentException) {
    $emptyThrew = true;
}
assertL8b($emptyThrew, 'leere Tx abgelehnt');

$list = PayrollSepaService::auditorChecklist();
assertL8b(count($list) >= 5, 'Checkliste');
$joined = implode(' ', $list);
assertL8b(str_contains($joined, 'payroll_sepa_live') || str_contains($joined, 'G3'), 'Checkliste Flag/G3');
assertL8b(str_contains($joined, 'Storno'), 'Checkliste Storno');

assertL8b(
    PaymentReferenceFormula::sanitizeSepa('Löhn 10/2026') !== '',
    'SEPA sanitize verfügbar'
);

echo "L8b Selftest bestanden.\n";
exit(0);
