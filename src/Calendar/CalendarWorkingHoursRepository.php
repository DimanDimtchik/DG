<?php
declare(strict_types=1);

/** Globale Öffnungs- und Buchungszeiten im Terminkalender (ab Stichtag). */
final class CalendarWorkingHoursRepository
{
    private const SLOT_STEP_MINUTES = 15;

    /**
     * Stellt Standarddaten in der Datenbank sicher.
     * @return void
     */
    public static function ensureSeeded(): void
    {
        if (!Database::isConfigured()) {
            return;
        }

        MigrationRunner::runPending();

        $count = (int) Database::pdo()->query('SELECT COUNT(*) FROM dg_calendar_working_hours')->fetchColumn();
        if ($count > 0) {
            return;
        }

        $stmt = Database::pdo()->prepare(
            'INSERT INTO dg_calendar_working_hours (start_date, start_time, end_time, weekdays)
             VALUES (:start_date, :start_time, :end_time, :weekdays)'
        );
        $stmt->execute([
            'start_date' => '1970-01-01',
            'start_time' => '09:00:00',
            'end_time' => '17:00:00',
            'weekdays' => '1,2,3,4,5',
        ]);
    }

    /**
     * Methode all.
     * @return array<string, mixed>
     */
    public static function all(): array
    {
        self::ensureSeeded();
        if (!Database::isConfigured()) {
            return [];
        }

        $rows = Database::pdo()->query(
            'SELECT id, start_date, start_time, end_time, weekdays, created_at
             FROM dg_calendar_working_hours
             ORDER BY start_date ASC'
        )->fetchAll(PDO::FETCH_ASSOC) ?: [];

        foreach ($rows as &$row) {
            $row['weekdays_label'] = self::formatWeekdays((string) ($row['weekdays'] ?? ''));
            $row['start_time_hm'] = self::formatTimeHm((string) ($row['start_time'] ?? ''));
            $row['end_time_hm'] = self::formatTimeHm((string) ($row['end_time'] ?? ''));
            $row['start_date_label'] = self::formatDate((string) ($row['start_date'] ?? ''));
        }
        unset($row);

        return $rows;
    }

    /**
     * Methode save.
     * @param array $input
     * @return void
     * @throws RuntimeException
     * @throws InvalidArgumentException
     */
    public static function save(array $input): void
    {
        if (!Database::isConfigured()) {
            throw new RuntimeException('Datenbank nicht konfiguriert.');
        }

        self::ensureSeeded();

        $startDate = trim((string) ($input['start_date'] ?? ''));
        $startTime = self::normalizeTime((string) ($input['start_time'] ?? ''));
        $endTime = self::normalizeTime((string) ($input['end_time'] ?? ''));
        $weekdays = self::sanitizeWeekdays($input['weekdays'] ?? []);

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $startDate)) {
            throw new InvalidArgumentException('Gültiges Ab-Datum erforderlich.');
        }
        if ($startTime === '' || $endTime === '') {
            throw new InvalidArgumentException('Start- und Endzeit sind Pflichtfelder.');
        }
        if ($startTime >= $endTime) {
            throw new InvalidArgumentException('Die Endzeit muss nach der Startzeit liegen.');
        }
        if ($weekdays === '') {
            throw new InvalidArgumentException('Bitte wählen Sie mindestens einen Wochentag aus.');
        }

        $pdo = Database::pdo();
        $existing = $pdo->prepare('SELECT id FROM dg_calendar_working_hours WHERE start_date = :start_date LIMIT 1');
        $existing->execute(['start_date' => $startDate]);
        $id = $existing->fetchColumn();

        if ($id !== false) {
            $stmt = $pdo->prepare(
                'UPDATE dg_calendar_working_hours
                 SET start_time = :start_time, end_time = :end_time, weekdays = :weekdays
                 WHERE start_date = :start_date'
            );
            $stmt->execute([
                'start_date' => $startDate,
                'start_time' => $startTime,
                'end_time' => $endTime,
                'weekdays' => $weekdays,
            ]);

            return;
        }

        $stmt = $pdo->prepare(
            'INSERT INTO dg_calendar_working_hours (start_date, start_time, end_time, weekdays)
             VALUES (:start_date, :start_time, :end_time, :weekdays)'
        );
        $stmt->execute([
            'start_date' => $startDate,
            'start_time' => $startTime,
            'end_time' => $endTime,
            'weekdays' => $weekdays,
        ]);
    }

    /**
     * Führt aus: delete.
     * @param int $id
     * @return void
     * @throws InvalidArgumentException
     * @throws RuntimeException
     */
    public static function delete(int $id): void
    {
        if ($id < 1) {
            throw new InvalidArgumentException('ID erforderlich.');
        }
        if (!Database::isConfigured()) {
            throw new RuntimeException('Datenbank nicht konfiguriert.');
        }

        Database::pdo()->prepare('DELETE FROM dg_calendar_working_hours WHERE id = :id')->execute(['id' => $id]);
    }

    /**
     * Liefert for date.
     * @param string $dateYmd
     * @return array<string, mixed>
     */
    public static function getForDate(string $dateYmd): array
    {
        self::ensureSeeded();

        $defaults = [
            'start_time' => '09:00:00',
            'end_time' => '17:00:00',
            'weekdays' => '1,2,3,4,5',
        ];

        if (!Database::isConfigured() || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateYmd)) {
            return $defaults;
        }

        $stmt = Database::pdo()->prepare(
            'SELECT start_time, end_time, weekdays
             FROM dg_calendar_working_hours
             WHERE start_date <= :start_date
             ORDER BY start_date DESC
             LIMIT 1'
        );
        $stmt->execute(['start_date' => $dateYmd]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return $defaults;
        }

        return [
            'start_time' => (string) $row['start_time'],
            'end_time' => (string) $row['end_time'],
            'weekdays' => (string) ($row['weekdays'] ?: '1,2,3,4,5'),
        ];
    }

    /**
     * Prüft: is working weekday.
     * @param DateTimeInterface $date
     * @param string $weekdaysCsv
     * @return bool
     */
    public static function isWorkingWeekday(DateTimeInterface $date, string $weekdaysCsv): bool
    {
        $weekday = (int) $date->format('N');
        $days = array_map('intval', explode(',', $weekdaysCsv));

        return in_array($weekday, $days, true);
    }

    /**
     * Methode slot step minutes.
     * @return int
     */
    public static function slotStepMinutes(): int
    {
        return self::SLOT_STEP_MINUTES;
    }

    /**
     * Liefert Uhrzeit-Optionen für Select-Felder.
     * @return list<string>
     */
    public static function timeOptions(): array
    {
        return CalendarStaffRepository::timeOptions();
    }

    /**
     * Liefert Wochentags-Bezeichnungen.
     * @return array<int, string>
     */
    public static function weekdayLabels(): array
    {
        return CalendarStaffRepository::weekdayLabels();
    }

    /**
     * Methode format weekdays.
     * @param string $weekdaysCsv
     * @return string
     */
    public static function formatWeekdays(string $weekdaysCsv): string
    {
        $labels = self::weekdayLabels();
        $days = array_map('intval', explode(',', $weekdaysCsv));
        $names = [];

        foreach ($days as $day) {
            if (isset($labels[$day])) {
                $names[] = $labels[$day];
            }
        }

        return $names !== [] ? implode(', ', $names) : '—';
    }

    /**
     * Methode format time hm.
     * @param string $time
     * @return string
     */
    public static function formatTimeHm(string $time): string
    {
        if (preg_match('/^(\d{2}):(\d{2})/', $time, $matches)) {
            return $matches[1] . ':' . $matches[2];
        }

        return $time;
    }

    /**
     * Methode format date.
     * @param string $dateYmd
     * @return string
     */
    public static function formatDate(string $dateYmd): string
    {
        try {
            return (new DateTimeImmutable($dateYmd))->format('d.m.Y');
        } catch (Throwable) {
            return $dateYmd;
        }
    }

    /**
     * Führt aus: sanitize weekdays.
     * @param mixed $weekdays
     * @return string
     */
    private static function sanitizeWeekdays(mixed $weekdays): string
    {
        if (!is_array($weekdays)) {
            return '';
        }

        $normalized = [];
        foreach ($weekdays as $day) {
            $day = (int) $day;
            if ($day >= 1 && $day <= 7) {
                $normalized[$day] = $day;
            }
        }

        if ($normalized === []) {
            return '';
        }

        ksort($normalized);

        return implode(',', array_values($normalized));
    }

    /**
     * Führt aus: normalize time.
     * @param string $value
     * @return string
     */
    private static function normalizeTime(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        if (preg_match('/^(\d{1,2}):(\d{2})(?::\d{2})?$/', $value, $matches)) {
            $hour = max(0, min(23, (int) $matches[1]));
            $minute = max(0, min(59, (int) $matches[2]));
            $total = $hour * 60 + $minute;
            $snapped = (int) round($total / self::SLOT_STEP_MINUTES) * self::SLOT_STEP_MINUTES;
            if ($snapped >= 24 * 60) {
                $snapped = 24 * 60 - self::SLOT_STEP_MINUTES;
            }

            return sprintf('%02d:%02d:00', intdiv($snapped, 60), $snapped % 60);
        }

        return '';
    }
}
