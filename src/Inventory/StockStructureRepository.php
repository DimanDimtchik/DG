<?php
declare(strict_types=1);

/** Stammdaten Lagerstruktur: Ort → Halle → Regal/Stellplätze → Platz */
final class StockStructureRepository
{
    /** @return list<array<string, mixed>> */
    public static function allLocations(): array
    {
        if (!Database::isConfigured()) {
            return [];
        }

        $rows = Database::pdo()->query(
            'SELECT l.*,
                    (SELECT COUNT(*) FROM dg_stock_halls h WHERE h.location_id = l.id) AS hall_count,
                    (SELECT COUNT(*) FROM dg_stock_shelves s WHERE s.location_id = l.id AND s.shelf_type = \'shelf\') AS shelf_count,
                    (SELECT COUNT(*) FROM dg_stock_shelves s WHERE s.location_id = l.id AND s.shelf_type = \'floor_slots\') AS floor_slot_group_count,
                    (SELECT COUNT(*) FROM dg_stock_places p WHERE p.location_id = l.id) AS place_count
             FROM dg_stock_locations l
             ORDER BY l.sort_order ASC, l.code ASC'
        )->fetchAll(PDO::FETCH_ASSOC);

        return array_map([self::class, 'enrichLocationRow'], $rows ?: []);
    }

    /** @return array<string, mixed>|null */
    public static function findLocation(int $id): ?array
    {
        if ($id < 1 || !Database::isConfigured()) {
            return null;
        }

        $stmt = Database::pdo()->prepare('SELECT * FROM dg_stock_locations WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? self::enrichLocationRow($row) : null;
    }

    /** @param array<string, mixed> $input */
    public static function saveLocation(array $input): int
    {
        $id = (int) ($input['id'] ?? 0);
        $code = StockPositionCode::sanitizeSegment((string) ($input['code'] ?? ''));
        $name = trim((string) ($input['name'] ?? ''));
        $address = trim((string) ($input['address'] ?? ''));
        $functionText = trim((string) ($input['function_text'] ?? ''));
        $notes = trim((string) ($input['notes'] ?? ''));
        $sortOrder = (int) ($input['sort_order'] ?? 0);
        $isActive = !empty($input['is_active']) ? 1 : 0;

        if ($code === '') {
            throw new InvalidArgumentException('Ortkode ist erforderlich.');
        }

        self::assertUniqueLocationCode($code, $id);

        $pdo = Database::pdo();
        $params = [
            'code' => $code,
            'name' => $name,
            'address' => $address !== '' ? $address : null,
            'function_text' => $functionText,
            'notes' => $notes !== '' ? $notes : null,
            'sort_order' => $sortOrder,
            'is_active' => $isActive,
        ];

        if ($id > 0) {
            $params['id'] = $id;
            $pdo->prepare(
                'UPDATE dg_stock_locations
                 SET code = :code, name = :name, address = :address, function_text = :function_text,
                     notes = :notes, sort_order = :sort_order, is_active = :is_active
                 WHERE id = :id'
            )->execute($params);

            return $id;
        }

        $pdo->prepare(
            'INSERT INTO dg_stock_locations (code, name, address, function_text, notes, sort_order, is_active)
             VALUES (:code, :name, :address, :function_text, :notes, :sort_order, :is_active)'
        )->execute($params);

        return (int) $pdo->lastInsertId();
    }

    public static function deleteLocation(int $id): void
    {
        if ($id < 1) {
            throw new InvalidArgumentException('Ungültiger Lagerort.');
        }

        $pdo = Database::pdo();
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM dg_calendar_articles WHERE stock_location_id = :id');
        $stmt->execute(['id' => $id]);
        if ((int) $stmt->fetchColumn() > 0) {
            throw new InvalidArgumentException('Lagerort wird noch von Artikeln verwendet und kann nicht gelöscht werden.');
        }

        $pdo->prepare('DELETE FROM dg_stock_locations WHERE id = :id')->execute(['id' => $id]);
    }

    /** @return list<array<string, mixed>> */
    public static function allHalls(?int $locationId = null): array
    {
        if (!Database::isConfigured()) {
            return [];
        }

        $sql = 'SELECT h.*, l.code AS location_code, l.name AS location_name,
                       (SELECT COUNT(*) FROM dg_stock_shelves s WHERE s.hall_id = h.id AND s.shelf_type = \'shelf\') AS shelf_count,
                       (SELECT COUNT(*) FROM dg_stock_shelves s WHERE s.hall_id = h.id AND s.shelf_type = \'floor_slots\') AS floor_slot_group_count,
                       (SELECT COUNT(*) FROM dg_stock_places p WHERE p.hall_id = h.id) AS place_count
                FROM dg_stock_halls h
                INNER JOIN dg_stock_locations l ON l.id = h.location_id';
        $params = [];
        if ($locationId !== null && $locationId > 0) {
            $sql .= ' WHERE h.location_id = :location_id';
            $params['location_id'] = $locationId;
        }
        $sql .= ' ORDER BY l.sort_order ASC, l.code ASC, h.sort_order ASC, h.code ASC';

        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return array_map([self::class, 'enrichHallRow'], $rows ?: []);
    }

    /** @return array<string, mixed>|null */
    public static function findHall(int $id): ?array
    {
        if ($id < 1 || !Database::isConfigured()) {
            return null;
        }

        $stmt = Database::pdo()->prepare(
            'SELECT h.*, l.code AS location_code, l.name AS location_name
             FROM dg_stock_halls h
             INNER JOIN dg_stock_locations l ON l.id = h.location_id
             WHERE h.id = :id LIMIT 1'
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? self::enrichHallRow($row) : null;
    }

    /** @param array<string, mixed> $input */
    public static function saveHall(array $input): int
    {
        $id = (int) ($input['id'] ?? 0);
        $locationId = (int) ($input['location_id'] ?? 0);
        $code = StockPositionCode::sanitizeSegment((string) ($input['code'] ?? ''));
        $usageText = trim((string) ($input['usage_text'] ?? ''));
        $notes = trim((string) ($input['notes'] ?? ''));
        $sortOrder = (int) ($input['sort_order'] ?? 0);
        $isActive = !empty($input['is_active']) ? 1 : 0;

        if ($locationId < 1 || self::findLocation($locationId) === null) {
            throw new InvalidArgumentException('Bitte einen gültigen Lagerort wählen.');
        }
        if ($code === '') {
            throw new InvalidArgumentException('Hallenkode ist erforderlich.');
        }

        self::assertUniqueHallCode($locationId, $code, $id);

        $pdo = Database::pdo();
        $params = [
            'location_id' => $locationId,
            'code' => $code,
            'usage_text' => $usageText,
            'notes' => $notes !== '' ? $notes : null,
            'sort_order' => $sortOrder,
            'is_active' => $isActive,
        ];

        if ($id > 0) {
            $params['id'] = $id;
            $pdo->prepare(
                'UPDATE dg_stock_halls
                 SET location_id = :location_id, code = :code, usage_text = :usage_text,
                     notes = :notes, sort_order = :sort_order, is_active = :is_active
                 WHERE id = :id'
            )->execute($params);
            $pdo->prepare('UPDATE dg_stock_shelves SET location_id = :location_id WHERE hall_id = :id')
                ->execute(['location_id' => $locationId, 'id' => $id]);
            $pdo->prepare('UPDATE dg_stock_places SET location_id = :location_id WHERE hall_id = :id')
                ->execute(['location_id' => $locationId, 'id' => $id]);

            return $id;
        }

        $pdo->prepare(
            'INSERT INTO dg_stock_halls (location_id, code, usage_text, notes, sort_order, is_active)
             VALUES (:location_id, :code, :usage_text, :notes, :sort_order, :is_active)'
        )->execute($params);

        return (int) $pdo->lastInsertId();
    }

    public static function deleteHall(int $id): void
    {
        if ($id < 1) {
            throw new InvalidArgumentException('Ungültige Halle.');
        }

        $pdo = Database::pdo();
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM dg_calendar_articles WHERE stock_hall_id = :id');
        $stmt->execute(['id' => $id]);
        if ((int) $stmt->fetchColumn() > 0) {
            throw new InvalidArgumentException('Halle wird noch von Artikeln verwendet und kann nicht gelöscht werden.');
        }

        $pdo->prepare('DELETE FROM dg_stock_halls WHERE id = :id')->execute(['id' => $id]);
    }

    /** @return list<array<string, mixed>> */
    public static function allShelves(?int $hallId = null, ?int $locationId = null): array
    {
        if (!Database::isConfigured()) {
            return [];
        }

        $sql = 'SELECT s.*, l.code AS location_code, h.code AS hall_code, h.usage_text AS hall_usage,
                       (SELECT COUNT(*) FROM dg_stock_places p WHERE p.shelf_id = s.id) AS place_count
                FROM dg_stock_shelves s
                INNER JOIN dg_stock_locations l ON l.id = s.location_id
                INNER JOIN dg_stock_halls h ON h.id = s.hall_id
                WHERE 1=1';
        $params = [];
        if ($hallId !== null && $hallId > 0) {
            $sql .= ' AND s.hall_id = :hall_id';
            $params['hall_id'] = $hallId;
        }
        if ($locationId !== null && $locationId > 0) {
            $sql .= ' AND s.location_id = :location_id';
            $params['location_id'] = $locationId;
        }
        $sql .= ' ORDER BY l.code ASC, h.code ASC, s.sort_order ASC, s.code ASC';

        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return array_map([self::class, 'enrichShelfRow'], $rows ?: []);
    }

    /** @return array<string, mixed>|null */
    public static function findShelf(int $id): ?array
    {
        if ($id < 1 || !Database::isConfigured()) {
            return null;
        }

        $stmt = Database::pdo()->prepare(
            'SELECT s.*, l.code AS location_code, h.code AS hall_code
             FROM dg_stock_shelves s
             INNER JOIN dg_stock_locations l ON l.id = s.location_id
             INNER JOIN dg_stock_halls h ON h.id = s.hall_id
             WHERE s.id = :id LIMIT 1'
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? self::enrichShelfRow($row) : null;
    }

    /** @param array<string, mixed> $input */
    public static function saveShelf(array $input): int
    {
        $id = (int) ($input['id'] ?? 0);
        $locationId = (int) ($input['location_id'] ?? 0);
        $hallId = (int) ($input['hall_id'] ?? 0);
        $code = StockPositionCode::sanitizeSegment((string) ($input['code'] ?? ''));
        $shelfType = (string) ($input['shelf_type'] ?? 'shelf');
        if (!in_array($shelfType, ['shelf', 'floor_slots'], true)) {
            $shelfType = 'shelf';
        }
        $capacityUnits = round((float) str_replace(',', '.', (string) ($input['capacity_units'] ?? 0)), 3);
        if ($capacityUnits < 0) {
            $capacityUnits = 0;
        }
        $capacityLabel = trim((string) ($input['capacity_label'] ?? ''));
        $slotCount = max(0, (int) ($input['slot_count'] ?? 0));
        $sortOrder = (int) ($input['sort_order'] ?? 0);
        $isActive = !empty($input['is_active']) ? 1 : 0;

        $hall = self::findHall($hallId);
        if ($hall === null) {
            throw new InvalidArgumentException('Bitte eine gültige Halle wählen.');
        }
        if ($locationId < 1) {
            $locationId = (int) $hall['location_id'];
        }
        if ((int) $hall['location_id'] !== $locationId) {
            throw new InvalidArgumentException('Halle gehört nicht zum gewählten Lagerort.');
        }
        if ($code === '') {
            throw new InvalidArgumentException('Regalkode ist erforderlich.');
        }
        if ($slotCount < 1) {
            throw new InvalidArgumentException('Anzahl Stellplätze muss mindestens 1 sein.');
        }

        self::assertUniqueShelfCode($hallId, $code, $id);

        $pdo = Database::pdo();
        $params = [
            'location_id' => $locationId,
            'hall_id' => $hallId,
            'code' => $code,
            'shelf_type' => $shelfType,
            'capacity_units' => $capacityUnits,
            'capacity_label' => $capacityLabel,
            'slot_count' => $slotCount,
            'sort_order' => $sortOrder,
            'is_active' => $isActive,
        ];

        if ($id > 0) {
            $params['id'] = $id;
            $pdo->prepare(
                'UPDATE dg_stock_shelves
                 SET location_id = :location_id, hall_id = :hall_id, code = :code, shelf_type = :shelf_type,
                     capacity_units = :capacity_units, capacity_label = :capacity_label, slot_count = :slot_count,
                     sort_order = :sort_order, is_active = :is_active
                 WHERE id = :id'
            )->execute($params);
            self::syncPlacesForShelf($id);

            return $id;
        }

        $pdo->prepare(
            'INSERT INTO dg_stock_shelves
             (location_id, hall_id, code, shelf_type, capacity_units, capacity_label, slot_count, sort_order, is_active)
             VALUES
             (:location_id, :hall_id, :code, :shelf_type, :capacity_units, :capacity_label, :slot_count, :sort_order, :is_active)'
        )->execute($params);
        $newId = (int) $pdo->lastInsertId();
        self::syncPlacesForShelf($newId);

        return $newId;
    }

    public static function deleteShelf(int $id): void
    {
        if ($id < 1) {
            throw new InvalidArgumentException('Ungültiges Regal.');
        }

        $pdo = Database::pdo();
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM dg_calendar_articles WHERE stock_shelf_id = :id');
        $stmt->execute(['id' => $id]);
        if ((int) $stmt->fetchColumn() > 0) {
            throw new InvalidArgumentException('Regal wird noch von Artikeln verwendet und kann nicht gelöscht werden.');
        }

        $pdo->prepare('DELETE FROM dg_stock_shelves WHERE id = :id')->execute(['id' => $id]);
    }

    /** @return list<array<string, mixed>> */
    public static function placesForShelf(int $shelfId): array
    {
        if ($shelfId < 1 || !Database::isConfigured()) {
            return [];
        }

        $stmt = Database::pdo()->prepare(
            'SELECT p.*, l.code AS location_code, h.code AS hall_code, s.code AS shelf_code,
                    a.title AS fixed_article_title, a.article_number AS fixed_article_number
             FROM dg_stock_places p
             INNER JOIN dg_stock_locations l ON l.id = p.location_id
             INNER JOIN dg_stock_halls h ON h.id = p.hall_id
             INNER JOIN dg_stock_shelves s ON s.id = p.shelf_id
             LEFT JOIN dg_calendar_articles a ON a.id = p.fixed_article_id
             WHERE p.shelf_id = :shelf_id
             ORDER BY p.sort_order ASC, p.code ASC'
        );
        $stmt->execute(['shelf_id' => $shelfId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return array_map([self::class, 'enrichPlaceRow'], $rows ?: []);
    }

    /** @param array<string, mixed> $input */
    public static function savePlace(array $input): int
    {
        $id = (int) ($input['id'] ?? 0);
        $placeMode = (string) ($input['place_mode'] ?? 'flexible');
        if (!in_array($placeMode, ['fixed', 'flexible'], true)) {
            $placeMode = 'flexible';
        }
        $fixedArticleId = (int) ($input['fixed_article_id'] ?? 0);
        $isActive = !empty($input['is_active']) ? 1 : 0;

        if ($id < 1) {
            throw new InvalidArgumentException('Ungültiger Stellplatz.');
        }

        if ($placeMode === 'fixed' && $fixedArticleId < 1) {
            throw new InvalidArgumentException('Fester Platz benötigt einen zugeordneten Artikel.');
        }
        if ($placeMode === 'flexible') {
            $fixedArticleId = 0;
        }

        $pdo = Database::pdo();
        $pdo->prepare(
            'UPDATE dg_stock_places
             SET place_mode = :place_mode, fixed_article_id = :fixed_article_id, is_active = :is_active
             WHERE id = :id'
        )->execute([
            'id' => $id,
            'place_mode' => $placeMode,
            'fixed_article_id' => $fixedArticleId > 0 ? $fixedArticleId : null,
            'is_active' => $isActive,
        ]);

        return $id;
    }

    /** @param array<int, array<string, mixed>> $placesInput */
    public static function savePlacesFromPost(int $shelfId, array $placesInput): void
    {
        foreach ($placesInput as $placeInput) {
            if (!is_array($placeInput)) {
                continue;
            }
            $placeId = (int) ($placeInput['id'] ?? 0);
            if ($placeId < 1) {
                continue;
            }
            $existing = self::findPlace($placeId);
            if ($existing === null || (int) $existing['shelf_id'] !== $shelfId) {
                continue;
            }
            self::savePlace($placeInput);
        }
    }

    /** @return array<string, mixed>|null */
    public static function findPlace(int $id): ?array
    {
        if ($id < 1 || !Database::isConfigured()) {
            return null;
        }

        $stmt = Database::pdo()->prepare(
            'SELECT p.*, l.code AS location_code, h.code AS hall_code, s.code AS shelf_code
             FROM dg_stock_places p
             INNER JOIN dg_stock_locations l ON l.id = p.location_id
             INNER JOIN dg_stock_halls h ON h.id = p.hall_id
             INNER JOIN dg_stock_shelves s ON s.id = p.shelf_id
             WHERE p.id = :id LIMIT 1'
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? self::enrichPlaceRow($row) : null;
    }

    /** @return list<array{id: int, label: string}> */
    public static function locationOptions(): array
    {
        $options = [];
        foreach (self::allLocations() as $row) {
            if (empty($row['is_active'])) {
                continue;
            }
            $label = (string) $row['code'];
            $name = trim((string) ($row['name'] ?? ''));
            if ($name !== '') {
                $label .= ' — ' . $name;
            }
            $options[] = ['id' => (int) $row['id'], 'label' => $label, 'code' => (string) $row['code']];
        }

        return $options;
    }

    /** @return list<array{id: int, location_id: int, label: string, code: string}> */
    public static function hallOptions(?int $locationId = null): array
    {
        $options = [];
        foreach (self::allHalls($locationId) as $row) {
            if (empty($row['is_active'])) {
                continue;
            }
            $options[] = [
                'id' => (int) $row['id'],
                'location_id' => (int) $row['location_id'],
                'label' => (string) $row['location_code'] . ' / ' . (string) $row['code'],
                'code' => (string) $row['code'],
            ];
        }

        return $options;
    }

    /** @return list<array{id: int, hall_id: int, location_id: int, label: string, code: string, shelf_type: string}> */
    public static function shelfOptions(?int $hallId = null, ?int $locationId = null): array
    {
        $options = [];
        foreach (self::allShelves($hallId, $locationId) as $row) {
            if (empty($row['is_active'])) {
                continue;
            }
            $typeLabel = ($row['shelf_type'] ?? '') === 'floor_slots' ? 'Stellplätze' : 'Regal';
            $options[] = [
                'id' => (int) $row['id'],
                'hall_id' => (int) $row['hall_id'],
                'location_id' => (int) $row['location_id'],
                'label' => (string) $row['location_code'] . ' / ' . (string) $row['hall_code'] . ' / ' . (string) $row['code'] . ' (' . $typeLabel . ')',
                'code' => (string) $row['code'],
                'shelf_type' => (string) $row['shelf_type'],
            ];
        }

        return $options;
    }

    /** @return list<array{id: int, shelf_id: int, label: string, code: string, place_mode: string}> */
    public static function placeOptions(?int $shelfId = null, ?int $articleId = null): array
    {
        if (!Database::isConfigured()) {
            return [];
        }

        $sql = 'SELECT p.*, l.code AS location_code, h.code AS hall_code, s.code AS shelf_code
                FROM dg_stock_places p
                INNER JOIN dg_stock_locations l ON l.id = p.location_id
                INNER JOIN dg_stock_halls h ON h.id = p.hall_id
                INNER JOIN dg_stock_shelves s ON s.id = p.shelf_id
                WHERE p.is_active = 1';
        $params = [];
        if ($shelfId !== null && $shelfId > 0) {
            $sql .= ' AND p.shelf_id = :shelf_id';
            $params['shelf_id'] = $shelfId;
        }
        $sql .= ' ORDER BY l.code ASC, h.code ASC, s.code ASC, p.sort_order ASC, p.code ASC';

        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $options = [];
        foreach ($rows as $row) {
            $placeMode = (string) ($row['place_mode'] ?? 'flexible');
            $fixedArticleId = (int) ($row['fixed_article_id'] ?? 0);
            if ($placeMode === 'fixed' && $fixedArticleId > 0 && $articleId !== null && $articleId > 0 && $fixedArticleId !== $articleId) {
                continue;
            }
            $positionCode = StockPositionCode::compose(
                (string) $row['location_code'],
                (string) $row['hall_code'],
                (string) $row['shelf_code'],
                (string) $row['code'],
            );
            $modeLabel = $placeMode === 'fixed' ? 'fest' : 'flexibel';
            $options[] = [
                'id' => (int) $row['id'],
                'shelf_id' => (int) $row['shelf_id'],
                'label' => $positionCode . ' (' . $modeLabel . ')',
                'code' => (string) $row['code'],
                'place_mode' => $placeMode,
                'position_code' => $positionCode,
                'fixed_article_id' => (int) ($row['fixed_article_id'] ?? 0),
            ];
        }

        return $options;
    }

    public static function syncPlacesForShelf(int $shelfId): void
    {
        $shelf = self::findShelf($shelfId);
        if ($shelf === null) {
            return;
        }

        $slotCount = max(0, (int) ($shelf['slot_count'] ?? 0));
        $pdo = Database::pdo();
        $existing = self::placesForShelf($shelfId);
        $existingByCode = [];
        foreach ($existing as $place) {
            $existingByCode[(string) $place['code']] = $place;
        }

        for ($i = 1; $i <= $slotCount; ++$i) {
            $code = 'P' . str_pad((string) $i, 2, '0', STR_PAD_LEFT);
            if (isset($existingByCode[$code])) {
                continue;
            }
            $pdo->prepare(
                'INSERT INTO dg_stock_places (shelf_id, location_id, hall_id, code, place_mode, sort_order, is_active)
                 VALUES (:shelf_id, :location_id, :hall_id, :code, \'flexible\', :sort_order, 1)'
            )->execute([
                'shelf_id' => $shelfId,
                'location_id' => (int) $shelf['location_id'],
                'hall_id' => (int) $shelf['hall_id'],
                'code' => $code,
                'sort_order' => $i,
            ]);
        }

        if ($slotCount < count($existing)) {
            $stmt = $pdo->prepare(
                'SELECT id FROM dg_stock_places
                 WHERE shelf_id = :shelf_id AND sort_order > :slot_count'
            );
            $stmt->execute(['shelf_id' => $shelfId, 'slot_count' => $slotCount]);
            $removeIds = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN) ?: []);
            foreach ($removeIds as $removeId) {
                $check = $pdo->prepare('SELECT COUNT(*) FROM dg_calendar_articles WHERE stock_place_id = :id');
                $check->execute(['id' => $removeId]);
                if ((int) $check->fetchColumn() > 0) {
                    continue;
                }
                $pdo->prepare('DELETE FROM dg_stock_places WHERE id = :id')->execute(['id' => $removeId]);
            }
        }
    }

    /** @return list<array<string, mixed>> */
    public static function hallsForLocationSelect(int $locationId): array
    {
        return self::allHalls($locationId > 0 ? $locationId : null);
    }

    /** @return list<array<string, mixed>> */
    public static function shelvesForHallSelect(int $hallId): array
    {
        return self::allShelves($hallId > 0 ? $hallId : null, null);
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private static function enrichLocationRow(array $row): array
    {
        $row['display_label'] = trim((string) ($row['name'] ?? '')) !== ''
            ? (string) $row['name']
            : (string) $row['code'];

        return $row;
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private static function enrichHallRow(array $row): array
    {
        $row['location_label'] = (string) ($row['location_code'] ?? '');
        $row['summary'] = (int) ($row['shelf_count'] ?? 0) . ' Regale · '
            . (int) ($row['floor_slot_group_count'] ?? 0) . ' Stellplatz-Gruppen · '
            . (int) ($row['place_count'] ?? 0) . ' Plätze';

        return $row;
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private static function enrichShelfRow(array $row): array
    {
        $row['type_label'] = ($row['shelf_type'] ?? '') === 'floor_slots' ? 'Stellplätze' : 'Regal';
        $row['position_prefix'] = StockPositionCode::compose(
            (string) ($row['location_code'] ?? ''),
            (string) ($row['hall_code'] ?? ''),
            (string) ($row['code'] ?? ''),
            '',
        );
        if ($row['position_prefix'] !== '' && str_ends_with($row['position_prefix'], '-')) {
            $row['position_prefix'] = rtrim($row['position_prefix'], '-');
        }

        return $row;
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private static function enrichPlaceRow(array $row): array
    {
        $row['position_code'] = StockPositionCode::compose(
            (string) ($row['location_code'] ?? ''),
            (string) ($row['hall_code'] ?? ''),
            (string) ($row['shelf_code'] ?? ''),
            (string) ($row['code'] ?? ''),
        );
        $row['mode_label'] = ($row['place_mode'] ?? '') === 'fixed' ? 'fest' : 'flexibel';
        $row['is_occupied'] = StockPlaceService::isPlaceOccupied((int) ($row['id'] ?? 0));

        return $row;
    }

    private static function assertUniqueLocationCode(string $code, int $excludeId): void
    {
        $sql = 'SELECT id FROM dg_stock_locations WHERE code = :code';
        $params = ['code' => $code];
        if ($excludeId > 0) {
            $sql .= ' AND id != :id';
            $params['id'] = $excludeId;
        }
        $stmt = Database::pdo()->prepare($sql . ' LIMIT 1');
        $stmt->execute($params);
        if ($stmt->fetchColumn() !== false) {
            throw new InvalidArgumentException('Ortkode „' . $code . '“ ist bereits vergeben.');
        }
    }

    private static function assertUniqueHallCode(int $locationId, string $code, int $excludeId): void
    {
        $sql = 'SELECT id FROM dg_stock_halls WHERE location_id = :location_id AND code = :code';
        $params = ['location_id' => $locationId, 'code' => $code];
        if ($excludeId > 0) {
            $sql .= ' AND id != :id';
            $params['id'] = $excludeId;
        }
        $stmt = Database::pdo()->prepare($sql . ' LIMIT 1');
        $stmt->execute($params);
        if ($stmt->fetchColumn() !== false) {
            throw new InvalidArgumentException('Hallenkode „' . $code . '“ ist in diesem Ort bereits vergeben.');
        }
    }

    private static function assertUniqueShelfCode(int $hallId, string $code, int $excludeId): void
    {
        $sql = 'SELECT id FROM dg_stock_shelves WHERE hall_id = :hall_id AND code = :code';
        $params = ['hall_id' => $hallId, 'code' => $code];
        if ($excludeId > 0) {
            $sql .= ' AND id != :id';
            $params['id'] = $excludeId;
        }
        $stmt = Database::pdo()->prepare($sql . ' LIMIT 1');
        $stmt->execute($params);
        if ($stmt->fetchColumn() !== false) {
            throw new InvalidArgumentException('Regalkode „' . $code . '“ ist in dieser Halle bereits vergeben.');
        }
    }
}
