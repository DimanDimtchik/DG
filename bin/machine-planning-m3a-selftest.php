<?php
declare(strict_types=1);

/**
 * Selbsttest fuer Maschinenblock M3a ohne echte CRM-Datenbank.
 */
final class Database
{
    public static PDO $pdo;

    public static function isConfigured(): bool
    {
        return true;
    }

    public static function pdo(): PDO
    {
        return self::$pdo;
    }
}

final class MigrationRunner
{
    public static function runPending(): void
    {
        // Selftest erstellt das Schema selbst.
    }
}

require dirname(__DIR__) . '/src/Recipe/MachinePlanningRepository.php';

function assertM3a(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "FEHLER: {$message}\n");
        exit(1);
    }
    echo "OK: {$message}\n";
}

Database::$pdo = new PDO('sqlite::memory:');
Database::$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
Database::pdo()->exec(
    'CREATE TABLE dg_machine_booking_windows (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        work_center_id INTEGER NOT NULL,
        booking_type TEXT NOT NULL DEFAULT "production",
        recipe_id INTEGER NULL,
        maintenance_plan_id INTEGER NULL,
        maintenance_log_id INTEGER NULL,
        title TEXT NOT NULL DEFAULT "",
        starts_at TEXT NOT NULL,
        ends_at TEXT NOT NULL,
        planned_output_qty REAL NOT NULL DEFAULT 0,
        unit TEXT NOT NULL DEFAULT "",
        booking_status TEXT NOT NULL DEFAULT "planned",
        notes TEXT NOT NULL DEFAULT "",
        created_at TEXT DEFAULT CURRENT_TIMESTAMP,
        updated_at TEXT DEFAULT CURRENT_TIMESTAMP
    )'
);

$bookingId = MachinePlanningRepository::createBookingWindow([
    'work_center_id' => 1,
    'booking_type' => 'production',
    'recipe_id' => 2,
    'title' => 'Charge Morgen',
    'starts_at' => '2026-10-28 08:00:00',
    'ends_at' => '2026-10-28 10:00:00',
    'planned_output_qty' => '120,5',
    'unit' => 'kg',
    'booking_status' => 'planned',
    'notes' => 'Testlauf',
]);
$all = MachinePlanningRepository::listBookingWindows();
assertM3a($bookingId > 0 && count($all) === 1, 'Belegungsfenster anlegen und global listen');
assertM3a((float) $all[0]['planned_output_qty'] === 120.5, 'Kommazahl fuer geplanten Output wird normalisiert');

$overlap = MachinePlanningRepository::listBookingWindows(1, '2026-10-28 09:00:00', '2026-10-28 07:00:00', true);
assertM3a(count($overlap) === 1, 'Zeitfenster-Ueberlappung wird gefunden');
$conflicts = MachinePlanningRepository::listBookingConflicts(1, '2026-10-28 09:00:00', '2026-10-28 09:30:00');
assertM3a(count($conflicts) === 1, 'Konflikt fuer gleiche Maschine wird gefunden');
$otherMachineConflicts = MachinePlanningRepository::listBookingConflicts(2, '2026-10-28 09:00:00', '2026-10-28 09:30:00');
assertM3a($otherMachineConflicts === [], 'Andere Maschine wird nicht als Konflikt gewertet');

MachinePlanningRepository::cancelBookingWindow($bookingId);
$withoutCancelled = MachinePlanningRepository::listBookingWindows(1, null, null, true);
assertM3a($withoutCancelled === [], 'Stornierte Belegung wird optional ausgeblendet');
$conflictsAfterCancel = MachinePlanningRepository::listBookingConflicts(1, '2026-10-28 09:00:00', '2026-10-28 09:30:00');
assertM3a($conflictsAfterCancel === [], 'Stornierte Belegung wird nicht als Konflikt gewertet');

$failed = false;
try {
    MachinePlanningRepository::createBookingWindow([
        'work_center_id' => 1,
        'title' => 'Ungueltig',
        'starts_at' => '2026-10-28 10:00:00',
        'ends_at' => '2026-10-28 09:00:00',
    ]);
} catch (InvalidArgumentException) {
    $failed = true;
}
assertM3a($failed, 'Ende vor Start wird abgelehnt');

$editId = MachinePlanningRepository::createBookingWindow([
    'work_center_id' => 3,
    'booking_type' => 'production',
    'title' => 'Editierbar',
    'starts_at' => '2026-10-29 08:00:00',
    'ends_at' => '2026-10-29 09:00:00',
    'planned_output_qty' => 10,
    'unit' => 'kg',
    'booking_status' => 'planned',
]);
MachinePlanningRepository::updateBookingWindow($editId, [
    'work_center_id' => 3,
    'booking_type' => 'maintenance',
    'title' => 'Editiert',
    'starts_at' => '2026-10-29 08:30:00',
    'ends_at' => '2026-10-29 09:30:00',
    'planned_output_qty' => '15,5',
    'unit' => 'h',
    'booking_status' => 'done',
    'notes' => 'nachgezogen',
]);
$edited = MachinePlanningRepository::findBookingWindow($editId);
assertM3a(is_array($edited) && (string) $edited['title'] === 'Editiert', 'Belegungsfenster laesst sich bearbeiten');
assertM3a((string) ($edited['booking_status'] ?? '') === 'done', 'Status erledigt beim Speichern');
assertM3a((float) ($edited['planned_output_qty'] ?? 0) === 15.5, 'Output beim Bearbeiten normalisiert');
MachinePlanningRepository::setBookingWindowStatus($editId, 'planned');
$reopened = MachinePlanningRepository::findBookingWindow($editId);
assertM3a((string) ($reopened['booking_status'] ?? '') === 'planned', 'Status wieder auf geplant setzen');
$conflictsIgnoreSelf = MachinePlanningRepository::listBookingConflicts(
    3,
    '2026-10-29 08:30:00',
    '2026-10-29 09:30:00',
    $editId
);
assertM3a($conflictsIgnoreSelf === [], 'Konfliktpruefung ignoriert das eigene Fenster beim Update');
MachinePlanningRepository::cancelBookingWindow($editId);
$blocked = false;
try {
    MachinePlanningRepository::updateBookingWindow($editId, [
        'work_center_id' => 3,
        'title' => 'Darf nicht',
        'starts_at' => '2026-10-29 10:00:00',
        'ends_at' => '2026-10-29 11:00:00',
        'booking_status' => 'planned',
    ]);
} catch (InvalidArgumentException) {
    $blocked = true;
}
assertM3a($blocked, 'Stornierte Fenster lassen sich nicht bearbeiten');

echo "Fertig.\n";
