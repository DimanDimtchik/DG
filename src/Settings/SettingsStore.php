<?php
declare(strict_types=1);

/**
 * Einstellungen in der Datenbank — unabhängig vom Hosting-Pfad.
 */
final class SettingsStore
{
    /**
     * Methode ensure ready.
     * @return void
     * @throws RuntimeException
     */
    private static function ensureReady(): void
    {
        if (!Database::isConfigured()) {
            throw new RuntimeException('Datenbank ist nicht konfiguriert.');
        }
        MigrationRunner::runPending();
        self::ensureTable();
    }

    /**
     * Methode ensure table.
     * @return void
     */
    private static function ensureTable(): void
    {
        $pdo = Database::pdo();
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS dg_settings (
                setting_key VARCHAR(64) NOT NULL,
                value_json LONGTEXT NOT NULL,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (setting_key)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
    }

    /**
     * Methode get.
     * @param string $key
     * @param array $defaults
     * @return array<string, mixed>
     * @throws RuntimeException
     */
    public static function get(string $key, array $defaults = []): array
    {
        if (!Database::isConfigured()) {
            return self::normalize($defaults, $defaults);
        }

        self::ensureReady();

        try {
            $pdo = Database::pdo();
            $stmt = $pdo->prepare('SELECT value_json FROM dg_settings WHERE setting_key = :key LIMIT 1');
            $stmt->execute(['key' => $key]);
            $raw = $stmt->fetchColumn();
            if ($raw === false || $raw === '') {
                return self::normalize($defaults, $defaults);
            }
            $decoded = json_decode((string) $raw, true);
            if (!is_array($decoded)) {
                throw new RuntimeException('Einstellungen in der Datenbank sind beschädigt (Schlüssel: ' . $key . ').');
            }

            return self::normalize($defaults, $decoded);
        } catch (PDOException $e) {
            throw new RuntimeException('Einstellungen konnten nicht geladen werden: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Methode set.
     * @param string $key
     * @param array $data
     * @return void
     * @throws RuntimeException
     */
    public static function set(string $key, array $data): void
    {
        self::ensureReady();

        $json = json_encode($data, JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            throw new RuntimeException('Einstellungen konnten nicht serialisiert werden.');
        }

        $pdo = Database::pdo();
        $stmt = $pdo->prepare(
            'INSERT INTO dg_settings (setting_key, value_json) VALUES (:key, :value_json)
             ON DUPLICATE KEY UPDATE value_json = VALUES(value_json)'
        );
        $stmt->execute([
            'key' => $key,
            'value_json' => $json,
        ]);

        self::verifyStored($key);
    }

    /**
     * Methode verify stored.
     * @param string $key
     * @return void
     * @throws RuntimeException
     */
    private static function verifyStored(string $key): void
    {
        $pdo = Database::pdo();
        $stmt = $pdo->prepare('SELECT value_json FROM dg_settings WHERE setting_key = :key LIMIT 1');
        $stmt->execute(['key' => $key]);
        $raw = $stmt->fetchColumn();
        if ($raw === false || $raw === '') {
            throw new RuntimeException('Einstellungen konnten nicht gespeichert werden (kein DB-Eintrag).');
        }

        $decoded = json_decode((string) $raw, true);
        if (!is_array($decoded)) {
            throw new RuntimeException('Einstellungen konnten nicht gespeichert werden (ungültige DB-Antwort).');
        }
    }

    /**
     * Normalisiert den Eingabewert.
     * @param array $defaults
     * @param array $stored
     * @return array<string, mixed>
     */
    private static function normalize(array $defaults, array $stored): array
    {
        if ($defaults === []) {
            return $stored;
        }

        $out = $defaults;
        foreach ($defaults as $field => $defaultValue) {
            if (array_key_exists($field, $stored)) {
                $out[$field] = $stored[$field];
            }
        }

        return $out;
    }
}
