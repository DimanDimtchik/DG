<?php
declare(strict_types=1);

final class Database
{
    private static ?PDO $pdo = null;

    public static function pdo(): PDO
    {
        if (self::$pdo instanceof PDO) {
            return self::$pdo;
        }

        $cfg = App::config('database');
        if (!is_array($cfg)) {
            throw new RuntimeException('Datenbank nicht konfiguriert.');
        }

        $password = (string) ($cfg['password'] ?? '');
        if ($password === '') {
            throw new RuntimeException(
                'Kein Datenbank-Passwort. Bitte config/database.local.php anlegen.'
            );
        }

        $host = (string) ($cfg['host'] ?? 'localhost');
        $port = (int) ($cfg['port'] ?? 3306);
        $database = (string) ($cfg['database'] ?? '');
        $username = (string) ($cfg['username'] ?? '');
        $charset = (string) ($cfg['charset'] ?? 'utf8mb4');

        $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s', $host, $port, $database, $charset);

        self::$pdo = new PDO($dsn, $username, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);

        return self::$pdo;
    }

    public static function isConfigured(): bool
    {
        $cfg = App::config('database');
        return is_array($cfg) && ($cfg['password'] ?? '') !== '';
    }

    public static function reset(): void
    {
        self::$pdo = null;
    }

    /** @param array<string, mixed> $cfg */
    public static function testWithConfig(array $cfg): string
    {
        $password = (string) ($cfg['password'] ?? '');
        if ($password === '' || ($cfg['database'] ?? '') === '' || ($cfg['username'] ?? '') === '') {
            throw new InvalidArgumentException('Datenbank, Benutzer und Passwort sind erforderlich.');
        }

        $host = (string) ($cfg['host'] ?? 'localhost');
        $port = (int) ($cfg['port'] ?? 3306);
        $database = (string) $cfg['database'];
        $username = (string) $cfg['username'];
        $charset = (string) ($cfg['charset'] ?? 'utf8mb4');
        $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s', $host, $port, $database, $charset);

        $pdo = new PDO($dsn, $username, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]);
        $version = $pdo->query('SELECT VERSION()')->fetchColumn();

        return 'Verbindung OK – ' . (string) $version;
    }
}
