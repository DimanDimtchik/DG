<?php
declare(strict_types=1);

/** Stammdaten Betriebsstätten / Filialen (K1j Stub). */
final class CashLocationRepository
{
    private const TABLE = 'dg_cash_locations';

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
        $stmt = Database::pdo()->prepare('SELECT * FROM dg_cash_locations WHERE code = :code LIMIT 1');
        $stmt->execute(['code' => $code]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : $row;
    }

    /** @return list<array<string, mixed>> */
    public static function listAll(bool $activeOnly = false): array
    {
        return CashRegisterTable::listAll(self::TABLE, $activeOnly);
    }

    /**
     * @param 'all'|'active'|'inactive' $status
     * @return list<array<string, mixed>>
     */
    public static function listFiltered(string $status = 'all'): array
    {
        if ($status === 'active') {
            return self::listAll(true);
        }
        $rows = self::listAll(false);
        if ($status === 'inactive') {
            return array_values(array_filter(
                $rows,
                static fn (array $row): bool => empty($row['is_active'])
            ));
        }

        return $rows;
    }

    /** @param array<string, mixed> $data */
    public static function insert(array $data): int
    {
        return CashRegisterTable::insert(self::TABLE, self::normalize($data, false));
    }

    /** @param array<string, mixed> $data */
    public static function update(int $id, array $data): void
    {
        CashRegisterTable::update(self::TABLE, $id, self::normalize($data, true));
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private static function normalize(array $data, bool $forUpdate): array
    {
        $out = [
            'code' => trim((string) ($data['code'] ?? '')),
            'name' => trim((string) ($data['name'] ?? '')),
            'street' => trim((string) ($data['street'] ?? '')),
            'zip' => trim((string) ($data['zip'] ?? '')),
            'city' => trim((string) ($data['city'] ?? '')),
            'country' => strtoupper(substr(trim((string) ($data['country'] ?? 'DE')), 0, 2)) ?: 'DE',
            'notes' => trim((string) ($data['notes'] ?? '')),
            'is_active' => array_key_exists('is_active', $data) ? (!empty($data['is_active']) ? 1 : 0) : 1,
            'sort_order' => (int) ($data['sort_order'] ?? 0),
        ];
        if ($forUpdate) {
            foreach (array_keys($out) as $key) {
                if (!array_key_exists($key, $data)) {
                    unset($out[$key]);
                }
            }
        }

        return $out;
    }
}
