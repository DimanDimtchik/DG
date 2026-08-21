<?php
declare(strict_types=1);

/**
 * Persistiert sicherheitsrelevante Ereignisse in der Datenbank (dg_audit_log).
 */
final class AuditLog
{
    /**
     * Schreibt einen Audit-Eintrag (Login, Logout, Integritätsverletzungen usw.).
     *
     * @param string $action Kurzbezeichnung des Ereignisses (max. 100 Zeichen).
     * @param int|null $userId Betroffener Benutzer oder null bei anonymen Aktionen.
     * @param string|null $details Optionale Detailinformationen (JSON oder Freitext).
     */
    public static function record(string $action, ?int $userId = null, ?string $details = null): void
    {
        if (!Database::isConfigured()) {
            return;
        }

        try {
            $pdo = Database::pdo();
            $stmt = $pdo->prepare(
                'INSERT INTO dg_audit_log (user_id, action, ip, user_agent, details, created_at)
                 VALUES (:uid, :action, :ip, :ua, :details, NOW())'
            );
            $stmt->execute([
                'uid'     => $userId,
                'action'  => mb_substr($action, 0, 100),
                'ip'      => Firewall::clientIp(),
                'ua'      => mb_substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 500),
                'details' => $details !== null ? mb_substr($details, 0, 65000) : null,
            ]);
        } catch (Throwable) {
            // table may not exist yet
        }
    }

    /**
     * Liefert die jüngsten Audit-Einträge mit Benutzernamen.
     *
     * @param int $limit Maximale Anzahl Zeilen.
     * @param int $offset Offset für Paginierung.
     * @return list<array<string, mixed>>
     */
    public static function recent(int $limit = 100, int $offset = 0): array
    {
        if (!Database::isConfigured()) {
            return [];
        }

        $pdo = Database::pdo();
        $stmt = $pdo->prepare(
            'SELECT a.*, u.username
             FROM dg_audit_log a
             LEFT JOIN dg_users u ON u.id = a.user_id
             ORDER BY a.created_at DESC
             LIMIT :lim OFFSET :off'
        );
        $stmt->bindValue('lim', $limit, PDO::PARAM_INT);
        $stmt->bindValue('off', $offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Löscht Audit- und Security-Log-Einträge älter als die angegebene Aufbewahrungsfrist.
     *
     * @param int $daysToKeep Anzahl Tage, die Einträge aufbewahrt werden.
     */
    public static function cleanup(int $daysToKeep = 90): void
    {
        if (!Database::isConfigured()) {
            return;
        }

        $pdo = Database::pdo();
        $pdo->exec("DELETE FROM dg_audit_log WHERE created_at < DATE_SUB(NOW(), INTERVAL {$daysToKeep} DAY)");
        $pdo->exec("DELETE FROM dg_security_log WHERE created_at < DATE_SUB(NOW(), INTERVAL {$daysToKeep} DAY)");
    }
}
