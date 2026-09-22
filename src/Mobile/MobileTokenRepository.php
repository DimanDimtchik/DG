<?php
declare(strict_types=1);

/** Bearer-Tokens für Mobile Apps. */
final class MobileTokenRepository
{
    public const TTL_DAYS = 30;

    public static function tableReady(): bool
    {
        if (!Database::isConfigured()) {
            return false;
        }
        try {
            $r = Database::pdo()->query("SHOW TABLES LIKE 'dg_mobile_tokens'");

            return $r !== false && $r->fetchColumn() !== false;
        } catch (Throwable) {
            return false;
        }
    }

    public static function hash(string $plain): string
    {
        return hash('sha256', $plain);
    }

    /**
     * @return array{token: string, expires_at: string, id: int}
     */
    public static function issue(string $subjectType, int $subjectId, int $contactId): array
    {
        if (!in_array($subjectType, ['customer', 'staff'], true) || $subjectId < 1 || $contactId < 1) {
            throw new InvalidArgumentException('Ungültiger Token-Subject.');
        }
        MigrationRunner::runPending();
        if (!self::tableReady()) {
            throw new RuntimeException('Token-Tabelle fehlt — Migration 102.');
        }
        $plain = bin2hex(random_bytes(32));
        $expires = (new DateTimeImmutable('+' . self::TTL_DAYS . ' days'))->format('Y-m-d H:i:s');
        $stmt = Database::pdo()->prepare(
            'INSERT INTO dg_mobile_tokens
                (token_hash, subject_type, subject_id, contact_id, expires_at)
             VALUES (:h, :t, :sid, :cid, :exp)'
        );
        $stmt->execute([
            'h' => self::hash($plain),
            't' => $subjectType,
            'sid' => $subjectId,
            'cid' => $contactId,
            'exp' => $expires,
        ]);

        return [
            'token' => $plain,
            'expires_at' => $expires,
            'id' => (int) Database::pdo()->lastInsertId(),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function findValidByPlain(string $plain): ?array
    {
        $plain = trim($plain);
        if ($plain === '' || !self::tableReady()) {
            return null;
        }
        MigrationRunner::runPending();
        $stmt = Database::pdo()->prepare(
            'SELECT * FROM dg_mobile_tokens
             WHERE token_hash = :h
               AND revoked_at IS NULL
               AND expires_at > NOW()
             LIMIT 1'
        );
        $stmt->execute(['h' => self::hash($plain)]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) {
            return null;
        }
        $upd = Database::pdo()->prepare(
            'UPDATE dg_mobile_tokens SET last_used_at = NOW() WHERE id = :id'
        );
        $upd->execute(['id' => (int) ($row['id'] ?? 0)]);

        return $row;
    }

    public static function revokeByPlain(string $plain): void
    {
        if (!self::tableReady() || trim($plain) === '') {
            return;
        }
        MigrationRunner::runPending();
        $stmt = Database::pdo()->prepare(
            'UPDATE dg_mobile_tokens SET revoked_at = NOW()
             WHERE token_hash = :h AND revoked_at IS NULL'
        );
        $stmt->execute(['h' => self::hash($plain)]);
    }

    public static function revokeById(int $id): void
    {
        if ($id < 1 || !self::tableReady()) {
            return;
        }
        MigrationRunner::runPending();
        $stmt = Database::pdo()->prepare(
            'UPDATE dg_mobile_tokens SET revoked_at = NOW() WHERE id = :id AND revoked_at IS NULL'
        );
        $stmt->execute(['id' => $id]);
    }
}
