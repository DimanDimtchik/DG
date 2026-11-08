<?php
declare(strict_types=1);

/**
 * FL1c: Fließlinie Stammdaten — Linie + Stationen (kein Puffer, kein Hard-DELETE).
 */
final class ProductionLineStore
{
    public static function ensureReady(): void
    {
        if (!Database::isConfigured()) {
            return;
        }
        MigrationRunner::runPending();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function listLines(bool $activeOnly = true): array
    {
        self::ensureReady();
        if (!Database::isConfigured()) {
            return [];
        }
        $sql = 'SELECT * FROM dg_production_lines';
        if ($activeOnly) {
            $sql .= ' WHERE is_active = 1';
        }
        $sql .= ' ORDER BY is_active DESC, name ASC, id ASC';
        $stmt = Database::pdo()->query($sql);

        return $stmt ? ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: []) : [];
    }

    /**
     * @return array{line: array<string, mixed>, stations: list<array<string, mixed>>}|null
     */
    public static function getLineWithStations(int $lineId): ?array
    {
        self::ensureReady();
        if ($lineId <= 0 || !Database::isConfigured()) {
            return null;
        }
        $pdo = Database::pdo();
        $stmt = $pdo->prepare('SELECT * FROM dg_production_lines WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $lineId]);
        $line = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$line) {
            return null;
        }
        $st = $pdo->prepare(
            'SELECT * FROM dg_production_line_stations
             WHERE line_id = :line_id
             ORDER BY station_order ASC, id ASC'
        );
        $st->execute(['line_id' => $lineId]);
        $stations = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

        return [
            'line' => $line,
            'stations' => $stations,
        ];
    }

    /**
     * Linie anlegen oder Kopfdaten aktualisieren. Soft-active; kein Hard-DELETE.
     *
     * @param array{name?: string, code?: string, recipe_id?: int|null, is_active?: bool|int, notes?: string} $data
     */
    public static function saveLine(array $data, ?int $id = null): int
    {
        self::ensureReady();
        if (!Database::isConfigured()) {
            throw new RuntimeException('Datenbank nicht verbunden.');
        }

        $name = trim((string) ($data['name'] ?? ''));
        if ($name === '') {
            throw new InvalidArgumentException('Bitte einen Namen für die Linie angeben.');
        }
        if (mb_strlen($name) > 191) {
            throw new InvalidArgumentException('Name ist zu lang (max. 191 Zeichen).');
        }
        $code = trim((string) ($data['code'] ?? ''));
        if (mb_strlen($code) > 64) {
            $code = mb_substr($code, 0, 64);
        }
        $recipeId = $data['recipe_id'] ?? null;
        if ($recipeId !== null && $recipeId !== '') {
            $recipeId = (int) $recipeId;
            if ($recipeId <= 0) {
                $recipeId = null;
            }
        } else {
            $recipeId = null;
        }
        $isActive = !empty($data['is_active']) ? 1 : 0;
        if ($id === null || $id <= 0) {
            // Neu: Standard aktiv, außer explizit false
            if (!array_key_exists('is_active', $data)) {
                $isActive = 1;
            }
        }
        $notes = trim((string) ($data['notes'] ?? ''));
        if (mb_strlen($notes) > 1000) {
            $notes = mb_substr($notes, 0, 1000);
        }

        $pdo = Database::pdo();
        if ($id !== null && $id > 0) {
            $existing = self::getLineWithStations($id);
            if ($existing === null) {
                throw new InvalidArgumentException('Linie nicht gefunden.');
            }
            $stmt = $pdo->prepare(
                'UPDATE dg_production_lines
                 SET name = :name,
                     code = :code,
                     recipe_id = :recipe_id,
                     is_active = :is_active,
                     notes = :notes
                 WHERE id = :id'
            );
            $stmt->execute([
                'name' => $name,
                'code' => $code,
                'recipe_id' => $recipeId,
                'is_active' => $isActive,
                'notes' => $notes,
                'id' => $id,
            ]);

            return $id;
        }

        $stmt = $pdo->prepare(
            'INSERT INTO dg_production_lines (name, code, recipe_id, is_active, notes)
             VALUES (:name, :code, :recipe_id, :is_active, :notes)'
        );
        $stmt->execute([
            'name' => $name,
            'code' => $code,
            'recipe_id' => $recipeId,
            'is_active' => $isActive,
            'notes' => $notes,
        ]);

        return (int) $pdo->lastInsertId();
    }

    /** Soft-Deaktivieren — kein Hard-DELETE. */
    public static function deactivateLine(int $lineId): void
    {
        self::ensureReady();
        if ($lineId <= 0 || !Database::isConfigured()) {
            return;
        }
        $stmt = Database::pdo()->prepare(
            'UPDATE dg_production_lines SET is_active = 0 WHERE id = :id'
        );
        $stmt->execute(['id' => $lineId]);
    }

    /**
     * Stationen komplett neu setzen (Rewrite). Reihenfolge = Array-Reihenfolge → station_order 1…n.
     * Jedes Element: work_center_id (int), optional label (string).
     *
     * @param list<array{work_center_id: int, label?: string}> $stations
     */
    public static function saveStations(int $lineId, array $stations): void
    {
        self::ensureReady();
        if (!Database::isConfigured()) {
            throw new RuntimeException('Datenbank nicht verbunden.');
        }
        if ($lineId <= 0 || self::getLineWithStations($lineId) === null) {
            throw new InvalidArgumentException('Linie nicht gefunden.');
        }
        if ($stations === []) {
            throw new InvalidArgumentException('Mindestens eine Station angeben.');
        }

        $normalized = [];
        $seenWc = [];
        foreach ($stations as $i => $row) {
            if (!is_array($row)) {
                throw new InvalidArgumentException('Station #' . ($i + 1) . ': ungültige Daten.');
            }
            $wcId = (int) ($row['work_center_id'] ?? 0);
            if ($wcId <= 0) {
                throw new InvalidArgumentException('Station #' . ($i + 1) . ': Arbeitsplatz fehlt.');
            }
            if (isset($seenWc[$wcId])) {
                throw new InvalidArgumentException('Jeder Arbeitsplatz darf nur einmal in der Linie vorkommen.');
            }
            $seenWc[$wcId] = true;
            $label = trim((string) ($row['label'] ?? ''));
            if (mb_strlen($label) > 191) {
                $label = mb_substr($label, 0, 191);
            }
            $normalized[] = [
                'work_center_id' => $wcId,
                'station_order' => $i + 1,
                'label' => $label,
            ];
        }

        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            $del = $pdo->prepare('DELETE FROM dg_production_line_stations WHERE line_id = :line_id');
            $del->execute(['line_id' => $lineId]);
            $ins = $pdo->prepare(
                'INSERT INTO dg_production_line_stations (line_id, work_center_id, station_order, label)
                 VALUES (:line_id, :work_center_id, :station_order, :label)'
            );
            foreach ($normalized as $row) {
                $ins->execute([
                    'line_id' => $lineId,
                    'work_center_id' => $row['work_center_id'],
                    'station_order' => $row['station_order'],
                    'label' => $row['label'],
                ]);
            }
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }
}
