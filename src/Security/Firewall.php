<?php
declare(strict_types=1);

/**
 * Blockiert bekannte Scanner, sensible Pfade und verdächtige Anfragen (HTTP 403).
 */
final class Firewall
{
    private const BLOCKED_AGENTS = [
        'sqlmap', 'nikto', 'nessus', 'masscan', 'zgrab', 'gobuster',
        'dirbuster', 'wpscan', 'nmap', 'havij', 'acunetix',
    ];

    private const BLOCKED_PATHS = [
        '/wp-admin', '/wp-login', '/wp-login.php', '/xmlrpc.php',
        '/phpmyadmin', '/pma', '/myadmin', '/adminer', '/adminer.php',
        '/.env', '/.git', '/.svn', '/.htpasswd',
        '/wp-config.php', '/config.php', '/phpinfo.php',
        '/eval-stdin.php', '/vendor/phpunit',
        '/telescope', '/horizon', '/_ignition',
        '/solr', '/actuator', '/api/jsonws',
    ];

    /**
     * Prüft die aktuelle Anfrage und beendet sie bei Verdacht mit 403 Forbidden.
     */
    public static function inspect(): void
    {
        $ip = self::clientIp();
        $ua = strtolower((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''));
        $path = strtolower(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/');

        foreach (self::BLOCKED_AGENTS as $bot) {
            if (str_contains($ua, $bot)) {
                self::block($ip, $ua, $path, 'bad_user_agent');
            }
        }

        foreach (self::BLOCKED_PATHS as $blocked) {
            if ($path === $blocked || str_starts_with($path, $blocked . '/')) {
                self::block($ip, $ua, $path, 'blocked_path');
            }
        }

        if (preg_match('/\.(sql|bak|old|orig|swp|DS_Store)$/i', $path)) {
            self::block($ip, $ua, $path, 'sensitive_extension');
        }
    }

    /**
     * Ermittelt die Client-IP (X-Forwarded-For oder REMOTE_ADDR).
     */
    public static function clientIp(): string
    {
        $forwarded = (string) ($_SERVER['HTTP_X_FORWARDED_FOR'] ?? '');
        if ($forwarded !== '') {
            $parts = array_map('trim', explode(',', $forwarded));
            $ip = $parts[0];
            if (filter_var($ip, FILTER_VALIDATE_IP)) {
                return $ip;
            }
        }
        return (string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
    }

    /**
     * Protokolliert den Vorfall und sendet 403 Forbidden.
     *
     * @return never
     */
    private static function block(string $ip, string $ua, string $path, string $reason): void
    {
        self::log($ip, $ua, $path, $reason);
        http_response_code(403);
        echo '<!DOCTYPE html><html><head><title>403</title></head><body><h1>403 Forbidden</h1></body></html>';
        exit;
    }

    /**
     * Schreibt einen Eintrag in dg_security_log (falls Tabelle existiert).
     */
    private static function log(string $ip, string $ua, string $path, string $reason): void
    {
        if (!Database::isConfigured()) {
            return;
        }
        try {
            $pdo = Database::pdo();
            $stmt = $pdo->prepare(
                'INSERT INTO dg_security_log (ip, user_agent, path, reason, created_at) VALUES (:ip, :ua, :p, :r, NOW())'
            );
            $stmt->execute([
                'ip' => $ip,
                'ua' => mb_substr($ua, 0, 500),
                'p'  => mb_substr($path, 0, 500),
                'r'  => $reason,
            ]);
        } catch (Throwable) {
            // table may not exist yet
        }
    }
}
