<?php
declare(strict_types=1);

/**
 * Gemeinsame DB-Hilfen für Registerkasse-Stubs (K1j).
 * Keine Fachlogik — nur tableExists / find / list / insert / update.
 */
final class CashRegisterTable
{
    public static function tableExists(string $table): bool
    {
        if (!Database::isConfigured()) {
            return false;
        }
        MigrationRunner::runPending();
        $stmt = Database::pdo()->query('SHOW TABLES LIKE ' . Database::pdo()->quote($table));

        return $stmt !== false && $stmt->fetchColumn() !== false;
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function findById(string $table, int $id): ?array
    {
        if ($id <= 0 || !self::tableExists($table)) {
            return null;
        }
        $stmt = Database::pdo()->prepare('SELECT * FROM `' . str_replace('`', '``', $table) . '` WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : $row;
    }

    /**
     * @param list<string> $orderBy
     * @return list<array<string, mixed>>
     */
    public static function listAll(string $table, bool $activeOnly = false, array $orderBy = ['sort_order ASC', 'id ASC']): array
    {
        if (!self::tableExists($table)) {
            return [];
        }
        $sql = 'SELECT * FROM `' . str_replace('`', '``', $table) . '`';
        if ($activeOnly) {
            $sql .= ' WHERE is_active = 1';
        }
        if ($orderBy !== []) {
            $sql .= ' ORDER BY ' . implode(', ', $orderBy);
        }
        $stmt = Database::pdo()->query($sql);
        if ($stmt === false) {
            return [];
        }
        $rows = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $rows[] = $row;
        }

        return $rows;
    }

    /**
     * @param array<string, mixed> $fields
     */
    public static function insert(string $table, array $fields): int
    {
        if (!self::tableExists($table)) {
            throw new RuntimeException('Tabelle fehlt: ' . $table);
        }
        $cols = array_keys($fields);
        $placeholders = array_map(static fn (string $c): string => ':' . $c, $cols);
        Database::pdo()->prepare(
            'INSERT INTO `' . str_replace('`', '``', $table) . '` (' . implode(', ', $cols) . ')
             VALUES (' . implode(', ', $placeholders) . ')'
        )->execute($fields);

        return (int) Database::pdo()->lastInsertId();
    }

    /**
     * @param array<string, mixed> $fields
     */
    public static function update(string $table, int $id, array $fields): void
    {
        if ($id <= 0 || $fields === [] || !self::tableExists($table)) {
            return;
        }
        $set = [];
        $params = ['id' => $id];
        foreach ($fields as $col => $value) {
            $set[] = $col . ' = :' . $col;
            $params[$col] = $value;
        }
        Database::pdo()->prepare(
            'UPDATE `' . str_replace('`', '``', $table) . '` SET ' . implode(', ', $set) . ' WHERE id = :id'
        )->execute($params);
    }
}
