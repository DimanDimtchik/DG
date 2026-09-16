<?php
declare(strict_types=1);

/** Lagerplatz-Zuordnung: fest vs. flexibel, freie Plätze vorschlagen */
final class StockPlaceService
{
    public static function isPlaceOccupied(int $placeId): bool
    {
        if ($placeId < 1 || !Database::isConfigured()) {
            return false;
        }

        $pdo = Database::pdo();
        $stmt = $pdo->prepare(
            'SELECT place_mode, fixed_article_id FROM dg_stock_places WHERE id = :id AND is_active = 1 LIMIT 1'
        );
        $stmt->execute(['id' => $placeId]);
        $place = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($place === false) {
            return false;
        }

        if (($place['place_mode'] ?? '') === 'fixed' && (int) ($place['fixed_article_id'] ?? 0) > 0) {
            return true;
        }

        $stmt = $pdo->prepare('SELECT COUNT(*) FROM dg_stock_place_occupancy WHERE place_id = :place_id');
        $stmt->execute(['place_id' => $placeId]);

        return (int) $stmt->fetchColumn() > 0;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function suggestFreePlaces(?int $hallId = null, ?int $locationId = null, int $limit = 10): array
    {
        if (!Database::isConfigured()) {
            return [];
        }

        $sql = 'SELECT p.*, l.code AS location_code, h.code AS hall_code, s.code AS shelf_code
                FROM dg_stock_places p
                INNER JOIN dg_stock_locations l ON l.id = p.location_id
                INNER JOIN dg_stock_halls h ON h.id = p.hall_id
                INNER JOIN dg_stock_shelves s ON s.id = p.shelf_id
                WHERE p.is_active = 1 AND p.place_mode = \'flexible\'';
        $params = [];
        if ($hallId !== null && $hallId > 0) {
            $sql .= ' AND p.hall_id = :hall_id';
            $params['hall_id'] = $hallId;
        } elseif ($locationId !== null && $locationId > 0) {
            $sql .= ' AND p.location_id = :location_id';
            $params['location_id'] = $locationId;
        }
        $sql .= ' ORDER BY l.code ASC, h.code ASC, s.code ASC, p.sort_order ASC LIMIT ' . max(1, min(50, $limit * 3));

        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $free = [];
        foreach ($rows as $row) {
            $placeId = (int) ($row['id'] ?? 0);
            if ($placeId < 1 || self::isPlaceOccupied($placeId)) {
                continue;
            }
            $row['position_code'] = StockPositionCode::compose(
                (string) $row['location_code'],
                (string) $row['hall_code'],
                (string) ($row['shelf_code'] ?? ''),
                (string) $row['code'],
            );
            $free[] = $row;
            if (count($free) >= $limit) {
                break;
            }
        }

        return $free;
    }

    /**
     * @param array<string, mixed> $input
     * @return array{
     *   location_id: int|null,
     *   hall_id: int|null,
     *   shelf_id: int|null,
     *   place_id: int|null,
     *   place_mode: string,
     *   ort: string,
     *   halle: string,
     *   regal: string,
     *   platz: string
     * }
     */
    public static function resolveArticlePositionFromInput(array $input, int $articleId = 0): array
    {
        $placeId = (int) ($input['stock_place_id'] ?? 0);
        $placeMode = (string) ($input['stock_place_mode'] ?? 'flexible');
        if (!in_array($placeMode, ['fixed', 'flexible'], true)) {
            $placeMode = 'flexible';
        }

        $locationId = (int) ($input['stock_location_id'] ?? 0);
        $hallId = (int) ($input['stock_hall_id'] ?? 0);
        $shelfId = (int) ($input['stock_shelf_id'] ?? 0);

        $ort = '';
        $halle = '';
        $regal = '';
        $platz = '';

        if ($placeId > 0) {
            $place = StockStructureRepository::findPlace($placeId);
            if ($place === null) {
                throw new InvalidArgumentException('Gewählter Lagerplatz existiert nicht.');
            }
            if (($place['place_mode'] ?? '') === 'fixed') {
                $fixedArticleId = (int) ($place['fixed_article_id'] ?? 0);
                if ($fixedArticleId > 0 && $articleId > 0 && $fixedArticleId !== $articleId) {
                    throw new InvalidArgumentException('Dieser Platz ist fest einem anderen Artikel zugeordnet.');
                }
                $placeMode = 'fixed';
            }
            $locationId = (int) $place['location_id'];
            $hallId = (int) $place['hall_id'];
            $shelfId = (int) $place['shelf_id'];
            $ort = (string) ($place['location_code'] ?? '');
            $halle = (string) ($place['hall_code'] ?? '');
            $regal = (string) ($place['shelf_code'] ?? '');
            $platz = (string) ($place['code'] ?? '');
        } elseif ($shelfId > 0) {
            $shelf = StockStructureRepository::findShelf($shelfId);
            if ($shelf === null) {
                throw new InvalidArgumentException('Gewähltes Regal existiert nicht.');
            }
            $locationId = (int) $shelf['location_id'];
            $hallId = (int) $shelf['hall_id'];
            $ort = (string) ($shelf['location_code'] ?? '');
            $halle = (string) ($shelf['hall_code'] ?? '');
            $regal = (string) ($shelf['code'] ?? '');
            if ($placeMode === 'fixed') {
                throw new InvalidArgumentException('Fester Platz erfordert die Auswahl eines konkreten Stellplatzes.');
            }
        } elseif ($hallId > 0) {
            $hall = StockStructureRepository::findHall($hallId);
            if ($hall === null) {
                throw new InvalidArgumentException('Gewählte Halle existiert nicht.');
            }
            $locationId = (int) $hall['location_id'];
            $ort = (string) ($hall['location_code'] ?? '');
            $halle = (string) ($hall['code'] ?? '');
        } elseif ($locationId > 0) {
            $location = StockStructureRepository::findLocation($locationId);
            if ($location === null) {
                throw new InvalidArgumentException('Gewählter Lagerort existiert nicht.');
            }
            $ort = (string) ($location['code'] ?? '');
        } else {
            $legacy = StockPositionCode::fromInput($input);
            if ($legacy !== ['ort' => '', 'halle' => '', 'regal' => '', 'platz' => '']) {
                StockPositionCode::assertCompleteIfAny($legacy);
                return [
                    'location_id' => null,
                    'hall_id' => null,
                    'shelf_id' => null,
                    'place_id' => null,
                    'place_mode' => $placeMode,
                    'ort' => $legacy['ort'],
                    'halle' => $legacy['halle'],
                    'regal' => $legacy['regal'],
                    'platz' => $legacy['platz'],
                ];
            }
        }

        if ($placeMode === 'fixed' && $placeId < 1) {
            throw new InvalidArgumentException('Fester Lagerplatz: bitte Ort, Halle, Regal und Platz vollständig wählen.');
        }

        return [
            'location_id' => $locationId > 0 ? $locationId : null,
            'hall_id' => $hallId > 0 ? $hallId : null,
            'shelf_id' => $shelfId > 0 ? $shelfId : null,
            'place_id' => $placeId > 0 ? $placeId : null,
            'place_mode' => $placeMode,
            'ort' => $ort,
            'halle' => $halle,
            'regal' => $regal,
            'platz' => $platz,
        ];
    }

    public static function assignFlexiblePlace(int $articleId, int $placeId, float $quantity = 0): void
    {
        if ($articleId < 1 || $placeId < 1 || !Database::isConfigured()) {
            return;
        }

        $place = StockStructureRepository::findPlace($placeId);
        if ($place === null || ($place['place_mode'] ?? '') !== 'flexible') {
            throw new InvalidArgumentException('Nur flexible Plätze können bei Einnahme zugewiesen werden.');
        }
        if (self::isPlaceOccupied($placeId)) {
            throw new InvalidArgumentException('Lagerplatz ist bereits belegt.');
        }

        Database::pdo()->prepare(
            'INSERT INTO dg_stock_place_occupancy (place_id, article_id, quantity)
             VALUES (:place_id, :article_id, :quantity)
             ON DUPLICATE KEY UPDATE quantity = VALUES(quantity)'
        )->execute([
            'place_id' => $placeId,
            'article_id' => $articleId,
            'quantity' => max(0, $quantity),
        ]);
    }

    public static function releasePlace(int $placeId, ?int $articleId = null): void
    {
        if ($placeId < 1 || !Database::isConfigured()) {
            return;
        }

        if ($articleId !== null && $articleId > 0) {
            Database::pdo()->prepare(
                'DELETE FROM dg_stock_place_occupancy WHERE place_id = :place_id AND article_id = :article_id'
            )->execute(['place_id' => $placeId, 'article_id' => $articleId]);

            return;
        }

        Database::pdo()->prepare('DELETE FROM dg_stock_place_occupancy WHERE place_id = :place_id')
            ->execute(['place_id' => $placeId]);
    }
}
