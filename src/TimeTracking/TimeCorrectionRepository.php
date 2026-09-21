<?php
declare(strict_types=1);

/** Persistenz Stempel-Korrekturen (Z2e) — Originale Events bleiben unverändert. */
final class TimeCorrectionRepository
{
    public static function tableReady(): bool
    {
        if (!Database::isConfigured()) {
            return false;
        }
        try {
            $r = Database::pdo()->query("SHOW TABLES LIKE 'dg_time_corrections'");

            return $r !== false && $r->fetchColumn() !== false;
        } catch (Throwable) {
            return false;
        }
    }

    public static function insert(
        int $contactId,
        string $workDate,
        int $deltaMinutes,
        string $reason,
        ?int $createdBy,
    ): int {
        MigrationRunner::runPending();
        $stmt = Database::pdo()->prepare(
            'INSERT INTO dg_time_corrections
                (contact_id, work_date, delta_worked_minutes, reason, created_by)
             VALUES
                (:contact_id, :work_date, :delta, :reason, :created_by)'
        );
        $stmt->execute([
            'contact_id' => $contactId,
            'work_date' => $workDate,
            'delta' => $deltaMinutes,
            'reason' => $reason,
            'created_by' => $createdBy,
        ]);

        return (int) Database::pdo()->lastInsertId();
    }

    public static function sumDeltaForDay(int $contactId, string $workDate): int
    {
        if (!self::tableReady() || $contactId < 1 || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $workDate)) {
            return 0;
        }
        $stmt = Database::pdo()->prepare(
            'SELECT COALESCE(SUM(delta_worked_minutes), 0)
             FROM dg_time_corrections
             WHERE contact_id = :cid AND work_date = :d'
        );
        $stmt->execute(['cid' => $contactId, 'd' => $workDate]);

        return (int) $stmt->fetchColumn();
    }

    public static function sumDeltaForRange(int $contactId, string $fromYmd, string $toYmd): int
    {
        if (
            !self::tableReady()
            || $contactId < 1
            || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $fromYmd)
            || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $toYmd)
        ) {
            return 0;
        }
        $stmt = Database::pdo()->prepare(
            'SELECT COALESCE(SUM(delta_worked_minutes), 0)
             FROM dg_time_corrections
             WHERE contact_id = :cid AND work_date >= :from AND work_date <= :to'
        );
        $stmt->execute(['cid' => $contactId, 'from' => $fromYmd, 'to' => $toYmd]);

        return (int) $stmt->fetchColumn();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function listForContact(int $contactId, int $limit = 50): array
    {
        if (!self::tableReady() || $contactId < 1) {
            return [];
        }
        $limit = max(1, min(200, $limit));
        $stmt = Database::pdo()->prepare(
            'SELECT * FROM dg_time_corrections
             WHERE contact_id = :cid
             ORDER BY created_at DESC, id DESC
             LIMIT ' . $limit
        );
        $stmt->execute(['cid' => $contactId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        return array_values(array_filter($rows, static fn ($r): bool => is_array($r)));
    }
}
