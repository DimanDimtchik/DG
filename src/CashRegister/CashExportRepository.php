<?php
declare(strict_types=1);

/** Export-Job-Metadaten Registerkasse (K1j Stub). */
final class CashExportRepository
{
    private const TABLE = 'dg_cash_exports';

    public static function tableExists(): bool
    {
        return CashRegisterTable::tableExists(self::TABLE);
    }

    /** @return array<string, mixed>|null */
    public static function findById(int $id): ?array
    {
        return CashRegisterTable::findById(self::TABLE, $id);
    }

    /** @param array<string, mixed> $data */
    public static function insert(array $data): int
    {
        return CashRegisterTable::insert(self::TABLE, [
            'location_id' => isset($data['location_id']) ? (int) $data['location_id'] : null,
            'register_id' => isset($data['register_id']) ? (int) $data['register_id'] : null,
            'export_type' => trim((string) ($data['export_type'] ?? '')),
            'date_from' => $data['date_from'] ?? null,
            'date_to' => $data['date_to'] ?? null,
            'status' => trim((string) ($data['status'] ?? 'pending')) ?: 'pending',
            'file_path' => trim((string) ($data['file_path'] ?? '')),
            'file_checksum' => trim((string) ($data['file_checksum'] ?? '')),
            'created_by' => isset($data['created_by']) ? (int) $data['created_by'] : null,
            'error_message' => trim((string) ($data['error_message'] ?? '')),
            'meta_json' => $data['meta_json'] ?? null,
            'finished_at' => $data['finished_at'] ?? null,
        ]);
    }

    /** @param array<string, mixed> $data */
    public static function update(int $id, array $data): void
    {
        CashRegisterTable::update(self::TABLE, $id, $data);
    }
}
