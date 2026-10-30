<?php
declare(strict_types=1);

/**
 * Lohn L5b Selftest — Status/Reason/Access ohne DB.
 */
require_once dirname(__DIR__) . '/src/autoload.php';

function assertL5b(bool $ok, string $msg): void
{
    if (!$ok) {
        fwrite(STDERR, "FEHLER: {$msg}\n");
        exit(1);
    }
    echo "OK: {$msg}\n";
}

assertL5b(
    PayrollRunRepository::statuses() === ['draft', 'calculated', 'approved', 'locked'],
    'Status-Reihenfolge'
);
assertL5b(PayrollRunRepository::KIND_REGULAR === 'regular', 'kind regular');

$threw = false;
try {
    PayrollRunRepository::requireReason('approve', 'kurz');
} catch (InvalidArgumentException) {
    $threw = true;
}
assertL5b($threw, 'approve braucht Begründung ≥10');
assertL5b(
    PayrollRunRepository::requireReason('approve', 'Freigabe nach Prüfung OK') === 'Freigabe nach Prüfung OK',
    'approve Begründung ok'
);
assertL5b(
    PayrollRunRepository::requireReason('calculate', '') === '',
    'calculate ohne Pflicht-Reason'
);

assertL5b(class_exists('PayrollRunAccess'), 'PayrollRunAccess');
assertL5b(class_exists('PayrollRunRepository'), 'PayrollRunRepository');

echo "L5b Selftest bestanden.\n";
exit(0);
