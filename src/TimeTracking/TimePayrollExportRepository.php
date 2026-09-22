<?php
declare(strict_types=1);

/** Zeiterfassung Z6b: Protokoll Lohn-Exporte. */
final class TimePayrollExportRepository
{
    public static function tableReady(): bool
    {
        if (!Database::isConfigured()) {
            return false;
        }
        try {
            $r = Database::pdo()->query("SHOW TABLES LIKE 'dg_time_payroll_exports'");

            return $r !== false && $r->fetchColumn() !== false;
        } catch (Throwable) {
            return false;
        }
    }

    public static function insert(
        string $yearMonth,
        string $format,
        string $filename,
        int $rowCount,
        ?int $createdBy,
    ): int {
        MigrationRunner::runPending();
        if (!self::tableReady()) {
            throw new RuntimeException('Lohn-Export-Protokoll fehlt — Migration 094 ausführen.');
        }
        if (!preg_match('/^\d{4}-\d{2}$/', $yearMonth)) {
            throw new InvalidArgumentException('Monat JJJJ-MM erforderlich.');
        }
        $format = preg_replace('/[^a-z0-9_]/', '', strtolower($format)) ?? 'csv';
        if ($format === '') {
            $format = 'csv';
        }
        if (function_exists('mb_substr')) {
            $filename = mb_substr(trim($filename), 0, 255);
        } else {
            $filename = substr(trim($filename), 0, 255);
        }

        $stmt = Database::pdo()->prepare(
            'INSERT INTO dg_time_payroll_exports
                (`year_month`, `format`, filename, row_count, created_by)
             VALUES
                (:ym, :fmt, :fn, :rc, :by)'
        );
        $stmt->execute([
            'ym' => $yearMonth,
            'fmt' => $format,
            'fn' => $filename,
            'rc' => max(0, $rowCount),
            'by' => ($createdBy !== null && $createdBy > 0) ? $createdBy : null,
        ]);

        return (int) Database::pdo()->lastInsertId();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function listRecent(int $limit = 50): array
    {
        if (!self::tableReady()) {
            return [];
        }
        MigrationRunner::runPending();
        $limit = max(1, min(200, $limit));
        $rows = Database::pdo()->query(
            'SELECT * FROM dg_time_payroll_exports
             ORDER BY created_at DESC, id DESC
             LIMIT ' . $limit
        )->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $out = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $out[] = [
                'id' => (int) ($row['id'] ?? 0),
                'year_month' => (string) ($row['year_month'] ?? ''),
                'format' => (string) ($row['format'] ?? ''),
                'filename' => (string) ($row['filename'] ?? ''),
                'row_count' => (int) ($row['row_count'] ?? 0),
                'created_by' => isset($row['created_by']) && $row['created_by'] !== null
                    ? (int) $row['created_by']
                    : null,
                'created_at' => (string) ($row['created_at'] ?? ''),
            ];
        }

        return $out;
    }
}
