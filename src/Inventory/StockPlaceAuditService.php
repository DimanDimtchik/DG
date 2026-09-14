<?php
declare(strict_types=1);

/** Mini-Audit: Etikett scannen → Belegung, Reservierung, letzte Bewegungen */
final class StockPlaceAuditService
{
    /**
     * @return array<string, mixed>
     */
    public static function audit(string $code): array
    {
        $resolved = StockBarcodeService::resolve($code);
        $type = (string) ($resolved['type'] ?? '');

        return match ($type) {
            'place' => self::auditPlace($resolved),
            'package' => self::auditPackage($resolved),
            'shelf' => self::auditShelf($resolved),
            'hall' => self::auditHall($resolved),
            'location' => self::auditLocation($resolved),
            'article' => self::auditArticle($resolved),
            default => throw new InvalidArgumentException('Scan-Typ nicht für Platz-Check unterstützt.'),
        };
    }

    /** @param array<string, mixed> $resolved */
    private static function auditPlace(array $resolved): array
    {
        $place = $resolved['place'] ?? null;
        if (!is_array($place)) {
            throw new InvalidArgumentException('Platz nicht gefunden.');
        }

        $placeId = (int) ($place['id'] ?? 0);
        $details = self::placeDetails($placeId);

        return [
            'scan_type' => 'place',
            'title' => (string) ($place['position_code'] ?? 'Stellplatz'),
            'subtitle' => (string) ($place['kind_label'] ?? '') . ' · ' . (string) ($place['mode_label'] ?? ''),
            'place' => self::publicPlace($place),
            'reservation' => $details['reservation'],
            'occupancy' => $details['occupancy'],
            'packages' => $details['packages'],
            'article_links' => $details['article_links'],
            'movements' => $details['movements'],
            'summary' => self::buildPlaceSummary($place, $details),
        ];
    }

    /** @param array<string, mixed> $resolved */
    private static function auditPackage(array $resolved): array
    {
        $package = $resolved['package'] ?? null;
        if (!is_array($package)) {
            throw new InvalidArgumentException('Karton nicht gefunden.');
        }

        $placeId = (int) ($package['place_id'] ?? 0);
        $place = $placeId > 0 ? StockStructureRepository::findPlace($placeId) : null;
        return [
            'scan_type' => 'package',
            'title' => 'Karton ' . (string) ($package['barcode'] ?? ''),
            'subtitle' => (string) ($package['title'] ?? '') . ' · ' . self::formatQty((float) ($package['quantity'] ?? 0)) . ' ' . (string) ($package['unit'] ?? ''),
            'package' => self::publicPackage($package),
            'place' => $place !== null ? self::publicPlace($place) : null,
            'reservation' => $place !== null ? self::placeDetails($placeId)['reservation'] : null,
            'occupancy' => [],
            'packages' => [self::publicPackage($package)],
            'article_links' => [],
            'movements' => self::publicMovements(StockMovementRepository::forPackage((int) ($package['id'] ?? 0), 10)),
            'summary' => ($package['status'] ?? '') === 'in_stock' ? 'Karton im Bestand' : 'Karton ausgebucht',
        ];
    }

    /** @param array<string, mixed> $resolved */
    private static function auditShelf(array $resolved): array
    {
        $shelf = $resolved['shelf'] ?? null;
        if (!is_array($shelf)) {
            throw new InvalidArgumentException('Regal nicht gefunden.');
        }

        $shelfId = (int) ($shelf['id'] ?? 0);
        $places = StockStructureRepository::placesForShelf($shelfId);

        return [
            'scan_type' => 'shelf',
            'title' => StockLabelService::displayCodeForShelf($shelf),
            'subtitle' => (string) ($shelf['capacity_summary'] ?? ''),
            'place' => null,
            'reservation' => null,
            'occupancy' => [],
            'packages' => [],
            'article_links' => [],
            'movements' => self::publicMovements(StockMovementRepository::forShelf($shelfId, 15)),
            'places_overview' => self::placesOverview($places),
            'summary' => self::summarizePlaces($places),
        ];
    }

    /** @param array<string, mixed> $resolved */
    private static function auditHall(array $resolved): array
    {
        $hall = $resolved['hall'] ?? null;
        if (!is_array($hall)) {
            throw new InvalidArgumentException('Halle nicht gefunden.');
        }

        $hallId = (int) ($hall['id'] ?? 0);
        $places = StockStructureRepository::allPlaces(0, $hallId, 0);

        return [
            'scan_type' => 'hall',
            'title' => StockLabelService::displayCodeForHall($hall),
            'subtitle' => (string) ($hall['usage_text'] ?? ''),
            'place' => null,
            'reservation' => null,
            'occupancy' => [],
            'packages' => [],
            'article_links' => [],
            'movements' => self::publicMovements(StockMovementRepository::forHall($hallId, 15)),
            'places_overview' => self::placesOverview($places),
            'summary' => self::summarizePlaces($places),
        ];
    }

    /** @param array<string, mixed> $resolved */
    private static function auditLocation(array $resolved): array
    {
        $location = $resolved['location'] ?? null;
        if (!is_array($location)) {
            throw new InvalidArgumentException('Lagerort nicht gefunden.');
        }

        $locationId = (int) ($location['id'] ?? 0);
        $places = StockStructureRepository::allPlaces($locationId, 0, 0);

        return [
            'scan_type' => 'location',
            'title' => StockLabelService::displayCodeForLocation($location),
            'subtitle' => (string) ($location['display_label'] ?? ''),
            'place' => null,
            'reservation' => null,
            'occupancy' => [],
            'packages' => [],
            'article_links' => [],
            'movements' => self::publicMovements(StockMovementRepository::forLocation($locationId, 15)),
            'places_overview' => self::placesOverview($places),
            'summary' => self::summarizePlaces($places),
        ];
    }

    /** @param array<string, mixed> $resolved */
    private static function auditArticle(array $resolved): array
    {
        $article = $resolved['article'] ?? null;
        if (!is_array($article)) {
            throw new InvalidArgumentException('Artikel nicht gefunden.');
        }

        $articleId = (int) ($article['id'] ?? 0);
        $placeId = (int) ($article['stock_place_id'] ?? 0);
        $place = $placeId > 0 ? StockStructureRepository::findPlace($placeId) : null;

        return [
            'scan_type' => 'article',
            'title' => (string) ($article['article_number'] ?? '') . ' — ' . (string) ($article['title'] ?? ''),
            'subtitle' => 'Bestand ' . self::formatQty((float) ($article['stock_qty'] ?? 0)) . ' ' . (string) ($article['unit'] ?? ''),
            'place' => $place !== null ? self::publicPlace($place) : null,
            'reservation' => null,
            'occupancy' => [],
            'packages' => StockPackageRepository::inStockForArticle($articleId),
            'article_links' => [],
            'movements' => self::publicMovements(StockMovementRepository::forArticle($articleId, 15)),
            'summary' => $place !== null
                ? 'Artikel-Stammplatz: ' . (string) ($place['position_code'] ?? '')
                : 'Kein fester Stammplatz am Artikel hinterlegt',
        ];
    }

    /** @return array{reservation: array<string, mixed>|null, occupancy: list<array<string, mixed>>, packages: list<array<string, mixed>>, article_links: list<array<string, mixed>>, movements: list<array<string, mixed>>} */
    private static function placeDetails(int $placeId): array
    {
        $place = StockStructureRepository::findPlace($placeId);
        if ($place === null) {
            throw new InvalidArgumentException('Platz nicht gefunden.');
        }

        $reservation = null;
        if (($place['place_mode'] ?? '') === 'fixed') {
            $fixedId = (int) ($place['fixed_article_id'] ?? 0);
            if ($fixedId > 0) {
                $article = CalendarArticleRepository::findById($fixedId);
                if ($article !== null) {
                    $reservation = [
                        'mode' => 'fixed',
                        'article_id' => $fixedId,
                        'article_number' => (string) ($article['article_number'] ?? ''),
                        'title' => (string) ($article['title'] ?? ''),
                        'label' => 'Fest reserviert für Artikel',
                    ];
                }
            } else {
                $reservation = [
                    'mode' => 'fixed',
                    'label' => 'Modus fest — noch kein Artikel zugeordnet',
                ];
            }
        } else {
            $reservation = [
                'mode' => 'flexible',
                'label' => 'Flexibler Platz — Zuweisung bei Wareneingang',
            ];
        }

        $occupancy = [];
        if (Database::isConfigured()) {
            $stmt = Database::pdo()->prepare(
                'SELECT o.quantity, a.id AS article_id, a.article_number, a.title, a.unit
                 FROM dg_stock_place_occupancy o
                 INNER JOIN dg_calendar_articles a ON a.id = o.article_id
                 WHERE o.place_id = :place_id
                 ORDER BY a.article_number ASC'
            );
            $stmt->execute(['place_id' => $placeId]);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
                $occupancy[] = [
                    'article_id' => (int) ($row['article_id'] ?? 0),
                    'article_number' => (string) ($row['article_number'] ?? ''),
                    'title' => (string) ($row['title'] ?? ''),
                    'quantity' => round((float) ($row['quantity'] ?? 0), 3),
                    'unit' => (string) ($row['unit'] ?? ''),
                ];
            }
        }

        $packages = StockPackageRepository::inStockForPlace($placeId);
        $articleLinks = [];
        if (Database::isConfigured()) {
            $stmt = Database::pdo()->prepare(
                'SELECT id, article_number, title, unit, stock_qty
                 FROM dg_calendar_articles
                 WHERE stock_place_id = :place_id AND track_stock = 1
                 ORDER BY article_number ASC'
            );
            $stmt->execute(['place_id' => $placeId]);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
                $articleLinks[] = [
                    'article_id' => (int) ($row['id'] ?? 0),
                    'article_number' => (string) ($row['article_number'] ?? ''),
                    'title' => (string) ($row['title'] ?? ''),
                    'stock_qty' => round((float) ($row['stock_qty'] ?? 0), 3),
                    'unit' => (string) ($row['unit'] ?? ''),
                    'label' => 'Artikel-Stammplatz (Stammdaten)',
                ];
            }
        }

        return [
            'reservation' => $reservation,
            'occupancy' => $occupancy,
            'packages' => array_map(self::publicPackage(...), $packages),
            'article_links' => $articleLinks,
            'movements' => self::publicMovements(StockMovementRepository::forPlace($placeId, 20)),
        ];
    }

    /**
     * @param list<array<string, mixed>> $places
     * @return list<array<string, mixed>>
     */
    private static function placesOverview(array $places): array
    {
        $out = [];
        foreach ($places as $place) {
            if (empty($place['is_active'])) {
                continue;
            }
            $placeId = (int) ($place['id'] ?? 0);
            $occupied = StockPlaceService::isPlaceOccupied($placeId);
            $out[] = [
                'id' => $placeId,
                'position_code' => (string) ($place['position_code'] ?? ''),
                'kind_label' => (string) ($place['kind_label'] ?? ''),
                'mode_label' => (string) ($place['mode_label'] ?? ''),
                'is_occupied' => $occupied,
                'barcode' => (string) ($place['barcode'] ?? ''),
            ];
        }

        return $out;
    }

    /**
     * @param list<array<string, mixed>> $places
     */
    private static function summarizePlaces(array $places): string
    {
        $active = 0;
        $occupied = 0;
        foreach ($places as $place) {
            if (empty($place['is_active'])) {
                continue;
            }
            ++$active;
            if (StockPlaceService::isPlaceOccupied((int) ($place['id'] ?? 0))) {
                ++$occupied;
            }
        }
        $free = max(0, $active - $occupied);

        return $active . ' Stellplätze · ' . $occupied . ' belegt · ' . $free . ' frei';
    }

    /**
     * @param array<string, mixed> $place
     * @param array{reservation: array<string, mixed>|null, occupancy: list<array<string, mixed>>, packages: list<array<string, mixed>>, article_links: list<array<string, mixed>>, movements: list<array<string, mixed>>} $details
     */
    private static function buildPlaceSummary(array $place, array $details): string
    {
        if (($details['occupancy'] ?? []) !== []) {
            return count($details['occupancy']) . ' aktive Belegung(en) auf diesem Platz';
        }
        if (($details['packages'] ?? []) !== []) {
            return count($details['packages']) . ' Karton(s) auf diesem Platz';
        }
        if (($place['place_mode'] ?? '') === 'fixed' && (int) ($place['fixed_article_id'] ?? 0) > 0) {
            return 'Platz fest reserviert, aktuell ohne flexible Belegung';
        }

        return 'Platz derzeit frei';
    }

    /** @param array<string, mixed> $place */
    private static function publicPlace(array $place): array
    {
        return [
            'id' => (int) ($place['id'] ?? 0),
            'position_code' => (string) ($place['position_code'] ?? ''),
            'code' => (string) ($place['code'] ?? ''),
            'place_kind' => (string) ($place['place_kind'] ?? ''),
            'kind_label' => (string) ($place['kind_label'] ?? ''),
            'place_mode' => (string) ($place['place_mode'] ?? ''),
            'mode_label' => (string) ($place['mode_label'] ?? ''),
            'barcode' => (string) ($place['barcode'] ?? ''),
            'is_occupied' => !empty($place['is_occupied']) || StockPlaceService::isPlaceOccupied((int) ($place['id'] ?? 0)),
        ];
    }

    /** @param array<string, mixed> $package */
    private static function publicPackage(array $package): array
    {
        return [
            'id' => (int) ($package['id'] ?? 0),
            'barcode' => (string) ($package['barcode'] ?? ''),
            'quantity' => round((float) ($package['quantity'] ?? 0), 3),
            'status' => (string) ($package['status'] ?? ''),
            'article_number' => (string) ($package['article_number'] ?? ''),
            'title' => (string) ($package['title'] ?? ''),
            'unit' => (string) ($package['unit'] ?? ''),
        ];
    }

    /** @param list<array<string, mixed>> $rows */
    private static function publicMovements(array $rows): array
    {
        $out = [];
        foreach ($rows as $row) {
            $out[] = [
                'movement_date' => (string) ($row['movement_date'] ?? ''),
                'quantity' => round((float) ($row['quantity'] ?? 0), 3),
                'unit' => (string) ($row['unit'] ?? ''),
                'reason' => StockMovementRepository::reasonLabel((string) ($row['reason'] ?? '')),
                'note' => (string) ($row['note'] ?? ''),
                'article_number' => (string) ($row['article_number'] ?? ''),
                'title' => (string) ($row['title'] ?? ''),
            ];
        }

        return $out;
    }

    private static function formatQty(float $qty): string
    {
        return rtrim(rtrim(number_format($qty, 3, ',', '.'), '0'), ',');
    }
}
