<?php
declare(strict_types=1);

/**
 * Zeiterfassung Z2b/Z3d/Z4e: Soll-Minuten pro Kontakt und Tag.
 *
 * Prio: genehmigte Abwesenheit (Soll 0, außer ot_comp) → Schicht → MA daily_work_minutes
 * → working_hours → Kalender → 0.
 */
final class TimeScheduleService
{
    /**
     * Soll-Arbeitsminuten für einen Mitarbeiter an einem Kalendertag.
     */
    public static function scheduledMinutesFor(int $contactId, string $dateYmd): int
    {
        if ($contactId < 1 || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateYmd)) {
            return 0;
        }

        // Z4e: genehmigte Abwesenheit schlägt Schicht/Stammdaten/Kalender — außer Überstundenabbau (Ist aus Konto).
        $absence = self::approvedAbsenceFor($contactId, $dateYmd);
        if ($absence !== null && (string) ($absence['type'] ?? '') !== 'ot_comp') {
            return 0;
        }

        return self::targetMinutesIgnoringAbsence($contactId, $dateYmd);
    }

    /**
     * Soll ohne Abwesenheits-Nullung (Schicht → Stammdaten → Kalender).
     */
    public static function targetMinutesIgnoringAbsence(int $contactId, string $dateYmd): int
    {
        if ($contactId < 1 || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateYmd)) {
            return 0;
        }

        $shiftMinutes = self::shiftTargetMinutes($contactId, $dateYmd);
        if ($shiftMinutes > 0) {
            return $shiftMinutes;
        }

        $personal = self::personalTargetMinutes(self::employeeDataForContact($contactId));
        if ($personal > 0) {
            return $personal;
        }

        return self::calendarTargetMinutes($dateYmd);
    }

    /**
     * Genehmigte Abwesenheit am Tag (Z4), oder null.
     *
     * @return array<string, mixed>|null
     */
    public static function approvedAbsenceFor(int $contactId, string $dateYmd): ?array
    {
        if ($contactId < 1 || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateYmd)) {
            return null;
        }
        if (!class_exists('TimeAbsenceRepository')) {
            return null;
        }

        return TimeAbsenceRepository::approvedOnDate($contactId, $dateYmd);
    }

    /**
     * Schicht-Zuordnung für den Tag (Z3c), oder null.
     *
     * @return array<string, mixed>|null
     */
    public static function shiftAssignmentFor(int $contactId, string $dateYmd): ?array
    {
        if ($contactId < 1 || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateYmd)) {
            return null;
        }
        if (!class_exists('TimeShiftAssignmentRepository')) {
            return null;
        }

        return TimeShiftAssignmentRepository::findForContactDate($contactId, $dateYmd);
    }

    /**
     * Soll aus Schicht-Vorlage (nach Abwesenheit), sonst 0.
     */
    public static function shiftTargetMinutes(int $contactId, string $dateYmd): int
    {
        $assignment = self::shiftAssignmentFor($contactId, $dateYmd);
        if ($assignment === null) {
            return 0;
        }
        $minutes = (int) ($assignment['duration_minutes'] ?? 0);

        return $minutes > 0 ? min(960, $minutes) : 0;
    }

    /**
     * Quelle des Soll-Werts: absence | shift | personal | calendar | none.
     */
    public static function scheduleSource(int $contactId, string $dateYmd): string
    {
        if ($contactId < 1 || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateYmd)) {
            return 'none';
        }
        if (self::approvedAbsenceFor($contactId, $dateYmd) !== null) {
            return 'absence';
        }
        if (self::shiftTargetMinutes($contactId, $dateYmd) > 0) {
            return 'shift';
        }
        if (self::personalTargetMinutes(self::employeeDataForContact($contactId)) > 0) {
            return 'personal';
        }
        if (self::calendarTargetMinutes($dateYmd) > 0) {
            return 'calendar';
        }

        return 'none';
    }

    /**
     * Nur Stammdaten (ohne Kalender, ohne Fake-8h).
     *
     * @param array<string, mixed> $data EmployeeData-Array
     */
    public static function personalTargetMinutes(array $data): int
    {
        $minutes = (int) preg_replace('/\D/', '', (string) ($data['daily_work_minutes'] ?? ''));
        if ($minutes > 0) {
            return min(960, $minutes);
        }

        $workingHours = trim((string) ($data['working_hours'] ?? ''));
        if ($workingHours !== '' && preg_match('/(\d+)/', $workingHours, $m)) {
            return min(960, (int) $m[1] * 60);
        }

        return 0;
    }

    /**
     * Firmen-Öffnungszeiten: Wochentag in weekdays → end − start, sonst 0.
     */
    public static function calendarTargetMinutes(string $dateYmd): int
    {
        if (!Database::isConfigured() || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateYmd)) {
            return 0;
        }
        if (!class_exists('CalendarWorkingHoursRepository')) {
            return 0;
        }

        $dt = DateTimeImmutable::createFromFormat('Y-m-d', $dateYmd);
        if ($dt === false) {
            return 0;
        }

        $row = CalendarWorkingHoursRepository::getForDate($dateYmd);
        $weekdays = (string) ($row['weekdays'] ?? '');
        if ($weekdays === '' || !CalendarWorkingHoursRepository::isWorkingWeekday($dt, $weekdays)) {
            return 0;
        }

        $start = self::timeToMinutes((string) ($row['start_time'] ?? ''));
        $end = self::timeToMinutes((string) ($row['end_time'] ?? ''));
        if ($start === null || $end === null || $end <= $start) {
            return 0;
        }

        return min(960, $end - $start);
    }

    /**
     * @return array<string, mixed>
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

    private static function timeToMinutes(string $time): ?int
    {
        $time = trim($time);
        if ($time === '') {
            return null;
        }
        if (preg_match('/^(\d{1,2}):(\d{2})(?::(\d{2}))?$/', $time, $m)) {
            $h = (int) $m[1];
            $i = (int) $m[2];
            if ($h > 23 || $i > 59) {
                return null;
            }

            return $h * 60 + $i;
        }

        return null;
    }
}
