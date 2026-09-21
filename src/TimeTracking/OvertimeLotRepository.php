<?php
declare(strict_types=1);

/** Überstunden-Lots mit Ausgleichsfrist (Stunden bleiben dauerhaft im Konto). */
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
     * Lots mit fälliger Erinnerung (noch nicht versendet). Fristüberschreitung löscht nichts.
     *
     * @return list<array<string, mixed>>
     */
    public static function lotsDueForReminder(string $today): array
    {
        if (!Database::isConfigured() || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $today)) {
            return [];
        }
        MigrationRunner::runPending();

        $stmt = Database::pdo()->prepare(
            'SELECT l.*, c.display_name, c.company_name, c.email AS contact_email, c.email_2 AS contact_email_2
             FROM dg_time_overtime_lots l
             INNER JOIN dg_contacts c ON c.id = l.contact_id
             WHERE l.minutes_remaining > 0
               AND l.reminder_due_at <= :today
               AND l.reminder_sent_at IS NULL
             ORDER BY l.expires_at ASC, c.display_name ASC, l.id ASC'
        );
        $stmt->execute(['today' => $today]);

        return self::fetchRows($stmt);
    }

    /**
     * @return array{due: list<array<string, mixed>>, overdue: list<array<string, mixed>>}
     */
    public static function pendingRemindersGrouped(string $today): array
    {
        if (!Database::isConfigured() || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $today)) {
            return ['due' => [], 'overdue' => []];
        }
        MigrationRunner::runPending();

        $stmt = Database::pdo()->prepare(
            'SELECT l.*, c.display_name, c.company_name
             FROM dg_time_overtime_lots l
             INNER JOIN dg_contacts c ON c.id = l.contact_id
             WHERE l.minutes_remaining > 0
               AND l.reminder_due_at <= :today
             ORDER BY l.expires_at ASC, c.display_name ASC, l.id ASC'
        );
        $stmt->execute(['today' => $today]);

        $due = [];
        $overdue = [];
        foreach (self::fetchRows($stmt) as $row) {
            $mapped = self::mapRow($row, $today);
            if ($mapped === null) {
                continue;
            }
            if (!empty($mapped['is_overdue'])) {
                $overdue[] = $mapped;
            } else {
                $due[] = $mapped;
            }
        }

        return ['due' => $due, 'overdue' => $overdue];
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

    public static function sumRemainingMinutes(int $contactId): int
    {
        if (!Database::isConfigured() || $contactId < 1) {
            return 0;
        }
        MigrationRunner::runPending();
        $stmt = Database::pdo()->prepare(
            'SELECT COALESCE(SUM(minutes_remaining), 0) FROM dg_time_overtime_lots
             WHERE contact_id = :cid AND minutes_remaining > 0'
        );
        $stmt->execute(['cid' => $contactId]);

        return max(0, (int) $stmt->fetchColumn());
    }

    /**
     * Fehlende Lots aus aggregierten overtime_minutes nachziehen (ohne Restsaldo zu überschreiben).
     */
    public static function syncAccrualsFromWorkDays(int $contactId): void
    {
        if (!Database::isConfigured() || $contactId < 1) {
            return;
        }
        MigrationRunner::runPending();
        $months = max(1, (int) (TimeTrackingSettings::config()['overtime_compensation_months'] ?? 6));
        $stmt = Database::pdo()->prepare(
            'SELECT work_date, overtime_minutes FROM dg_time_work_days
             WHERE contact_id = :cid AND overtime_minutes > 0
             ORDER BY work_date ASC'
        );
        $stmt->execute(['cid' => $contactId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $date = (string) ($row['work_date'] ?? '');
            $mins = (int) ($row['overtime_minutes'] ?? 0);
            if ($date === '' || $mins < 1) {
                continue;
            }
            $exists = Database::pdo()->prepare(
                'SELECT id FROM dg_time_overtime_lots WHERE contact_id = :cid AND accrued_date = :d LIMIT 1'
            );
            $exists->execute(['cid' => $contactId, 'd' => $date]);
            if ($exists->fetchColumn() !== false) {
                continue;
            }
            $expires = OvertimeDateRules::addMonths($date, $months);
            $reminder = OvertimeDateRules::addMonths($date, max(1, $months - 1));
            self::upsertLot($contactId, $date, $mins, $expires, $reminder);
        }
    }

    /**
     * FIFO-Abbau nach expires_at ASC.
     */
    public static function reduceMinutesFifo(int $contactId, int $minutes): int
    {
        if (!Database::isConfigured() || $contactId < 1 || $minutes < 1) {
            return 0;
        }
        MigrationRunner::runPending();
        $pdo = Database::pdo();
        $stmt = $pdo->prepare(
            'SELECT id, minutes_remaining FROM dg_time_overtime_lots
             WHERE contact_id = :cid AND minutes_remaining > 0
             ORDER BY expires_at ASC, accrued_date ASC, id ASC'
        );
        $left = $minutes;
        $reduced = 0;
        $pdo->beginTransaction();
        try {
            $stmt->execute(['cid' => $contactId]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
            $upd = $pdo->prepare(
                'UPDATE dg_time_overtime_lots SET minutes_remaining = :rem WHERE id = :id'
            );
            foreach ($rows as $row) {
                if (!is_array($row) || $left < 1) {
                    break;
                }
                $id = (int) ($row['id'] ?? 0);
                $rem = max(0, (int) ($row['minutes_remaining'] ?? 0));
                if ($id < 1 || $rem < 1) {
                    continue;
                }
                $take = min($rem, $left);
                $upd->execute(['rem' => $rem - $take, 'id' => $id]);
                $left -= $take;
                $reduced += $take;
            }
            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }

        return $reduced;
    }

    public static function insertReductionAudit(int $contactId, int $minutes, string $reason, ?int $createdBy): void
    {
        if (!Database::isConfigured() || $contactId < 1 || $minutes < 1) {
            return;
        }
        MigrationRunner::runPending();
        try {
            $chk = Database::pdo()->query("SHOW TABLES LIKE 'dg_time_overtime_reductions'");
            if ($chk === false || $chk->fetchColumn() === false) {
                return;
            }
        } catch (Throwable) {
            return;
        }
        $stmt = Database::pdo()->prepare(
            'INSERT INTO dg_time_overtime_reductions (contact_id, minutes, reason, created_by)
             VALUES (:cid, :minutes, :reason, :created_by)'
        );
        $stmt->execute([
            'cid' => $contactId,
            'minutes' => $minutes,
            'reason' => $reason,
            'created_by' => $createdBy,
        ]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function listOpenLots(int $contactId, int $limit = 40): array
    {
        if (!Database::isConfigured() || $contactId < 1) {
            return [];
        }
        MigrationRunner::runPending();
        $limit = max(1, min(100, $limit));
        $stmt = Database::pdo()->prepare(
            'SELECT * FROM dg_time_overtime_lots
             WHERE contact_id = :cid AND minutes_remaining > 0
             ORDER BY expires_at ASC, accrued_date ASC
             LIMIT ' . $limit
        );
        $stmt->execute(['cid' => $contactId]);
        $today = date('Y-m-d');
        $out = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            if (!is_array($row)) {
                continue;
            }
            $mapped = self::mapRow($row, $today);
            if ($mapped !== null) {
                $out[] = $mapped;
            }
        }

        return $out;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function listReductions(int $contactId, int $limit = 30): array
    {
        if (!Database::isConfigured() || $contactId < 1) {
            return [];
        }
        try {
            $chk = Database::pdo()->query("SHOW TABLES LIKE 'dg_time_overtime_reductions'");
            if ($chk === false || $chk->fetchColumn() === false) {
                return [];
            }
        } catch (Throwable) {
            return [];
        }
        $limit = max(1, min(100, $limit));
        $stmt = Database::pdo()->prepare(
            'SELECT * FROM dg_time_overtime_reductions
             WHERE contact_id = :cid
             ORDER BY created_at DESC, id DESC
             LIMIT ' . $limit
        );
        $stmt->execute(['cid' => $contactId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        return array_values(array_filter($rows, static fn ($r): bool => is_array($r)));
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>|null
     */
    public static function mapRowPublic(array $row, ?string $today = null): ?array
    {
        return self::mapRow($row, $today ?? date('Y-m-d'));
    }

    public static function buildReminderMessage(string $employeeLabel, int $minutesRemaining, string $expiresAt, bool $isOverdue = false): string
    {
        $hours = TimeClockService::formatMinutes($minutesRemaining);
        $deadline = self::formatGermanDate($expiresAt);
        $monthLabel = self::germanMonthYear($expiresAt);

        if ($isOverdue) {
            return sprintf(
                '%s hat noch %s Überstunden (Ausgleichsfrist %s überschritten — Stunden bleiben offen und sind weiter abzubauen).',
                $employeeLabel,
                $hours,
                $deadline,
            );
        }

        return sprintf(
            '%s hat noch %s Überstunden, die bis %s (spätestens %s) abgebaut werden sollen.',
            $employeeLabel,
            $hours,
            $monthLabel,
            $deadline,
        );
    }

    public static function buildEmployeeReminderMessage(int $minutesRemaining, string $expiresAt, bool $isOverdue = false): string
    {
        $hours = TimeClockService::formatMinutes($minutesRemaining);
        $deadline = self::formatGermanDate($expiresAt);
        $monthLabel = self::germanMonthYear($expiresAt);

        if ($isOverdue) {
            return sprintf(
                'Sie haben noch %s Überstunden (Ausgleichsfrist %s überschritten). Die Stunden bleiben in Ihrem Konto und sind weiter abzubauen.',
                $hours,
                $deadline,
            );
        }

        return sprintf(
            'Sie haben noch %s Überstunden, die bis %s (spätestens %s) abgebaut werden sollen.',
            $hours,
            $monthLabel,
            $deadline,
        );
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>|null
     */
    private static function mapRow(array $row, string $today): ?array
    {
        $label = trim((string) ($row['display_name'] ?? ''));
        if ($label === '') {
            $label = trim((string) ($row['company_name'] ?? ''));
        }
        if ($label === '') {
            $label = 'Mitarbeiter #' . (int) ($row['contact_id'] ?? 0);
        }

        $remaining = (int) ($row['minutes_remaining'] ?? 0);
        if ($remaining < 1) {
            return null;
        }

        $expiresAt = (string) ($row['expires_at'] ?? '');
        $isOverdue = $expiresAt !== '' && $expiresAt < $today;

        return [
            'id' => (int) ($row['id'] ?? 0),
            'contact_id' => (int) ($row['contact_id'] ?? 0),
            'label' => $label,
            'accrued_date' => (string) ($row['accrued_date'] ?? ''),
            'minutes_remaining' => $remaining,
            'remaining_display' => TimeClockService::formatMinutes($remaining),
            'expires_at' => $expiresAt,
            'expires_display' => self::formatGermanDate($expiresAt),
            'reminder_due_at' => (string) ($row['reminder_due_at'] ?? ''),
            'reminder_sent_at' => isset($row['reminder_sent_at']) && $row['reminder_sent_at'] !== null
                ? (string) $row['reminder_sent_at']
                : null,
            'is_overdue' => $isOverdue,
            'message' => self::buildReminderMessage($label, $remaining, $expiresAt, $isOverdue),
            'employee_message' => self::buildEmployeeReminderMessage($remaining, $expiresAt, $isOverdue),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function fetchRows(PDOStatement $stmt): array
    {
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $out = [];
        foreach ($rows as $row) {
            if (is_array($row)) {
                $out[] = $row;
            }
        }

        return $out;
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
