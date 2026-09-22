<?php
declare(strict_types=1);

/** Einfaches Rate-Limit für Mobile-Auth (pro IP + Aktion). */
final class MobileAuthThrottle
{
    private const WINDOW_SECONDS = 900;
    private const MAX_ATTEMPTS = 30;

    public static function assertAllowed(string $action): void
    {
        if (!Database::isConfigured()) {
            return;
        }
        MigrationRunner::runPending();
        try {
            $chk = Database::pdo()->query("SHOW TABLES LIKE 'dg_mobile_auth_throttle'");
            if ($chk === false || $chk->fetchColumn() === false) {
                return;
            }
        } catch (Throwable) {
            return;
        }

        $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
        $hash = hash('sha256', $ip);
        $action = preg_replace('/[^a-z_]/', '', strtolower($action)) ?: 'auth';
        $pdo = Database::pdo();
        $pdo->prepare(
            'DELETE FROM dg_mobile_auth_throttle
             WHERE attempted_at < (NOW() - INTERVAL ' . self::WINDOW_SECONDS . ' SECOND)'
        )->execute();

        $cntStmt = $pdo->prepare(
            'SELECT COUNT(*) FROM dg_mobile_auth_throttle
             WHERE ip_hash = :h AND action = :a
               AND attempted_at >= (NOW() - INTERVAL ' . self::WINDOW_SECONDS . ' SECOND)'
        );
        $cntStmt->execute(['h' => $hash, 'a' => $action]);
        if ((int) $cntStmt->fetchColumn() >= self::MAX_ATTEMPTS) {
            throw new RuntimeException('Zu viele Versuche — bitte später erneut.');
        }

        $ins = $pdo->prepare(
            'INSERT INTO dg_mobile_auth_throttle (ip_hash, action) VALUES (:h, :a)'
        );
        $ins->execute(['h' => $hash, 'a' => $action]);
    }
}
