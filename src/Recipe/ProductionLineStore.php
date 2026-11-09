<?php
declare(strict_types=1);

/**
 * FL1c/FL2b: Fließlinie — Linie, Stationen, Zwischenpuffer (kein Hard-DELETE der Linie).
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
     * Puffer werden nach station_order wiederhergestellt (CASCADE würde IDs sonst verlieren).
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
            $bufferSnap = self::snapshotBuffersByStationOrder($pdo, $lineId);

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

            self::restoreBuffersFromSnapshot($pdo, $lineId, $bufferSnap, count($normalized));
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function listBuffers(int $lineId): array
    {
        self::ensureReady();
        if ($lineId <= 0 || !Database::isConfigured()) {
            return [];
        }
        $stmt = Database::pdo()->prepare(
            'SELECT b.*, s.station_order AS after_station_order
             FROM dg_production_line_buffers b
             INNER JOIN dg_production_line_stations s ON s.id = b.after_station_id
             WHERE b.line_id = :line_id
             ORDER BY s.station_order ASC, b.id ASC'
        );
        $stmt->execute(['line_id' => $lineId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Puffer je Lücke neu setzen (Rewrite). Key: after_station_order (1…n-1). Kapazität > 0 Pflicht.
     *
     * @param list<array{after_station_order: int, capacity_qty: float|string, min_qty?: float|string, unit?: string, current_qty?: float|string}> $buffers
     */
    public static function saveBuffers(int $lineId, array $buffers): void
    {
        self::ensureReady();
        if (!Database::isConfigured()) {
            throw new RuntimeException('Datenbank nicht verbunden.');
        }
        $bundle = self::getLineWithStations($lineId);
        if ($bundle === null) {
            throw new InvalidArgumentException('Linie nicht gefunden.');
        }
        $stations = $bundle['stations'];
        $n = count($stations);
        if ($n < 2) {
            throw new InvalidArgumentException('Puffer brauchen mindestens zwei Stationen.');
        }
        if ($buffers === []) {
            throw new InvalidArgumentException('Mindestens einen Puffer angeben.');
        }

        $byOrder = [];
        foreach ($stations as $st) {
            $byOrder[(int) $st['station_order']] = (int) $st['id'];
        }
        $maxOrder = $n;
        $seen = [];
        $normalized = [];
        foreach ($buffers as $i => $row) {
            if (!is_array($row)) {
                throw new InvalidArgumentException('Puffer #' . ($i + 1) . ': ungültige Daten.');
            }
            $order = (int) ($row['after_station_order'] ?? 0);
            if ($order < 1 || $order >= $maxOrder) {
                throw new InvalidArgumentException(
                    'Puffer #' . ($i + 1) . ': after_station_order muss 1…' . ($maxOrder - 1) . ' sein (nicht nach letzter Station).'
                );
            }
            if (isset($seen[$order])) {
                throw new InvalidArgumentException('Doppelter Puffer für Station-Order ' . $order . '.');
            }
            $seen[$order] = true;
            if (!isset($byOrder[$order])) {
                throw new InvalidArgumentException('Puffer #' . ($i + 1) . ': Station-Order fehlt.');
            }
            $capacity = self::parseQty((string) ($row['capacity_qty'] ?? ''), 'Kapazität');
            if ($capacity <= 0) {
                throw new InvalidArgumentException('Kapazität muss größer als 0 sein.');
            }
            $minQty = self::parseQty((string) ($row['min_qty'] ?? '0'), 'Mindestmenge', true);
            if ($minQty < 0) {
                throw new InvalidArgumentException('Mindestmenge darf nicht negativ sein.');
            }
            if ($minQty > $capacity) {
                throw new InvalidArgumentException('Mindestmenge darf die Kapazität nicht überschreiten.');
            }
            $current = self::parseQty((string) ($row['current_qty'] ?? '0'), 'Ist-Menge', true);
            if ($current < 0) {
                throw new InvalidArgumentException('Ist-Menge darf nicht negativ sein.');
            }
            $unit = trim((string) ($row['unit'] ?? 'Stk'));
            if ($unit === '') {
                $unit = 'Stk';
            }
            if (mb_strlen($unit) > 32) {
                $unit = mb_substr($unit, 0, 32);
            }
            $normalized[] = [
                'after_station_id' => $byOrder[$order],
                'capacity_qty' => $capacity,
                'min_qty' => $minQty,
                'unit' => $unit,
                'current_qty' => $current,
            ];
        }

        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            $del = $pdo->prepare('DELETE FROM dg_production_line_buffers WHERE line_id = :line_id');
            $del->execute(['line_id' => $lineId]);
            $ins = $pdo->prepare(
                'INSERT INTO dg_production_line_buffers
                    (line_id, after_station_id, capacity_qty, min_qty, unit, current_qty)
                 VALUES
                    (:line_id, :after_station_id, :capacity_qty, :min_qty, :unit, :current_qty)'
            );
            foreach ($normalized as $row) {
                $ins->execute([
                    'line_id' => $lineId,
                    'after_station_id' => $row['after_station_id'],
                    'capacity_qty' => $row['capacity_qty'],
                    'min_qty' => $row['min_qty'],
                    'unit' => $row['unit'],
                    'current_qty' => $row['current_qty'],
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

    /** Nur Simulations-/manueller Ist-Stand — keine Lagerbuchung. */
    public static function setBufferCurrentQty(int $bufferId, float|string $qty): void
    {
        self::ensureReady();
        if (!Database::isConfigured()) {
            throw new RuntimeException('Datenbank nicht verbunden.');
        }
        if ($bufferId <= 0) {
            throw new InvalidArgumentException('Puffer nicht gefunden.');
        }
        $current = self::parseQty((string) $qty, 'Ist-Menge', true);
        if ($current < 0) {
            throw new InvalidArgumentException('Ist-Menge darf nicht negativ sein.');
        }
        $pdo = Database::pdo();
        $find = $pdo->prepare('SELECT id, capacity_qty FROM dg_production_line_buffers WHERE id = :id LIMIT 1');
        $find->execute(['id' => $bufferId]);
        $row = $find->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            throw new InvalidArgumentException('Puffer nicht gefunden.');
        }
        $cap = (float) $row['capacity_qty'];
        if ($current > $cap) {
            throw new InvalidArgumentException('Ist-Menge darf die Kapazität nicht überschreiten.');
        }
        $upd = $pdo->prepare(
            'UPDATE dg_production_line_buffers SET current_qty = :current_qty WHERE id = :id'
        );
        $upd->execute([
            'current_qty' => $current,
            'id' => $bufferId,
        ]);
    }

    /**
     * @return list<array{after_station_order: int, capacity_qty: float, min_qty: float, unit: string, current_qty: float}>
     */
    private static function snapshotBuffersByStationOrder(PDO $pdo, int $lineId): array
    {
        try {
            $stmt = $pdo->prepare(
                'SELECT s.station_order AS after_station_order,
                        b.capacity_qty, b.min_qty, b.unit, b.current_qty
                 FROM dg_production_line_buffers b
                 INNER JOIN dg_production_line_stations s ON s.id = b.after_station_id
                 WHERE b.line_id = :line_id'
            );
            $stmt->execute(['line_id' => $lineId]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e) {
            // Tabelle ggf. noch nicht migriert — Stations-Rewrite trotzdem erlauben
            return [];
        }
        $out = [];
        foreach ($rows as $r) {
            $out[] = [
                'after_station_order' => (int) $r['after_station_order'],
                'capacity_qty' => (float) $r['capacity_qty'],
                'min_qty' => (float) $r['min_qty'],
                'unit' => (string) $r['unit'],
                'current_qty' => (float) $r['current_qty'],
            ];
        }

        return $out;
    }

    /**
     * @param list<array{after_station_order: int, capacity_qty: float, min_qty: float, unit: string, current_qty: float}> $snap
     */
    private static function restoreBuffersFromSnapshot(PDO $pdo, int $lineId, array $snap, int $stationCount): void
    {
        if ($snap === [] || $stationCount < 2) {
            return;
        }
        $st = $pdo->prepare(
            'SELECT id, station_order FROM dg_production_line_stations WHERE line_id = :line_id'
        );
        $st->execute(['line_id' => $lineId]);
        $map = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $map[(int) $row['station_order']] = (int) $row['id'];
        }
        $ins = $pdo->prepare(
            'INSERT INTO dg_production_line_buffers
                (line_id, after_station_id, capacity_qty, min_qty, unit, current_qty)
             VALUES
                (:line_id, :after_station_id, :capacity_qty, :min_qty, :unit, :current_qty)'
        );
        foreach ($snap as $b) {
            $order = (int) $b['after_station_order'];
            if ($order < 1 || $order >= $stationCount || !isset($map[$order])) {
                continue;
            }
            $ins->execute([
                'line_id' => $lineId,
                'after_station_id' => $map[$order],
                'capacity_qty' => $b['capacity_qty'],
                'min_qty' => $b['min_qty'],
                'unit' => $b['unit'],
                'current_qty' => $b['current_qty'],
            ]);
        }
    }

    private static function parseQty(string $raw, string $label, bool $allowEmptyZero = false): float
    {
        $raw = trim(str_replace([' ', ','], ['', '.'], $raw));
        if ($raw === '') {
            if ($allowEmptyZero) {
                return 0.0;
            }
            throw new InvalidArgumentException($label . ': ungültige Zahl.');
        }
        if (!is_numeric($raw)) {
            throw new InvalidArgumentException($label . ': ungültige Zahl.');
        }

        return (float) $raw;
    }
}
