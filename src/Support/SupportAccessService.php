<?php
declare(strict_types=1);

/**
 * Zeitlich begrenzte Support-Freigabe in der Kunden-CRM-Instanz.
 */
final class SupportAccessService
{
    public const DURATIONS = [
        4 => '4 Stunden',
        12 => '12 Stunden',
        24 => '24 Stunden',
        48 => '48 Stunden',
        72 => '72 Stunden',
    ];

    public const DEFAULT_HOURS = 24;

    public static function ensureTables(): void
    {
        if (!Database::isConfigured()) {
            return;
        }
        MigrationRunner::runPending();
    }

    /** @return list<int> */
    public static function allowedDurations(): array
    {
        return array_map('intval', array_keys(self::DURATIONS));
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function activeGrant(): ?array
    {
        self::ensureTables();
        self::expireStale();
        $stmt = Database::pdo()->query(
            "SELECT * FROM dg_support_access
             WHERE status = 'active' AND expires_at > NOW()
             ORDER BY id DESC LIMIT 1"
        );
        $row = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : false;

        return $row ?: null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function findById(int $id): ?array
    {
        if ($id < 1) {
            return null;
        }
        self::ensureTables();
        $stmt = Database::pdo()->prepare('SELECT * FROM dg_support_access WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    /**
     * @return array{grant: array<string, mixed>, token: string}
     */
    public static function start(int $hours, ?int $userId, bool $screenShare = true): array
    {
        self::ensureTables();
        if (!isset(self::DURATIONS[$hours])) {
            $hours = self::DEFAULT_HOURS;
        }

        $existing = self::activeGrant();
        if ($existing !== null) {
            throw new RuntimeException('Es ist bereits eine Support-Freigabe aktiv.');
        }

        $token = bin2hex(random_bytes(32));
        $hash = hash('sha256', $token);
        $pdo = Database::pdo();
        $stmt = $pdo->prepare(
            'INSERT INTO dg_support_access
             (token_hash, duration_hours, status, started_by, started_at, expires_at, screen_share_enabled)
             VALUES (:hash, :hours, \'active\', :uid, NOW(), DATE_ADD(NOW(), INTERVAL :hours2 HOUR), :screen)'
        );
        $stmt->execute([
            'hash' => $hash,
            'hours' => $hours,
            'hours2' => $hours,
            'uid' => $userId,
            'screen' => $screenShare ? 1 : 0,
        ]);
        $id = (int) $pdo->lastInsertId();
        $grant = self::findById($id);
        if ($grant === null) {
            throw new RuntimeException('Freigabe konnte nicht angelegt werden.');
        }

        AuditLog::record('support_access_start', $userId, json_encode([
            'id' => $id,
            'hours' => $hours,
            'expires_at' => $grant['expires_at'] ?? null,
        ], JSON_UNESCAPED_UNICODE));

        SupportHubClient::reportStart($token, $grant);

        return ['grant' => $grant, 'token' => $token];
    }

    public static function stop(?int $userId, string $reason = 'manual'): void
    {
        self::ensureTables();
        $grant = self::activeGrant();
        if ($grant === null) {
            return;
        }
        $stmt = Database::pdo()->prepare(
            "UPDATE dg_support_access
             SET status = 'ended', ended_at = NOW(), end_reason = :reason
             WHERE id = :id AND status = 'active'"
        );
        $stmt->execute([
            'reason' => mb_substr($reason, 0, 40),
            'id' => (int) $grant['id'],
        ]);
        AuditLog::record('support_access_stop', $userId, json_encode([
            'id' => (int) $grant['id'],
            'reason' => $reason,
        ], JSON_UNESCAPED_UNICODE));
        SupportHubClient::reportStop($grant);
        SupportSignalStore::clearForAccess((int) $grant['id']);
    }

    public static function expireStale(): void
    {
        if (!Database::isConfigured()) {
            return;
        }
        try {
            $pdo = Database::pdo();
            $rows = $pdo->query(
                "SELECT id FROM dg_support_access WHERE status = 'active' AND expires_at <= NOW()"
            )->fetchAll(PDO::FETCH_ASSOC);
            if ($rows === []) {
                return;
            }
            $pdo->exec(
                "UPDATE dg_support_access
                 SET status = 'expired', ended_at = NOW(), end_reason = 'expired'
                 WHERE status = 'active' AND expires_at <= NOW()"
            );
            foreach ($rows as $row) {
                AuditLog::record('support_access_expired', null, 'id=' . (int) $row['id']);
            }
        } catch (Throwable) {
            // table may not exist yet
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function findActiveByToken(string $token): ?array
    {
        $token = trim($token);
        if ($token === '' || strlen($token) < 32) {
            return null;
        }
        self::ensureTables();
        self::expireStale();
        $hash = hash('sha256', $token);
        $stmt = Database::pdo()->prepare(
            "SELECT * FROM dg_support_access
             WHERE token_hash = :hash AND status = 'active' AND expires_at > NOW()
             LIMIT 1"
        );
        $stmt->execute(['hash' => $hash]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public static function remainingLabel(?array $grant): string
    {
        if ($grant === null) {
            return '';
        }
        $expires = strtotime((string) ($grant['expires_at'] ?? ''));
        if ($expires === false) {
            return '';
        }
        $sec = max(0, $expires - time());
        $h = intdiv($sec, 3600);
        $m = intdiv($sec % 3600, 60);
        if ($h > 0) {
            return $h . ' Std. ' . $m . ' Min.';
        }

        return $m . ' Min.';
    }

    public static function supportUser(): User
    {
        return new User(
            id: 0,
            username: 'ganz-soft-support',
            displayName: 'Ganz Soft Support',
            email: 'support@ganz-soft.de',
            roles: ['administrator'],
            employeeActive: true,
        );
    }
}
