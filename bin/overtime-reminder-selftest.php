<?php
declare(strict_types=1);

/**
 * Selbsttest für Überstunden-Datumsregeln und Erinnerungstexte (ohne DB).
 */
require dirname(__DIR__) . '/bootstrap.php';

function line(string $s = ''): void { echo $s . "\n"; }

line('== OvertimeDateRules ==');

$cases = [
    ['2026-01-15', 5, '2026-06-15'],
    ['2026-01-15', 6, '2026-07-15'],
    ['2026-01-31', 1, '2026-02-28'],
    ['2024-01-31', 1, '2024-02-29'],
];

$failed = 0;
foreach ($cases as [$date, $months, $expected]) {
    $actual = OvertimeDateRules::addMonths($date, $months);
    $ok = $actual === $expected;
    if (!$ok) {
        $failed++;
    }
    line(($ok ? 'OK' : 'FEHLER') . "  {$date} + {$months} Monate = {$actual} (erwartet {$expected})");
}

line('');
line('== Erinnerungsfenster ==');
$windowCases = [
    ['2026-06-15', '2026-06-01', '2026-07-15', true],
    ['2026-05-31', '2026-06-01', '2026-07-15', false],
    ['2026-07-15', '2026-06-01', '2026-07-15', true],
    ['2026-07-16', '2026-06-01', '2026-07-15', false],
];
foreach ($windowCases as [$today, $reminder, $expires, $expected]) {
    $actual = OvertimeDateRules::isReminderDue($today, $reminder, $expires);
    $ok = $actual === $expected;
    if (!$ok) {
        $failed++;
    }
    line(($ok ? 'OK' : 'FEHLER') . "  heute={$today}, reminder={$reminder}, expires={$expires} → " . ($actual ? 'fällig' : 'nicht fällig'));
}

line('');
line('== Erinnerungstext ==');
$msg = OvertimeLotRepository::buildReminderMessage('Max Mustermann', 150, '2026-07-15');
line($msg);
if (!str_contains($msg, 'Max Mustermann') || !str_contains($msg, '2:30')) {
    $failed++;
    line('FEHLER: Text unvollständig');
} else {
    line('OK  Text enthält Name und Stunden');
}

line('');
if ($failed > 0) {
    line("FEHLGESCHLAGEN: {$failed} Prüfung(en)");
    exit(1);
}

line('Alle Prüfungen bestanden.');
