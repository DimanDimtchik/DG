<?php
declare(strict_types=1);

/**
 * Lohn L3c Selftest — Parse/Validierung ohne DB.
 */
require_once dirname(__DIR__) . '/src/autoload.php';

function assertL3c(bool $ok, string $msg): void
{
    if (!$ok) {
        fwrite(STDERR, "FEHLER: {$msg}\n");
        exit(1);
    }
    echo "OK: {$msg}\n";
}

assertL3c(
    PayrollEmployeeWageAssignmentRepository::parseAmount('1.234,50') === '1234.5000',
    'Betrag DE mit Tausenderpunkt'
);
assertL3c(
    PayrollEmployeeWageAssignmentRepository::parseAmount('12,5') === '12.5000',
    'Betrag mit Komma'
);
assertL3c(
    PayrollEmployeeWageAssignmentRepository::parseAmount('') === '0.0000',
    'Leerbetrag → 0'
);
assertL3c(
    PayrollEmployeeWageAssignmentRepository::parseAmount('abc') === null,
    'Ungültiger Betrag'
);
assertL3c(
    PayrollEmployeeWageAssignmentRepository::parseDate('') === null,
    'Leeres Datum'
);
assertL3c(
    PayrollEmployeeWageAssignmentRepository::parseDate('2026-03-01') === '2026-03-01',
    'ISO-Datum'
);

$parsed = PayrollEmployeeWageAssignmentRepository::parseFromPost([
    'payroll_wage_assignments' => [
        ['wage_type_id' => '0', 'amount' => '100'], // leer
        ['wage_type_id' => '3', 'amount' => '2.500,00', 'valid_from' => '2026-01-01', 'valid_to' => '', 'priority' => '10', 'notes' => 'Festgehalt'],
        ['wage_type_id' => '4', 'amount' => '15', 'delete' => '1'],
    ],
]);
assertL3c(count($parsed) === 1, 'Eine gültige Zeile');
assertL3c((int) $parsed[0]['wage_type_id'] === 3, 'wage_type_id');
assertL3c($parsed[0]['amount'] === '2500.0000', 'amount geparst');
assertL3c($parsed[0]['valid_from'] === '2026-01-01', 'valid_from');
assertL3c($parsed[0]['valid_to'] === null, 'valid_to leer');

$threw = false;
try {
    PayrollEmployeeWageAssignmentRepository::parseFromPost([
        'payroll_wage_assignments' => [
            ['wage_type_id' => '1', 'amount' => '10', 'valid_from' => '2026-06-01', 'valid_to' => '2026-01-01'],
        ],
    ]);
} catch (InvalidArgumentException) {
    $threw = true;
}
assertL3c($threw, 'Zeitraum-Validierung');

assertL3c(
    class_exists('PayrollEmployeeWageAssignmentRepository'),
    'Klasse geladen'
);

echo "L3c Selftest bestanden.\n";
exit(0);
