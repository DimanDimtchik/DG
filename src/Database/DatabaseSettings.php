<?php
declare(strict_types=1);

final class DatabaseSettings
{
    /** @return array<string, mixed> */
    public static function forForm(): array
    {
        $cfg = App::config('database');
        if (!is_array($cfg)) {
            $cfg = [];
        }

        return [
            'host' => (string) ($cfg['host'] ?? 'localhost'),
            'port' => (int) ($cfg['port'] ?? 3306),
            'database' => (string) ($cfg['database'] ?? ''),
            'username' => (string) ($cfg['username'] ?? ''),
            'password' => (string) ($cfg['password'] ?? ''),
            'charset' => (string) ($cfg['charset'] ?? 'utf8mb4'),
        ];
    }

    /** @param array<string, mixed> $input */
    public static function save(array $input): void
    {
        $current = self::forForm();
        $password = trim((string) ($input['password'] ?? ''));
        if ($password === '' && $current['password'] !== '') {
            $password = (string) $current['password'];
        }

        $data = [
            'host' => trim((string) ($input['host'] ?? 'localhost')) ?: 'localhost',
            'port' => max(1, (int) ($input['port'] ?? 3306)),
            'database' => trim((string) ($input['database'] ?? '')),
            'username' => trim((string) ($input['username'] ?? '')),
            'password' => $password,
            'charset' => trim((string) ($input['charset'] ?? 'utf8mb4')) ?: 'utf8mb4',
        ];

        if ($data['database'] === '' || $data['username'] === '' || $data['password'] === '') {
            throw new InvalidArgumentException('Host, Datenbank, Benutzer und Passwort sind Pflichtfelder.');
        }

        $path = DG_ROOT . '/config/database.local.php';
        $export = var_export($data, true);
        $php = "<?php\ndeclare(strict_types=1);\n\nreturn {$export};\n";

        if (file_put_contents($path, $php) === false) {
            throw new RuntimeException('Konfiguration konnte nicht gespeichert werden (Schreibrechte?).');
        }

        App::reloadConfig();
        Database::reset();
    }

    /** @param array<string, mixed> $input */
    public static function test(array $input): string
    {
        $current = self::forForm();
        $password = trim((string) ($input['password'] ?? ''));
        if ($password === '') {
            $password = (string) $current['password'];
        }

        return Database::testWithConfig([
            'host' => trim((string) ($input['host'] ?? 'localhost')) ?: 'localhost',
            'port' => max(1, (int) ($input['port'] ?? 3306)),
            'database' => trim((string) ($input['database'] ?? '')),
            'username' => trim((string) ($input['username'] ?? '')),
            'password' => $password,
            'charset' => trim((string) ($input['charset'] ?? 'utf8mb4')) ?: 'utf8mb4',
        ]);
    }

    public static function runMigrations(): int
    {
        return MigrationRunner::runPending();
    }
}
