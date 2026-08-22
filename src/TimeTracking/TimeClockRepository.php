<?php
declare(strict_types=1);

/** Persistenz für Stempel-Events. */
final class TimeClockRepository
{
    public const EVENT_CLOCK_IN = 'clock_in';
    public const EVENT_CLOCK_OUT = 'clock_out';
    public const EVENT_BREAK_START = 'break_start';
    public const EVENT_BREAK_END = 'break_end';

    public const SOURCE_WEB = 'web';
    public const SOURCE_AUTO_BREAK = 'auto_break';
    public const SOURCE_AUTO_CLOSE = 'auto_close';

    /**
     * @return list<array<string, mixed>>
     */
    public static function eventsForContact(int $contactId, string $date): array
    {
        if (!Database::isConfigured() || $contactId < 1 || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return [];
        }
        MigrationRunner::runPending();

        $stmt = Database::pdo()->prepare(
            'SELECT * FROM dg_time_clock_events
             WHERE contact_id = :contact_id
               AND occurred_at >= :from AND occurred_at < :to
             ORDER BY occurred_at ASC, id ASC'
        );
        $from = $date . ' 00:00:00';
        $to = date('Y-m-d', strtotime($date . ' +1 day')) . ' 00:00:00';
        $stmt->execute(['contact_id' => $contactId, 'from' => $from, 'to' => $to]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $out = [];
        foreach ($rows as $row) {
            if (is_array($row)) {
                $out[] = self::mapRow($row);
            }
        }

        return $out;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function eventsForContactsOnDate(array $contactIds, string $date): array
    {
        if (!Database::isConfigured() || $contactIds === [] || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return [];
        }
        MigrationRunner::runPending();

        $ids = array_values(array_filter(array_map('intval', $contactIds), static fn (int $id): bool => $id > 0));
        if ($ids === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $from = $date . ' 00:00:00';
        $to = date('Y-m-d', strtotime($date . ' +1 day')) . ' 00:00:00';
        $stmt = Database::pdo()->prepare(
            "SELECT * FROM dg_time_clock_events
             WHERE contact_id IN ({$placeholders})
               AND occurred_at >= ? AND occurred_at < ?
             ORDER BY contact_id ASC, occurred_at ASC, id ASC"
        );
        $params = array_merge($ids, [$from, $to]);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $out = [];
        foreach ($rows as $row) {
            if (is_array($row)) {
                $out[] = self::mapRow($row);
            }
        }

        return $out;
    }

    public static function insert(
        int $contactId,
        string $eventType,
        string $occurredAt,
        string $source = self::SOURCE_WEB,
        ?string $note = null,
        ?int $createdBy = null,
    ): int {
        MigrationRunner::runPending();
        $stmt = Database::pdo()->prepare(
            'INSERT INTO dg_time_clock_events (contact_id, event_type, occurred_at, source, note, created_by)
             VALUES (:contact_id, :event_type, :occurred_at, :source, :note, :created_by)'
        );
        $stmt->execute([
            'contact_id' => $contactId,
            'event_type' => $eventType,
            'occurred_at' => $occurredAt,
            'source' => $source,
            'note' => $note !== null && trim($note) !== '' ? trim($note) : null,
            'created_by' => $createdBy,
        ]);

        return (int) Database::pdo()->lastInsertId();
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private static function mapRow(array $row): array
    {
        return [
            'id' => (int) ($row['id'] ?? 0),
            'contact_id' => (int) ($row['contact_id'] ?? 0),
            'event_type' => (string) ($row['event_type'] ?? ''),
            'occurred_at' => (string) ($row['occurred_at'] ?? ''),
            'source' => (string) ($row['source'] ?? ''),
            'note' => (string) ($row['note'] ?? ''),
            'created_by' => (int) ($row['created_by'] ?? 0),
        ];
    }
}
