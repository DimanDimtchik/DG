<?php
declare(strict_types=1);

/**
 * Lohn L6b Selftest — Storage-Pfade, HTML-Kontext, optional Dompdf (ohne DB).
 */
if (!defined('DG_ROOT')) {
    define('DG_ROOT', dirname(__DIR__));
}
$composer = DG_ROOT . '/vendor/autoload.php';
if (is_readable($composer)) {
    require_once $composer;
}
require_once DG_ROOT . '/src/autoload.php';

function assertL6b(bool $ok, string $msg): void
{
    if (!$ok) {
        fwrite(STDERR, "FEHLER: {$msg}\n");
        exit(1);
    }
    echo "OK: {$msg}\n";
}

assertL6b(class_exists('PayrollDocumentStorage'), 'PayrollDocumentStorage');
assertL6b(class_exists('PayrollPayslipPdfService'), 'PayrollPayslipPdfService');
assertL6b(class_exists('PayrollDocumentRepository'), 'PayrollDocumentRepository');

$rel = PayrollDocumentStorage::relativePath(2026, 3, 42, 7, false);
assertL6b($rel === '2026/03/lohn-42-202603-run7.pdf', 'Final-Dateiname/Pfad');
$relDraft = PayrollDocumentStorage::relativePath(2026, 3, 42, 7, true);
assertL6b($relDraft === '2026/03/lohn-42-202603-run7-draft.pdf', 'Draft-Dateiname/Pfad');
assertL6b(
    PayrollPayslipPdfService::filename(42, 2026, 3, 7, false) === 'lohn-42-202603-run7.pdf',
    'filename() Final'
);

$run = [
    'id' => 7,
    'period_year' => 2026,
    'period_month' => 3,
    'status' => 'locked',
    'kind' => 'regular',
];
$emp = [
    'contact_id' => 42,
    'status' => 'ok',
    'path' => 'standard',
    'result_json' => json_encode([
        'gross_total' => 3000.0,
        'gross_taxable' => 3000.0,
        'gross_sv' => 3000.0,
        'lst' => 420.0,
        'solz' => 23.10,
        'kst' => 0.0,
        'kv_an' => 270.0,
        'rv_an' => 279.0,
        'av_an' => 39.0,
        'pv_an' => 51.0,
        'kv_ag' => 270.0,
        'rv_ag' => 279.0,
        'av_ag' => 39.0,
        'pv_ag' => 51.0,
        'u1' => 51.0,
        'u2' => 12.0,
        'u3' => 1.80,
        'flat_lst' => 0.0,
        'flat_other' => 0.0,
        'net_deductions' => 0.0,
        'net_pay' => 1917.90,
        'employer_cost' => 3652.80,
    ], JSON_UNESCAPED_UNICODE),
    'snapshot_json' => json_encode([
        'employee' => [
            'tax_id' => '12345678901',
            'tax_class' => '1',
            'religion_code' => 'none',
            'child_allowances' => '0',
            'social_security_number' => '12120155A123',
            'payroll_employment_type' => 'fulltime',
            'entry_date' => '2024-01-01',
        ],
    ], JSON_UNESCAPED_UNICODE),
    'warnings_json' => json_encode(['Fixture-Hinweis'], JSON_UNESCAPED_UNICODE),
];

// buildContext braucht DB für linesForEmployee — Stub über direkten Kontext-Test via renderHtml
$ctx = [
    'run_id' => 7,
    'period_year' => 2026,
    'period_month' => 3,
    'run_status' => 'locked',
    'kind' => 'regular',
    'kind_label' => 'Regulär',
    'path' => 'standard',
    'generated_at' => '30.10.2026 12:00',
    'employer' => [
        'employer_name' => 'Muster GmbH',
        'legal_name' => 'Muster GmbH',
        'betriebsnummer' => '12345678',
        'tax_number_est' => '12/345/67890',
        'address' => ['street' => 'Testweg 1', 'postal' => '10115', 'city' => 'Berlin'],
    ],
    'employee' => [
        'display_name' => 'Max Mustermann',
        'address' => 'Beispielstr. 2, 10115 Berlin',
        'tax_id' => '12345678901',
        'tax_class' => '1',
        'religion_code' => 'none',
        'child_allowances' => '0',
        'social_security_number' => '12120155A123',
        'payroll_employment_type' => 'fulltime',
        'entry_date' => '2024-01-01',
    ],
    'amounts' => json_decode((string) $emp['result_json'], true),
    'lines' => [
        ['code' => 'SALARY_MONTH', 'source' => 'assignment', 'amount' => 3000, 'hours' => null],
    ],
    'warnings' => ['Fixture-Hinweis'],
];

$html = PayrollPayslipPdfService::renderHtml($ctx, false);
assertL6b(str_contains($html, 'Lohnabrechnung 03/2026'), 'HTML Titel Periode');
assertL6b(str_contains($html, 'Muster GmbH'), 'HTML Arbeitgeber');
assertL6b(str_contains($html, 'Max Mustermann'), 'HTML Mitarbeiter');
assertL6b(str_contains($html, '1.917,90'), 'HTML Netto DE-Format');
assertL6b(str_contains($html, 'SALARY_MONTH'), 'HTML Lohnart');
assertL6b(str_contains($html, 'Keine ELStAM'), 'HTML Fußhinweis');
assertL6b(!str_contains($html, 'ENTWURF'), 'Final ohne Wasserzeichen');

$htmlDraft = PayrollPayslipPdfService::renderHtml($ctx, true);
assertL6b(str_contains($htmlDraft, 'ENTWURF — nicht verbindlich'), 'Draft-Wasserzeichen');

// Storage write/read roundtrip (Temp unter storage/payroll)
$tmpRel = 'selftest/l6b-roundtrip.pdf';
$payload = "%PDF-1.4 selftest\n";
PayrollDocumentStorage::write($tmpRel, $payload, true);
$read = PayrollDocumentStorage::read($tmpRel);
assertL6b($read === $payload, 'Storage write/read');
$threw = false;
try {
    PayrollDocumentStorage::write($tmpRel, $payload, false);
} catch (RuntimeException) {
    $threw = true;
}
assertL6b($threw, 'Immutability: kein Überschreiben ohne allowOverwrite');
PayrollDocumentStorage::delete($tmpRel);
assertL6b(PayrollDocumentStorage::read($tmpRel) === null, 'Storage delete');

if (PayrollPayslipPdfService::isAvailable()) {
    $pdf = PayrollPayslipPdfService::renderPdf($ctx, false);
    assertL6b(str_starts_with($pdf, '%PDF'), 'Dompdf Binary beginnt mit %PDF');
    assertL6b(strlen($pdf) > 500, 'Dompdf Binary Größe');
    $checksum = hash('sha256', $pdf);
    assertL6b(strlen($checksum) === 64, 'SHA-256 Checksum');
    echo "OK: Dompdf verfügbar und PDF erzeugt\n";
} else {
    echo "HINWEIS: Dompdf nicht geladen — Binary-Test übersprungen (HTML/Storage ok).\n";
}

assertL6b(PayrollDocumentRepository::KIND_PAYSLIP === 'payslip', 'kind payslip');
assertL6b(PayrollDocumentRepository::KIND_DRAFT === 'payslip_draft', 'kind draft');
unset($run, $emp); // Fixture-Vars nur für Klarheit

echo "L6b Selftest bestanden.\n";
exit(0);
