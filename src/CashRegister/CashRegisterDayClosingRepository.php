<?php
declare(strict_types=1);

/** Tagesabschluss je Registerkasse (K1h / K12). */
final class CashRegisterDayClosingRepository
{
    private const TABLE = 'dg_cash_register_day_closings';

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
    public static function findByRegisterDate(int $registerId, string $businessDate): ?array
    {
        if ($registerId <= 0 || $businessDate === '' || !self::tableExists()) {
            return null;
        }
        $stmt = Database::pdo()->prepare(
            'SELECT * FROM dg_cash_register_day_closings
             WHERE register_id = :rid AND business_date = :d LIMIT 1'
        );
        $stmt->execute(['rid' => $registerId, 'd' => $businessDate]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : $row;
    }

    /**
     * Abschlüsse einer Filiale an einem Geschäftstag (K12c).
     *
     * @return list<array<string, mixed>>
     */
    public static function listByLocationDate(int $locationId, string $businessDate): array
    {
        if ($locationId <= 0 || $businessDate === '' || !self::tableExists()) {
            return [];
        }
        $stmt = Database::pdo()->prepare(
            'SELECT c.*,
                    reg.code AS register_code, reg.name AS register_name,
                    l.code AS location_code, l.name AS location_name
             FROM dg_cash_register_day_closings c
             LEFT JOIN dg_cash_registers reg ON reg.id = c.register_id
             LEFT JOIN dg_cash_locations l ON l.id = c.location_id
             WHERE c.location_id = :lid AND c.business_date = :d
             ORDER BY reg.sort_order ASC, reg.id ASC'
        );
        $stmt->execute(['lid' => $locationId, 'd' => $businessDate]);
        $rows = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $rows[] = $row;
        }

        return $rows;
    }

    /**
     * Neueste Abschlüsse (Listen-UI).
     *
     * @return list<array<string, mixed>>
     */
    public static function listRecent(int $limit = 30, ?int $registerId = null, ?int $locationId = null): array
    {
        if (!self::tableExists()) {
            return [];
        }
        $limit = max(1, min(100, $limit));
        $sql = 'SELECT c.*,
                       reg.code AS register_code, reg.name AS register_name,
                       l.code AS location_code, l.name AS location_name
                FROM dg_cash_register_day_closings c
                LEFT JOIN dg_cash_registers reg ON reg.id = c.register_id
                LEFT JOIN dg_cash_locations l ON l.id = c.location_id';
        $where = [];
        $params = [];
        if ($registerId !== null && $registerId > 0) {
            $where[] = 'c.register_id = :rid';
            $params['rid'] = $registerId;
        }
        if ($locationId !== null && $locationId > 0) {
            $where[] = 'c.location_id = :lid';
            $params['lid'] = $locationId;
        }
        if ($where !== []) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= ' ORDER BY c.business_date DESC, c.id DESC LIMIT ' . $limit;
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
        return CashRegisterTable::insert(self::TABLE, [
            'location_id' => (int) ($data['location_id'] ?? 0),
            'register_id' => (int) ($data['register_id'] ?? 0),
            'business_date' => (string) ($data['business_date'] ?? date('Y-m-d')),
            'opening_balance' => round((float) ($data['opening_balance'] ?? 0), 2),
            'total_cash' => round((float) ($data['total_cash'] ?? 0), 2),
            'total_ec' => round((float) ($data['total_ec'] ?? 0), 2),
            'total_card' => round((float) ($data['total_card'] ?? 0), 2),
            'total_voucher' => round((float) ($data['total_voucher'] ?? 0), 2),
            'total_gross' => round((float) ($data['total_gross'] ?? 0), 2),
            'receipt_count' => (int) ($data['receipt_count'] ?? 0),
            'expected_balance' => round((float) ($data['expected_balance'] ?? 0), 2),
            'counted_balance' => round((float) ($data['counted_balance'] ?? 0), 2),
            'difference' => round((float) ($data['difference'] ?? 0), 2),
            'summary_json' => $data['summary_json'] ?? null,
            'notes' => trim((string) ($data['notes'] ?? '')),
            'closed_by' => isset($data['closed_by']) ? (int) $data['closed_by'] : null,
            'closed_at' => (string) ($data['closed_at'] ?? date('Y-m-d H:i:s')),
        ]);
    }
}
