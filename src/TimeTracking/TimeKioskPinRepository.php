<?php
declare(strict_types=1);

/** Persistenz Kiosk-PIN, Sessions und Reset-Anfragen. */
final class TimeKioskPinRepository
{
    public const STATUS_PENDING_HR = 'pending_hr';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_BLOCKED = 'blocked';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_EXPIRED = 'expired';

    public static function findPinHash(int $contactId): ?string
    {
        if (!Database::isConfigured() || $contactId < 1) {
            return null;
        }
        MigrationRunner::runPending();
        $stmt = Database::pdo()->prepare(
            'SELECT pin_hash FROM dg_time_kiosk_pins WHERE contact_id = :id LIMIT 1'
        );
        $stmt->execute(['id' => $contactId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? (string) ($row['pin_hash'] ?? '') : null;
    }

    public static function hasPin(int $contactId): bool
    {
        $hash = self::findPinHash($contactId);

        return $hash !== null && $hash !== '';
    }

    public static function upsertPin(int $contactId, string $pinHash, ?int $updatedBy): void
    {
        MigrationRunner::runPending();
        $stmt = Database::pdo()->prepare(
            'INSERT INTO dg_time_kiosk_pins (contact_id, pin_hash, updated_by)
             VALUES (:id, :hash, :by)
             ON DUPLICATE KEY UPDATE pin_hash = VALUES(pin_hash), updated_by = VALUES(updated_by)'
        );
        $stmt->execute([
            'id' => $contactId,
            'hash' => $pinHash,
            'by' => $updatedBy,
        ]);
    }

    public static function createResetRequest(int $contactId, string $hrTokenHash, ?string $ip): int
    {
        MigrationRunner::runPending();
        // Offene Anfragen desselben MA schließen (neu startet)
        $close = Database::pdo()->prepare(
            "UPDATE dg_time_kiosk_pin_resets
             SET status = :expired
             WHERE contact_id = :cid AND status = :pending"
        );
        $close->execute([
            'expired' => self::STATUS_EXPIRED,
            'cid' => $contactId,
            'pending' => self::STATUS_PENDING_HR,
        ]);

        $stmt = Database::pdo()->prepare(
            'INSERT INTO dg_time_kiosk_pin_resets (contact_id, status, hr_token_hash, request_ip)
             VALUES (:cid, :status, :token, :ip)'
        );
        $stmt->execute([
            'cid' => $contactId,
            'status' => self::STATUS_PENDING_HR,
            'token' => $hrTokenHash,
            'ip' => $ip,
        ]);

        return (int) Database::pdo()->lastInsertId();
    }

    /** @return array<string, mixed>|null */
    public static function findResetByHrTokenHash(string $hash): ?array
    {
        return self::findResetByHash('hr_token_hash', $hash);
    }

    /** @return array<string, mixed>|null */
    public static function findResetBySetTokenHash(string $hash): ?array
    {
        return self::findResetByHash('set_token_hash', $hash);
    }

    public static function findResetById(int $id): ?array
    {
        if (!Database::isConfigured() || $id < 1) {
            return null;
        }
        MigrationRunner::runPending();
        $stmt = Database::pdo()->prepare('SELECT * FROM dg_time_kiosk_pin_resets WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function listPending(int $limit = 50): array
    {
        if (!Database::isConfigured()) {
            return [];
        }
        MigrationRunner::runPending();
        $limit = max(1, min(100, $limit));
        $stmt = Database::pdo()->query(
            "SELECT r.*, c.display_name, c.email, c.login
             FROM dg_time_kiosk_pin_resets r
             LEFT JOIN dg_contacts c ON c.id = r.contact_id
             WHERE r.status = 'pending_hr'
             ORDER BY r.requested_at DESC
             LIMIT {$limit}"
        );
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return is_array($rows) ? $rows : [];
    }

    public static function markApproved(int $id, string $setTokenHash, string $expiresAt, ?int $decidedBy): void
    {
        MigrationRunner::runPending();
        $stmt = Database::pdo()->prepare(
            'UPDATE dg_time_kiosk_pin_resets
             SET status = :status, set_token_hash = :set, set_token_expires_at = :exp,
                 decided_at = NOW(), decided_by = :by
             WHERE id = :id AND status = :pending'
        );
        $stmt->execute([
            'status' => self::STATUS_APPROVED,
            'set' => $setTokenHash,
            'exp' => $expiresAt,
            'by' => $decidedBy,
            'id' => $id,
            'pending' => self::STATUS_PENDING_HR,
        ]);
    }

    public static function markBlocked(int $id, ?int $decidedBy): void
    {
        MigrationRunner::runPending();
        $stmt = Database::pdo()->prepare(
            'UPDATE dg_time_kiosk_pin_resets
             SET status = :status, decided_at = NOW(), decided_by = :by
             WHERE id = :id AND status = :pending'
        );
        $stmt->execute([
            'status' => self::STATUS_BLOCKED,
            'by' => $decidedBy,
            'id' => $id,
            'pending' => self::STATUS_PENDING_HR,
        ]);
    }

    public static function markCompleted(int $id): void
    {
        MigrationRunner::runPending();
        $stmt = Database::pdo()->prepare(
            "UPDATE dg_time_kiosk_pin_resets
             SET status = :status, completed_at = NOW(), set_token_hash = NULL
             WHERE id = :id AND status = :approved"
        );
        $stmt->execute([
            'status' => self::STATUS_COMPLETED,
            'id' => $id,
            'approved' => self::STATUS_APPROVED,
        ]);
    }

    public static function createSession(int $contactId, string $sessionHash, string $expiresAt): int
    {
        MigrationRunner::runPending();
        $stmt = Database::pdo()->prepare(
            'INSERT INTO dg_time_kiosk_sessions (contact_id, session_hash, expires_at)
             VALUES (:cid, :hash, :exp)'
        );
        $stmt->execute([
            'cid' => $contactId,
            'hash' => $sessionHash,
            'exp' => $expiresAt,
        ]);

        return (int) Database::pdo()->lastInsertId();
    }

    public static function findValidSession(string $sessionHash): ?array
    {
        if (!Database::isConfigured() || $sessionHash === '') {
            return null;
        }
        MigrationRunner::runPending();
        $stmt = Database::pdo()->prepare(
            'SELECT * FROM dg_time_kiosk_sessions
             WHERE session_hash = :hash AND expires_at > NOW()
             LIMIT 1'
        );
        $stmt->execute(['hash' => $sessionHash]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    public static function deleteSession(string $sessionHash): void
    {
        if (!Database::isConfigured() || $sessionHash === '') {
            return;
        }
        MigrationRunner::runPending();
        $stmt = Database::pdo()->prepare('DELETE FROM dg_time_kiosk_sessions WHERE session_hash = :hash');
        $stmt->execute(['hash' => $sessionHash]);
    }

    public static function purgeExpiredSessions(): void
    {
        if (!Database::isConfigured()) {
            return;
        }
        MigrationRunner::runPending();
        Database::pdo()->exec('DELETE FROM dg_time_kiosk_sessions WHERE expires_at <= NOW()');
    }

    /** @return array<string, mixed>|null */
    private static function findResetByHash(string $column, string $hash): ?array
    {
        if (!Database::isConfigured() || $hash === '' || !in_array($column, ['hr_token_hash', 'set_token_hash'], true)) {
            return null;
        }
        MigrationRunner::runPending();
        $stmt = Database::pdo()->prepare(
            "SELECT * FROM dg_time_kiosk_pin_resets WHERE {$column} = :h LIMIT 1"
        );
        $stmt->execute(['h' => $hash]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }
}
