<?php
declare(strict_types=1);

/** Persistenz gesendeter Urlaubsplanungs-Erinnerungen. */
final class VacationPlanningReminderRepository
{
    public static function tableReady(): bool
    {
        if (!Database::isConfigured()) {
            return false;
        }
        try {
            $r = Database::pdo()->query("SHOW TABLES LIKE 'dg_time_vacation_planning_reminders'");

            return $r !== false && $r->fetchColumn() !== false;
        } catch (Throwable) {
            return false;
        }
    }

    public static function hasAutoReminder(int $contactId, int $year): bool
    {
        return self::latestSentAt($contactId, $year, 'auto') !== null;
    }

    public static function latestSentAt(int $contactId, int $year, ?string $channel = null): ?string
    {
        if (!self::tableReady() || $contactId < 1 || $year < 2000) {
            return null;
        }
        MigrationRunner::runPending();
        $sql = 'SELECT reminder_sent_at FROM dg_time_vacation_planning_reminders
                WHERE contact_id = :cid AND year = :y';
        $params = ['cid' => $contactId, 'y' => $year];
        if ($channel !== null && $channel !== '') {
            $sql .= ' AND channel = :ch';
            $params['ch'] = $channel;
        }
        $sql .= ' ORDER BY reminder_sent_at DESC LIMIT 1';
        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute($params);
        $v = $stmt->fetchColumn();

        return $v !== false && $v !== null && $v !== '' ? (string) $v : null;
    }

    public static function markSent(
        int $contactId,
        int $year,
        string $channel,
        float $plannedPercent,
        float $daysPlanned,
        float $daysBudget,
    ): void {
        if (!Database::isConfigured() || $contactId < 1 || $year < 2000) {
            return;
        }
        $channel = $channel === 'manual' ? 'manual' : 'auto';
        MigrationRunner::runPending();
        if (!self::tableReady()) {
            return;
        }

        $stmt = Database::pdo()->prepare(
            'INSERT INTO dg_time_vacation_planning_reminders
                (contact_id, year, channel, planned_percent, days_planned, days_budget, reminder_sent_at)
             VALUES
                (:cid, :y, :ch, :pct, :planned, :budget, NOW())
             ON DUPLICATE KEY UPDATE
                planned_percent = VALUES(planned_percent),
                days_planned = VALUES(days_planned),
                days_budget = VALUES(days_budget),
                reminder_sent_at = NOW()'
        );
        $stmt->execute([
            'cid' => $contactId,
            'y' => $year,
            'ch' => $channel,
            'pct' => round($plannedPercent, 1),
            'planned' => round($daysPlanned, 1),
            'budget' => round($daysBudget, 1),
        ]);
    }
}
