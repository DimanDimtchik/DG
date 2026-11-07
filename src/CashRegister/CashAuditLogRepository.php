<?php
declare(strict_types=1);

/** Append-only Registerkasse-Audit (K1j Stub). Kein update/delete. */
final class CashAuditLogRepository
{
    private const TABLE = 'dg_cash_audit_log';

    public static function tableExists(): bool
    {
        return CashRegisterTable::tableExists(self::TABLE);
    }

    /** @param array<string, mixed> $data */
    public static function insert(array $data): int
    {
        return CashRegisterTable::insert(self::TABLE, [
            'location_id' => isset($data['location_id']) ? (int) $data['location_id'] : null,
            'register_id' => isset($data['register_id']) ? (int) $data['register_id'] : null,
            'session_id' => isset($data['session_id']) ? (int) $data['session_id'] : null,
            'receipt_id' => isset($data['receipt_id']) ? (int) $data['receipt_id'] : null,
            'user_id' => isset($data['user_id']) ? (int) $data['user_id'] : null,
            'event_type' => trim((string) ($data['event_type'] ?? '')),
            'severity' => trim((string) ($data['severity'] ?? 'info')) ?: 'info',
            'message' => trim((string) ($data['message'] ?? '')),
            'details_json' => $data['details_json'] ?? null,
            'ip' => trim((string) ($data['ip'] ?? '')),
        ]);
    }
}
