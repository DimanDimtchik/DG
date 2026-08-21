<?php
declare(strict_types=1);

/**
 * Zentraler Anwendungs-Bootstrap: Session, Sicherheit, Lizenz, Updates und Backups.
 */
final class App
{
    private static ?array $config = null;
    private static ?string $version = null;

    private const SESSION_TIMEOUT = 28800; // 8 h (Arbeitstag)

    /**
     * Initialisiert Session, Security-Layer und periodische Hintergrundaufgaben.
     */
    public static function boot(): void
    {
        date_default_timezone_set(self::config('timezone', 'Europe/Berlin'));

        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_name((string) self::config('session_name', 'dg_crm_session'));
            session_set_cookie_params([
                'lifetime' => 0,
                'path'     => '/',
                'secure'   => self::isHttpsRequest(),
                'httponly'  => true,
                'samesite'  => 'Lax',
            ]);
            session_start();
        }

        if (isset($_SESSION['dg_last_activity'])
            && (time() - (int) $_SESSION['dg_last_activity']) > self::SESSION_TIMEOUT) {
            session_unset();
            session_destroy();
            session_start();
        }
        $_SESSION['dg_last_activity'] = time();

        SecurityHeaders::send();
        Firewall::inspect();
        LicenseGuard::verify();

        UpdateChecker::runIfDue();
        BackupService::runIfDue();
        FileIntegrity::runIfDue();
    }

    /**
     * Liest Konfigurationswerte aus config/app.php (lazy geladen).
     *
     * @return array<string, mixed>|mixed Gesamte Config bei $key === null, sonst einzelner Wert.
     */
    public static function config(?string $key = null, mixed $default = null): mixed
    {
        if (self::$config === null) {
            self::$config = require DG_ROOT . '/config/app.php';
        }

        if ($key === null) {
            return self::$config;
        }

        return self::$config[$key] ?? $default;
    }

    /**
     * Lädt die App-Konfiguration beim nächsten Zugriff neu (z. B. nach Speichern).
     */
    public static function reloadConfig(): void
    {
        self::$config = null;
    }

    /**
     * CRM-Versionsstring aus config/version.php.
     */
    public static function version(): string
    {
        if (self::$version === null) {
            $file = DG_ROOT . '/config/version.php';
            self::$version = is_readable($file) ? (string) require $file : '0.0.0';
        }
        return self::$version;
    }

    /**
     * Öffentliche Basis-URL (public_url oder aus HTTP_HOST abgeleitet).
     */
    public static function publicBaseUrl(): string
    {
        $configured = trim((string) self::config('public_url', ''));
        if ($configured !== '') {
            return rtrim($configured, '/');
        }

        $host = trim((string) ($_SERVER['HTTP_HOST'] ?? ''));
        if ($host === '') {
            return '';
        }

        return (self::isHttpsRequest() ? 'https' : 'http') . '://' . $host;
    }

    /**
     * Ob der aktuelle Request über HTTPS läuft (inkl. Proxy-Header).
     */
    public static function isHttpsRequest(): bool
    {
        if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
            return true;
        }
        $forwarded = strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''));
        if ($forwarded === 'https') {
            return true;
        }
        $httpsPort = (string) ($_SERVER['SERVER_PORT'] ?? '');

        return $httpsPort === '443';
    }
}
