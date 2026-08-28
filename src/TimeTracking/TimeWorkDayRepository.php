<?php
declare(strict_types=1);

/** Persistenz für aggregierte Arbeitstage (Soll/Ist). */
final class TimeWorkDayRepository
{
    /**
     * @param array{
     *   scheduled_minutes: int,
     *   worked_minutes: int,
     *   break_minutes: int,
     *   overtime_minutes: int,
     *   status?: string
     * } $data
     */
    public static function upsert(int $contactId, string $workDate, array $data): void
    {
        if (!Database::isConfigured() || $contactId < 1 || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $workDate)) {
            return;
        }
        MigrationRunner::runPending();

        $stmt = Database::pdo()->prepare(
            'INSERT INTO dg_time_work_days
                (contact_id, work_date, scheduled_minutes, worked_minutes, break_minutes, overtime_minutes, status, aggregated_at)
             VALUES
                (:contact_id, :work_date, :scheduled_minutes, :worked_minutes, :break_minutes, :overtime_minutes, :status, NOW())
             ON DUPLICATE KEY UPDATE
                scheduled_minutes = VALUES(scheduled_minutes),
                worked_minutes = VALUES(worked_minutes),
                break_minutes = VALUES(break_minutes),
                overtime_minutes = VALUES(overtime_minutes),
                status = VALUES(status),
                aggregated_at = NOW()'
        );
        $stmt->execute([
            'contact_id' => $contactId,
            'work_date' => $workDate,
            'scheduled_minutes' => max(0, (int) ($data['scheduled_minutes'] ?? 0)),
            'worked_minutes' => max(0, (int) ($data['worked_minutes'] ?? 0)),
            'break_minutes' => max(0, (int) ($data['break_minutes'] ?? 0)),
            'overtime_minutes' => max(0, (int) ($data['overtime_minutes'] ?? 0)),
            'status' => (string) ($data['status'] ?? 'closed'),
        ]);
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function find(int $contactId, string $workDate): ?array
    {
        if (!Database::isConfigured() || $contactId < 1 || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $workDate)) {
            return null;
        }
        MigrationRunner::runPending();

        $stmt = Database::pdo()->prepare(
            'SELECT * FROM dg_time_work_days WHERE contact_id = :contact_id AND work_date = :work_date LIMIT 1'
        );
        $stmt->execute(['contact_id' => $contactId, 'work_date' => $workDate]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }
}
