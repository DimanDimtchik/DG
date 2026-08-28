<?php
declare(strict_types=1);

/** Persistenz für gesendete ArbZG-Erinnerungen (6-Monats-Prüfung). */
final class ArbzgReminderRepository
{
    public static function hasReminder(int $contactId, string $periodTo): bool
    {
        if (!Database::isConfigured() || $contactId < 1 || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $periodTo)) {
            return false;
        }
        MigrationRunner::runPending();

        $stmt = Database::pdo()->prepare(
            'SELECT 1 FROM dg_time_arbzg_reminders
             WHERE contact_id = :contact_id AND period_to = :period_to LIMIT 1'
        );
        $stmt->execute(['contact_id' => $contactId, 'period_to' => $periodTo]);

        return $stmt->fetchColumn() !== false;
    }

    public static function markSent(
        int $contactId,
        string $periodFrom,
        string $periodTo,
        int $avgWeeklyMinutes,
    ): void {
        if (!Database::isConfigured() || $contactId < 1) {
            return;
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $periodFrom)
            || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $periodTo)) {
            return;
        }
        MigrationRunner::runPending();

        $stmt = Database::pdo()->prepare(
            'INSERT INTO dg_time_arbzg_reminders (contact_id, period_from, period_to, avg_weekly_minutes)
             VALUES (:contact_id, :period_from, :period_to, :avg_weekly_minutes)
             ON DUPLICATE KEY UPDATE
                avg_weekly_minutes = VALUES(avg_weekly_minutes),
                reminder_sent_at = NOW()'
        );
        $stmt->execute([
            'contact_id' => $contactId,
            'period_from' => $periodFrom,
            'period_to' => $periodTo,
            'avg_weekly_minutes' => max(0, $avgWeeklyMinutes),
        ]);
    }
}
