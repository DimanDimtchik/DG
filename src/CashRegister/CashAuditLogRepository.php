<?php
declare(strict_types=1);

/** Append-only Registerkasse-Audit (K1i / K14a). Kein update/delete. */
final class CashAuditLogRepository
{
    private const TABLE = 'dg_cash_audit_log';

    public static function tableExists(): bool
    {
        return CashRegisterTable::tableExists(self::TABLE);
    }

    /** @return array<string, mixed>|null */
    public static function findById(int $id): ?array
    {
        return CashRegisterTable::findById(self::TABLE, $id);
    }

    /**
     * Neueste Einträge (optional nach Typ / Kasse).
     *
     * @return list<array<string, mixed>>
     */
    public static function listRecent(int $limit = 50, ?string $eventType = null, ?int $registerId = null): array
    {
        if (!self::tableExists()) {
            return [];
        }
        $limit = max(1, min(200, $limit));
        $sql = 'SELECT * FROM dg_cash_audit_log';
        $where = [];
        $params = [];
        if ($eventType !== null && trim($eventType) !== '') {
            $where[] = 'event_type = :etype';
            $params['etype'] = trim($eventType);
        }
        if ($registerId !== null && $registerId > 0) {
            $where[] = 'register_id = :rid';
            $params['rid'] = $registerId;
        }
        if ($where !== []) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= ' ORDER BY id DESC LIMIT ' . $limit;
        $stmt = $params === []
            ? Database::pdo()->query($sql)
            : Database::pdo()->prepare($sql);
        if ($stmt === false) {
            return [];
        }
        if ($params !== []) {
            $stmt->execute($params);
        }
        $rows = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $rows[] = $row;
        }

        return $rows;
    }

    /** @param array<string, mixed> $data */
    public static function insert(array $data): int
    {
        $eventType = trim((string) ($data['event_type'] ?? ''));
        if ($eventType === '') {
            throw new RuntimeException('Audit event_type fehlt.');
        }

        return CashRegisterTable::insert(self::TABLE, [
            'location_id' => isset($data['location_id']) ? (int) $data['location_id'] : null,
            'register_id' => isset($data['register_id']) ? (int) $data['register_id'] : null,
            'session_id' => isset($data['session_id']) ? (int) $data['session_id'] : null,
            'receipt_id' => isset($data['receipt_id']) ? (int) $data['receipt_id'] : null,
            'user_id' => isset($data['user_id']) ? (int) $data['user_id'] : null,
            'event_type' => $eventType,
            'severity' => trim((string) ($data['severity'] ?? 'info')) ?: 'info',
            'message' => mb_substr(trim((string) ($data['message'] ?? '')), 0, 500),
            'details_json' => $data['details_json'] ?? null,
            'ip' => trim((string) ($data['ip'] ?? '')),
        ]);
    }
}
