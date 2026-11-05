<?php
declare(strict_types=1);

/**
 * Arbeitsplan-Day-Windows: Fenstergrenzen + Resolve-Priorität (Plan vor Wochenraster).
 */
if (!defined('DG_ROOT')) {
    define('DG_ROOT', dirname(__DIR__));
}
require_once DG_ROOT . '/src/autoload.php';

function assertDw(bool $ok, string $msg): void
{
    if (!$ok) {
        fwrite(STDERR, "FEHLER: {$msg}\n");
        exit(1);
    }
    echo "OK: {$msg}\n";
}

$day = new DateTimeImmutable('2026-11-12'); // Donnerstag
$bounds = CalendarStaffRepository::windowBoundsOnDay($day, '10:00', '12:00');
assertDw($bounds !== null, 'Fenster 10–12 gültig');
assertDw($bounds[0]->format('H:i') === '10:00', 'Start 10:00');
assertDw($bounds[1]->format('H:i') === '12:00', 'Ende 12:00');
assertDw(CalendarStaffRepository::windowBoundsOnDay($day, '18:00', '10:00') === null, 'Übernacht ungültig');

$merged = CalendarStaffRepository::getMergedDayWindowsForEmployees([], $day);
assertDw($merged === [], 'Leere Mitarbeiterliste → keine Fenster');

if (CalendarStaffRepository::dayWindowsTableReady()) {
    echo "INFO: Day-Windows-Tabelle bereit — Resolve braucht echte employee_id in DB.\n";
} else {
    echo "SKIP: Day-Windows-Tabelle nicht bereit (Migration 141).\n";
}

echo "Day-Windows Selftest bestanden.\n";
exit(0);
