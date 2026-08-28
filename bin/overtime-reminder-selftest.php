<?php
declare(strict_types=1);

/**
 * Selbsttest für ArbZG-Erinnerung (6 Monate, Ø 48 h/Woche, WD 6/097/19).
 */
require dirname(__DIR__) . '/bootstrap.php';

function line(string $s = ''): void { echo $s . "\n"; }

$failed = 0;

line('== Auswertungszeitraum (6 Kalendermonate) ==');
$period = ArbzgComplianceService::completedEvaluationPeriod('2026-08-01');
if ($period === null || $period['from'] !== '2026-02-01' || $period['to'] !== '2026-07-31') {
    $failed++;
    line('FEHLER  Erwartet 2026-02-01 bis 2026-07-31, erhalten: ' . json_encode($period));
} else {
    line('OK  ' . $period['from'] . ' – ' . $period['to']);
}

line('');
line('== Monatserster für E-Mail ==');
if (!ArbzgComplianceService::shouldSendMonthlyReminders('2026-08-01')) {
    $failed++;
    line('FEHLER  2026-08-01 soll Erinnerung auslösen');
} else {
    line('OK  Erinnerung am 1. des Monats');
}
if (ArbzgComplianceService::shouldSendMonthlyReminders('2026-08-15')) {
    $failed++;
    line('FEHLER  2026-08-15 soll keine E-Mail auslösen');
} else {
    line('OK  Keine E-Mail Mitte im Monat');
}

line('');
line('== Erinnerungstext Verantwortliche ==');
$manager = ArbzgComplianceService::buildManagerMessage('Max Mustermann', 6);
line($manager);
if (!str_contains($manager, '48 Stunden') || !str_contains($manager, 'WD 6/097/19')) {
    $failed++;
    line('FEHLER  Verantwortlichen-Text unvollständig');
} else {
    line('OK  Verantwortlichen-Text');
}

line('');
line('== Erinnerungstext Mitarbeiter ==');
$employee = ArbzgComplianceService::buildEmployeeMessage(6);
line($employee);
if (!str_contains($employee, '48 Stunden') || !str_contains($employee, 'Ihre Überstunden')) {
    $failed++;
    line('FEHLER  Mitarbeiter-Text unvollständig');
} else {
    line('OK  Mitarbeiter-Text');
}

line('');
if ($failed > 0) {
    line("FEHLGESCHLAGEN: {$failed} Prüfung(en)");
    exit(1);
}

line('Alle Prüfungen bestanden.');
