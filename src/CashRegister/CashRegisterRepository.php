<?php
declare(strict_types=1);

/** Stammdaten Kassen / Terminals (K1j Stub). */
final class CashRegisterRepository
{
    private const TABLE = 'dg_cash_registers';

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
    public static function findByCode(string $code): ?array
    {
        $code = trim($code);
        if ($code === '' || !self::tableExists()) {
            return null;
        }
        $stmt = Database::pdo()->prepare('SELECT * FROM dg_cash_registers WHERE code = :code LIMIT 1');
        $stmt->execute(['code' => $code]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : $row;
    }

    /** @return list<array<string, mixed>> */
    public static function listByLocation(int $locationId, bool $activeOnly = false): array
    {
        if ($locationId <= 0 || !self::tableExists()) {
            return [];
        }
        $sql = 'SELECT r.*, l.code AS location_code, l.name AS location_name
                FROM dg_cash_registers r
                LEFT JOIN dg_cash_locations l ON l.id = r.location_id
                WHERE r.location_id = :lid';
        if ($activeOnly) {
            $sql .= ' AND r.is_active = 1';
        }
        $sql .= ' ORDER BY r.sort_order ASC, r.id ASC';
        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute(['lid' => $locationId]);
        $rows = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $rows[] = $row;
        }

        return $rows;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function listAll(?int $locationId = null, bool $activeOnly = false): array
    {
        if (!self::tableExists()) {
            return [];
        }
        if ($locationId !== null && $locationId > 0) {
            return self::listByLocation($locationId, $activeOnly);
        }
        $sql = 'SELECT r.*, l.code AS location_code, l.name AS location_name
                FROM dg_cash_registers r
                LEFT JOIN dg_cash_locations l ON l.id = r.location_id';
        if ($activeOnly) {
            $sql .= ' WHERE r.is_active = 1';
        }
        $sql .= ' ORDER BY l.sort_order ASC, l.name ASC, r.sort_order ASC, r.id ASC';
        $stmt = Database::pdo()->query($sql);
        if ($stmt === false) {
            return [];
        }
        $rows = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $rows[] = $row;
        }

        return $rows;
    }

    /** @return array<string, string> */
    public static function typeLabels(): array
    {
        return [
            'store' => 'Ladenkasse',
            'mobile' => 'Mobile Kasse',
            'counter' => 'Schalter',
        ];
    }

    /** @param array<string, mixed> $data */
    public static function insert(array $data): int
    {
        return CashRegisterTable::insert(self::TABLE, [
            'location_id' => (int) ($data['location_id'] ?? 0),
            'code' => trim((string) ($data['code'] ?? '')),
            'name' => trim((string) ($data['name'] ?? '')),
            'register_type' => trim((string) ($data['register_type'] ?? 'store')) ?: 'store',
            'next_receipt_number' => max(1, (int) ($data['next_receipt_number'] ?? 1)),
            'receipt_number_prefix' => trim((string) ($data['receipt_number_prefix'] ?? '')),
            'notes' => trim((string) ($data['notes'] ?? '')),
            'is_active' => array_key_exists('is_active', $data) ? (!empty($data['is_active']) ? 1 : 0) : 1,
            'sort_order' => (int) ($data['sort_order'] ?? 0),
        ]);
    }

    /** @param array<string, mixed> $data */
    public static function update(int $id, array $data): void
    {
        $fields = [];
        foreach (['location_id', 'code', 'name', 'register_type', 'next_receipt_number', 'receipt_number_prefix', 'notes', 'is_active', 'sort_order'] as $key) {
            if (!array_key_exists($key, $data)) {
                continue;
            }
            $fields[$key] = $data[$key];
        }
        if (isset($fields['code'])) {
            $fields['code'] = trim((string) $fields['code']);
        }
        if (isset($fields['name'])) {
            $fields['name'] = trim((string) $fields['name']);
        }
        if (isset($fields['is_active'])) {
            $fields['is_active'] = !empty($fields['is_active']) ? 1 : 0;
        }
        if (isset($fields['location_id'])) {
            $fields['location_id'] = (int) $fields['location_id'];
        }
        if (isset($fields['next_receipt_number'])) {
            $fields['next_receipt_number'] = max(1, (int) $fields['next_receipt_number']);
        }
        if (isset($fields['sort_order'])) {
            $fields['sort_order'] = (int) $fields['sort_order'];
        }
        CashRegisterTable::update(self::TABLE, $id, $fields);
    }

    public static function formatReceiptNumber(string $prefix, int $number): string
    {
        $prefix = trim($prefix);

        return $prefix !== '' ? $prefix . $number : (string) $number;
    }

    /**
     * Vergibt die nächste Bonnummer atomar und erhöht den Zähler (K5d).
     *
     * @return array{number: int, display: string, prefix: string}
     */
    public static function allocateNextReceiptNumber(int $registerId): array
    {
        if ($registerId <= 0 || !self::tableExists()) {
            throw new RuntimeException('Kasse für Nummernvergabe nicht gefunden.');
        }
        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare(
                'SELECT next_receipt_number, receipt_number_prefix
                 FROM dg_cash_registers WHERE id = :id FOR UPDATE'
            );
            $stmt->execute(['id' => $registerId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row === false) {
                throw new RuntimeException('Kasse für Nummernvergabe nicht gefunden.');
            }
            $number = max(1, (int) ($row['next_receipt_number'] ?? 1));
            $prefix = trim((string) ($row['receipt_number_prefix'] ?? ''));
            $pdo->prepare(
                'UPDATE dg_cash_registers SET next_receipt_number = :n WHERE id = :id'
            )->execute(['n' => $number + 1, 'id' => $registerId]);
            $pdo->commit();

            return [
                'number' => $number,
                'display' => self::formatReceiptNumber($prefix, $number),
                'prefix' => $prefix,
            ];
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }
}
