<?php
declare(strict_types=1);

/**
 * Lohn L3b Selftest — Seed-Katalog ohne DB.
 */
require_once dirname(__DIR__) . '/src/autoload.php';

function assertL3b(bool $ok, string $msg): void
{
    if (!$ok) {
        fwrite(STDERR, "FEHLER: {$msg}\n");
        exit(1);
    }
    echo "OK: {$msg}\n";
}

$seed = PayrollWageTypeRepository::seedCatalog();
assertL3b(count($seed) === 10, '10 Seed-Lohnarten');
$codes = array_column($seed, 'code');
assertL3b(in_array('SALARY_MONTH', $codes, true) && in_array('NET_DEDUCTION', $codes, true), 'Kern-Codes vorhanden');
assertL3b(!in_array('BAU_SOKA', $codes, true), 'Keine Bau-Arten im Seed');
assertL3b(isset(DepartmentAccess::MODULE_LABELS['lohn']), 'Modul lohn registriert');
assertL3b(PayrollEmployeeAccess::moduleRegistered() === true, 'PayrollEmployeeAccess erkennt Modul');

echo "L3b Selftest bestanden.\n";
exit(0);
