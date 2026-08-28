<?php
declare(strict_types=1);

/** Tagesaggregation aus Stempeldaten und Überstunden-Gutschrift. */
final class TimeWorkDayService
{
    /**
     * Aggregiert alle Mitarbeiter für ein abgeschlossenes Datum (typisch Vortag nach Autoclose).
     */
    public static function aggregateDate(string $workDate): int
    {
        if (!Database::isConfigured() || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $workDate)) {
            return 0;
        }
        MigrationRunner::runPending();

        $count = 0;
        foreach (self::staffContactIds() as $contactId) {
            if (self::aggregateContactDay($contactId, $workDate)) {
                $count++;
            }
        }

        return $count;
    }

    public static function aggregateContactDay(int $contactId, string $workDate): bool
    {
        if ($contactId < 1 || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $workDate)) {
            return false;
        }

        $events = TimeClockRepository::eventsForContact($contactId, $workDate);
        if ($events === []) {
            return false;
        }

        $lastType = (string) ($events[count($events) - 1]['event_type'] ?? '');
        if ($lastType !== TimeClockRepository::EVENT_CLOCK_OUT) {
            return false;
        }

        $summary = TimeClockService::daySummary($contactId, $workDate);
        $employeeData = self::employeeDataForContact($contactId);
        $netWorked = (int) ($summary['worked_minutes'] ?? 0);
        $scheduled = (int) ($summary['scheduled_minutes'] ?? 0);
        $breakMinutes = (int) ($summary['break_minutes'] ?? 0);

        $overtimeMinutes = 0;
        if (EmployeeData::overtimeAllowed($employeeData) && !EmployeeData::isMinijob($employeeData)) {
            $overtimeMinutes = max(0, $netWorked - $scheduled);
        }

        TimeWorkDayRepository::upsert($contactId, $workDate, [
            'scheduled_minutes' => $scheduled,
            'worked_minutes' => $netWorked,
            'break_minutes' => $breakMinutes,
            'overtime_minutes' => $overtimeMinutes,
            'status' => 'closed',
        ]);

        if ($overtimeMinutes > 0) {
            $cfg = TimeTrackingSettings::config();
            $compensationMonths = max(1, (int) ($cfg['overtime_compensation_months'] ?? 6));
            $reminderAfterMonths = max(1, (int) ($cfg['overtime_reminder_after_months'] ?? 5));
            if ($reminderAfterMonths >= $compensationMonths) {
                $reminderAfterMonths = max(1, $compensationMonths - 1);
            }

            $expiresAt = OvertimeDateRules::addMonths($workDate, $compensationMonths);
            $reminderDueAt = OvertimeDateRules::addMonths($workDate, $reminderAfterMonths);

            OvertimeLotRepository::upsertLot(
                $contactId,
                $workDate,
                $overtimeMinutes,
                $expiresAt,
                $reminderDueAt,
            );
        }

        return true;
    }

    /**
     * @return list<int>
     */
    private static function staffContactIds(): array
    {
        $pdo = Database::pdo();
        $stmt = $pdo->query(
            "SELECT id FROM dg_contacts
             WHERE contact_role IN ('dg_eigenmitarbeiter', 'administrator', 'mitarbeiter')
             ORDER BY id ASC"
        );
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $out = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $id = (int) ($row['id'] ?? 0);
            if ($id > 0) {
                $out[] = $id;
            }
        }

        return $out;
    }

    /**
     * @return array<string, string>
     */
    private static function employeeDataForContact(int $contactId): array
    {
        $contact = ContactRepository::findById($contactId);
        if ($contact === null) {
            return EmployeeData::empty();
        }

        $raw = $contact->employeeData ?? [];

        return is_array($raw) ? EmployeeData::sanitize($raw) : EmployeeData::empty();
    }
}
