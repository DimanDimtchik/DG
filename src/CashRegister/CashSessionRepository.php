<?php
declare(strict_types=1);

/** Kassensitzungen (K1e / K11). */
final class CashSessionRepository
{
    private const TABLE = 'dg_cash_sessions';

    public static function tableExists(): bool
    {
        return CashRegisterTable::tableExists(self::TABLE);
    }

    /** @return array<string, mixed>|null */
    public static function findById(int $id): ?array
    {
        return CashRegisterTable::findById(self::TABLE, $id);
    }

    /** @return array<string, mixed>|null */
    public static function findOpenByRegister(int $registerId): ?array
    {
        if ($registerId <= 0 || !self::tableExists()) {
            return null;
        }
        $stmt = Database::pdo()->prepare(
            "SELECT * FROM dg_cash_sessions WHERE register_id = :rid AND status = 'open' ORDER BY id DESC LIMIT 1"
        );
        $stmt->execute(['rid' => $registerId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : $row;
    }

    /**
     * Offene Sitzungen für mehrere Kassen (Listen-UI).
     *
     * @param list<int> $registerIds
     * @return array<int, array<string, mixed>> register_id => session row
     */
    public static function mapOpenByRegisterIds(array $registerIds): array
    {
        $ids = [];
        foreach ($registerIds as $id) {
            $id = (int) $id;
            if ($id > 0) {
                $ids[$id] = $id;
            }
        }
        if ($ids === [] || !self::tableExists()) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = Database::pdo()->prepare(
            "SELECT * FROM dg_cash_sessions
             WHERE status = 'open' AND register_id IN ({$placeholders})
             ORDER BY id ASC"
        );
        $stmt->execute(array_values($ids));
        $map = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            if ($row === false) {
                break;
            }
            $rid = (int) ($row['register_id'] ?? 0);
            if ($rid > 0) {
                $map[$rid] = $row;
            }
        }

        return $map;
    }

    /**
     * Sitzungen einer Kasse an einem Geschäftstag.
     *
     * @return list<array<string, mixed>>
     */
    public static function listByRegisterDate(int $registerId, string $businessDate): array
    {
        if ($registerId <= 0 || $businessDate === '' || !self::tableExists()) {
            return [];
        }
        $stmt = Database::pdo()->prepare(
            'SELECT * FROM dg_cash_sessions
             WHERE register_id = :rid AND business_date = :d
             ORDER BY id ASC'
        );
        $stmt->execute(['rid' => $registerId, 'd' => $businessDate]);
        $rows = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $rows[] = $row;
        }

        return $rows;
    }

    /** Summe Anfangsbestände aller Sitzungen (Kasse + Tag). */
    public static function sumOpeningBalance(int $registerId, string $businessDate): float
    {
        $sum = 0.0;
        foreach (self::listByRegisterDate($registerId, $businessDate) as $row) {
            $sum = round($sum + (float) ($row['opening_balance'] ?? 0), 2);
        }

        return $sum;
    }

    /** @param array<string, mixed> $data */
    public static function insert(array $data): int
    {
        return CashRegisterTable::insert(self::TABLE, [
            'register_id' => (int) ($data['register_id'] ?? 0),
            'business_date' => (string) ($data['business_date'] ?? date('Y-m-d')),
            'status' => trim((string) ($data['status'] ?? 'open')) ?: 'open',
            'opening_balance' => round((float) ($data['opening_balance'] ?? 0), 2),
            'closing_expected_balance' => $data['closing_expected_balance'] ?? null,
            'closing_counted_balance' => $data['closing_counted_balance'] ?? null,
            'closing_difference' => $data['closing_difference'] ?? null,
            'opened_by' => isset($data['opened_by']) ? (int) $data['opened_by'] : null,
            'closed_by' => isset($data['closed_by']) ? (int) $data['closed_by'] : null,
            'opened_at' => (string) ($data['opened_at'] ?? date('Y-m-d H:i:s')),
            'closed_at' => $data['closed_at'] ?? null,
            'notes' => trim((string) ($data['notes'] ?? '')),
        ]);
    }

    /** @param array<string, mixed> $data */
    public static function update(int $id, array $data): void
    {
        $fields = [];
        foreach ([
            'status', 'closing_expected_balance', 'closing_counted_balance', 'closing_difference',
            'closed_by', 'closed_at', 'notes', 'opening_balance', 'business_date',
        ] as $key) {
            if (array_key_exists($key, $data)) {
                $fields[$key] = $data[$key];
            }
        }
        CashRegisterTable::update(self::TABLE, $id, $fields);
    }
}
