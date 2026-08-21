<?php
declare(strict_types=1);

/**
 * Begrenzt fehlgeschlagene Login-Versuche pro IP (15-Minuten- und 24-Stunden-Fenster).
 */
final class LoginThrottle
{
    private const MAX_ATTEMPTS_SHORT = 5;
    private const WINDOW_SHORT       = 900;   // 15 min
    private const MAX_ATTEMPTS_LONG  = 20;
    private const WINDOW_LONG        = 86400; // 24 h

    /**
     * Prüft, ob die IP gesperrt ist.
     *
     * @return string|null Fehlermeldung für den Nutzer oder null wenn Login erlaubt.
     */
    public static function check(string $ip): ?string
    {
        if (!Database::isConfigured()) {
            return null;
        }

        $pdo = Database::pdo();
        self::cleanup($pdo);

        $count24h = self::countFailures($pdo, $ip, self::WINDOW_LONG);
        if ($count24h >= self::MAX_ATTEMPTS_LONG) {
            return 'Zu viele fehlgeschlagene Versuche. IP für 24 Stunden gesperrt.';
        }

        $count15m = self::countFailures($pdo, $ip, self::WINDOW_SHORT);
        if ($count15m >= self::MAX_ATTEMPTS_SHORT) {
            return 'Zu viele Versuche. Bitte warten Sie 15 Minuten.';
        }

        return null;
    }

    /**
     * Protokolliert einen fehlgeschlagenen Login-Versuch.
     */
    public static function recordFailure(string $ip, string $username): void
    {
        if (!Database::isConfigured()) {
            return;
        }

        $pdo = Database::pdo();
        $stmt = $pdo->prepare(
            'INSERT INTO dg_login_attempts (ip, username, success, created_at) VALUES (:ip, :u, 0, NOW())'
        );
        $stmt->execute(['ip' => $ip, 'u' => mb_substr($username, 0, 255)]);
    }

    /**
     * Protokolliert einen erfolgreichen Login und löscht Fehlversuche der IP.
     */
    public static function recordSuccess(string $ip, string $username): void
    {
        if (!Database::isConfigured()) {
            return;
        }

        $pdo = Database::pdo();
        $stmt = $pdo->prepare(
            'INSERT INTO dg_login_attempts (ip, username, success, created_at) VALUES (:ip, :u, 1, NOW())'
        );
        $stmt->execute(['ip' => $ip, 'u' => mb_substr($username, 0, 255)]);

        $del = $pdo->prepare('DELETE FROM dg_login_attempts WHERE ip = :ip AND success = 0');
        $del->execute(['ip' => $ip]);
    }

    /**
     * Zählt fehlgeschlagene Versuche innerhalb des Zeitfensters.
     */
    private static function countFailures(PDO $pdo, string $ip, int $windowSeconds): int
    {
        $stmt = $pdo->prepare(
            'SELECT COUNT(*) FROM dg_login_attempts
             WHERE ip = :ip AND success = 0 AND created_at > DATE_SUB(NOW(), INTERVAL :w SECOND)'
        );
        $stmt->execute(['ip' => $ip, 'w' => $windowSeconds]);
        return (int) $stmt->fetchColumn();
    }

    /**
     * Entfernt Einträge älter als 7 Tage (einmal pro Request).
     */
    private static function cleanup(PDO $pdo): void
    {
        static $done = false;
        if ($done) {
            return;
        }
        $done = true;
        $pdo->exec("DELETE FROM dg_login_attempts WHERE created_at < DATE_SUB(NOW(), INTERVAL 7 DAY)");
    }
}
