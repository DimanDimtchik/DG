<?php
declare(strict_types=1);

/** Tagesabschluss je Registerkasse (K1j Stub). */
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
