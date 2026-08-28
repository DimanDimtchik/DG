<?php
declare(strict_types=1);

/** Überstunden-Lots mit Verfalls- und Erinnerungsdatum (FIFO). */
final class OvertimeLotRepository
{
    public static function upsertLot(
        int $contactId,
        string $accruedDate,
        int $minutes,
        string $expiresAt,
        string $reminderDueAt,
    ): void {
        if (!Database::isConfigured() || $contactId < 1 || $minutes < 1) {
            return;
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $accruedDate)
            || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $expiresAt)
            || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $reminderDueAt)) {
            return;
        }
        MigrationRunner::runPending();

        $stmt = Database::pdo()->prepare(
            'INSERT INTO dg_time_overtime_lots
                (contact_id, accrued_date, minutes, minutes_remaining, expires_at, reminder_due_at)
             VALUES
                (:contact_id, :accrued_date, :minutes, :minutes_remaining, :expires_at, :reminder_due_at)
             ON DUPLICATE KEY UPDATE
                minutes = VALUES(minutes),
                minutes_remaining = VALUES(minutes_remaining),
                expires_at = VALUES(expires_at),
                reminder_due_at = VALUES(reminder_due_at)'
        );
        $stmt->execute([
            'contact_id' => $contactId,
            'accrued_date' => $accruedDate,
            'minutes' => $minutes,
            'minutes_remaining' => $minutes,
            'expires_at' => $expiresAt,
            'reminder_due_at' => $reminderDueAt,
        ]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function lotsDueForReminder(string $today): array
    {
        if (!Database::isConfigured() || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $today)) {
            return [];
        }
        MigrationRunner::runPending();

        $stmt = Database::pdo()->prepare(
            'SELECT l.*, c.display_name, c.company_name
             FROM dg_time_overtime_lots l
             INNER JOIN dg_contacts c ON c.id = l.contact_id
             WHERE l.minutes_remaining > 0
               AND l.reminder_due_at <= :today
               AND l.expires_at >= :today
               AND l.reminder_sent_at IS NULL
             ORDER BY l.expires_at ASC, c.display_name ASC, l.id ASC'
        );
        $stmt->execute(['today' => $today]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $out = [];
        foreach ($rows as $row) {
            if (is_array($row)) {
                $out[] = $row;
            }
        }

        return $out;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function pendingReminders(string $today): array
    {
        if (!Database::isConfigured() || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $today)) {
            return [];
        }
        MigrationRunner::runPending();

        $stmt = Database::pdo()->prepare(
            'SELECT l.*, c.display_name, c.company_name
             FROM dg_time_overtime_lots l
             INNER JOIN dg_contacts c ON c.id = l.contact_id
             WHERE l.minutes_remaining > 0
               AND l.reminder_due_at <= :today
               AND l.expires_at >= :today
             ORDER BY l.expires_at ASC, c.display_name ASC, l.id ASC'
        );
        $stmt->execute(['today' => $today]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $out = [];
        foreach ($rows as $row) {
            if (is_array($row)) {
                $out[] = self::mapRow($row);
            }
        }

        return $out;
    }

    public static function markReminderSent(int $lotId): void
    {
        if (!Database::isConfigured() || $lotId < 1) {
            return;
        }
        MigrationRunner::runPending();

        $stmt = Database::pdo()->prepare(
            'UPDATE dg_time_overtime_lots SET reminder_sent_at = NOW() WHERE id = :id AND reminder_sent_at IS NULL'
        );
        $stmt->execute(['id' => $lotId]);
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private static function mapRow(array $row): array
    {
        $label = trim((string) ($row['display_name'] ?? ''));
        if ($label === '') {
            $label = trim((string) ($row['company_name'] ?? ''));
        }
        if ($label === '') {
            $label = 'Mitarbeiter #' . (int) ($row['contact_id'] ?? 0);
        }

        $remaining = (int) ($row['minutes_remaining'] ?? 0);

        return [
            'id' => (int) ($row['id'] ?? 0),
            'contact_id' => (int) ($row['contact_id'] ?? 0),
            'label' => $label,
            'accrued_date' => (string) ($row['accrued_date'] ?? ''),
            'minutes_remaining' => $remaining,
            'remaining_display' => TimeClockService::formatMinutes($remaining),
            'expires_at' => (string) ($row['expires_at'] ?? ''),
            'expires_display' => self::formatGermanDate((string) ($row['expires_at'] ?? '')),
            'reminder_due_at' => (string) ($row['reminder_due_at'] ?? ''),
            'reminder_sent_at' => isset($row['reminder_sent_at']) && $row['reminder_sent_at'] !== null
                ? (string) $row['reminder_sent_at']
                : null,
            'message' => self::buildReminderMessage($label, $remaining, (string) ($row['expires_at'] ?? '')),
        ];
    }

    public static function buildReminderMessage(string $employeeLabel, int $minutesRemaining, string $expiresAt): string
    {
        $hours = TimeClockService::formatMinutes($minutesRemaining);
        $deadline = self::formatGermanDate($expiresAt);
        $monthLabel = self::germanMonthYear($expiresAt);

        return sprintf(
            '%s hat noch %s Überstunden, die bis %s (spätestens %s) abgebaut werden sollen.',
            $employeeLabel,
            $hours,
            $monthLabel,
            $deadline,
        );
    }

    private static function formatGermanDate(string $date): string
    {
        $ts = strtotime($date);

        return $ts !== false ? date('d.m.Y', $ts) : $date;
    }

    private static function germanMonthYear(string $date): string
    {
        $ts = strtotime($date);
        if ($ts === false) {
            return $date;
        }
        $months = [
            1 => 'Januar', 2 => 'Februar', 3 => 'März', 4 => 'April',
            5 => 'Mai', 6 => 'Juni', 7 => 'Juli', 8 => 'August',
            9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Dezember',
        ];
        $month = (int) date('n', $ts);

        return ($months[$month] ?? date('F', $ts)) . ' ' . date('Y', $ts);
    }
}
