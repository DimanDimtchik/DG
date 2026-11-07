<?php
declare(strict_types=1);

/** TSE-Zuordnung je Kasse (K1j Stub). */
final class CashTseAssignmentRepository
{
    private const TABLE = 'dg_cash_tse_assignments';

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
    public static function findByRegisterId(int $registerId): ?array
    {
        if ($registerId <= 0 || !self::tableExists()) {
            return null;
        }
        $stmt = Database::pdo()->prepare('SELECT * FROM dg_cash_tse_assignments WHERE register_id = :rid LIMIT 1');
        $stmt->execute(['rid' => $registerId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : $row;
    }

    /** @param array<string, mixed> $data */
    public static function insert(array $data): int
    {
        return CashRegisterTable::insert(self::TABLE, self::normalize($data));
    }

    /** @param array<string, mixed> $data */
    public static function update(int $id, array $data): void
    {
        CashRegisterTable::update(self::TABLE, $id, self::normalize($data));
    }

    /**
     * Insert oder Update für eine Kasse (max. eine Zeile je register_id).
     *
     * @param array<string, mixed> $data muss register_id enthalten
     */
    public static function upsertForRegister(array $data): int
    {
        $registerId = (int) ($data['register_id'] ?? 0);
        if ($registerId <= 0) {
            throw new RuntimeException('Kasse für TSE-Zuordnung fehlt.');
        }
        $existing = self::findByRegisterId($registerId);
        if ($existing === null) {
            if (!array_key_exists('is_active', $data)) {
                $data['is_active'] = 1;
            }

            return self::insert($data);
        }
        $id = (int) $existing['id'];
        unset($data['register_id']);
        self::update($id, $data);

        return $id;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function listWithRegisters(): array
    {
        if (!self::tableExists() || !CashRegisterRepository::tableExists()) {
            return [];
        }
        $sql = 'SELECT r.id AS register_id, r.code AS register_code, r.name AS register_name,
                       r.is_active AS register_active,
                       l.code AS location_code, l.name AS location_name,
                       a.id AS assignment_id, a.provider, a.environment, a.tss_id, a.client_id,
                       a.is_active AS assignment_active,
                       a.last_state, a.last_checked_at, a.last_error, a.certificate_valid_until
                FROM dg_cash_registers r
                LEFT JOIN dg_cash_locations l ON l.id = r.location_id
                LEFT JOIN dg_cash_tse_assignments a ON a.register_id = r.id
                ORDER BY l.sort_order ASC, l.name ASC, r.sort_order ASC, r.id ASC';
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

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private static function normalize(array $data): array
    {
        $out = [];
        $map = [
            'register_id' => static fn ($v) => (int) $v,
            'provider' => static fn ($v) => trim((string) $v) ?: 'fiskaly',
            'environment' => static fn ($v) => trim((string) $v) ?: 'test',
            'tss_id' => static fn ($v) => trim((string) $v),
            'client_id' => static fn ($v) => trim((string) $v),
            'tss_serial_number' => static fn ($v) => trim((string) $v),
            'client_serial_number' => static fn ($v) => trim((string) $v),
            'certificate_valid_until' => static fn ($v) => $v !== null && $v !== '' ? (string) $v : null,
            'last_state' => static fn ($v) => trim((string) $v),
            'last_checked_at' => static fn ($v) => $v !== null && $v !== '' ? (string) $v : null,
            'last_error' => static fn ($v) => trim((string) $v),
            'notes' => static fn ($v) => trim((string) $v),
            'is_active' => static fn ($v) => !empty($v) ? 1 : 0,
        ];
        foreach ($map as $key => $cast) {
            if (array_key_exists($key, $data)) {
                $out[$key] = $cast($data[$key]);
            }
        }

        return $out;
    }
}
