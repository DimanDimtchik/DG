<?php
declare(strict_types=1);

/** Etiketten-Daten für Lagerstruktur (Ort → Halle → Regal → Stellplatz) */
final class StockLabelService
{
    public const LEVEL_LOCATION = 'location';
    public const LEVEL_HALL = 'hall';
    public const LEVEL_SHELF = 'shelf';
    public const LEVEL_PLACE = 'place';

    /** @return array<string, array{label: string, layout: string, width_mm: float, height_mm: float, page_width_mm?: float, page_height_mm?: float, cols?: int, rows?: int, margin_mm?: float, gap_mm?: float}> */
    public static function formatPresets(): array
    {
        return [
            '62x29' => [
                'label' => '62 × 29 mm (Etikettenrolle / schmal)',
                'layout' => 'single',
                'width_mm' => 62.0,
                'height_mm' => 29.0,
            ],
            '70x36' => [
                'label' => '70 × 36 mm',
                'layout' => 'single',
                'width_mm' => 70.0,
                'height_mm' => 36.0,
            ],
            '100x50' => [
                'label' => '100 × 50 mm',
                'layout' => 'single',
                'width_mm' => 100.0,
                'height_mm' => 50.0,
            ],
            '105x74' => [
                'label' => '105 × 74 mm (Postkarte / A7)',
                'layout' => 'single',
                'width_mm' => 105.0,
                'height_mm' => 74.0,
            ],
            'a4-3x8' => [
                'label' => 'A4 — 3 × 8 (ca. 70 × 37 mm)',
                'layout' => 'a4-grid',
                'width_mm' => 70.0,
                'height_mm' => 37.0,
                'page_width_mm' => 210.0,
                'page_height_mm' => 297.0,
                'cols' => 3,
                'rows' => 8,
                'margin_mm' => 4.5,
                'gap_mm' => 2.0,
            ],
            'a4-2x5' => [
                'label' => 'A4 — 2 × 5 (ca. 99 × 57 mm)',
                'layout' => 'a4-grid',
                'width_mm' => 99.0,
                'height_mm' => 57.0,
                'page_width_mm' => 210.0,
                'page_height_mm' => 297.0,
                'cols' => 2,
                'rows' => 5,
                'margin_mm' => 4.0,
                'gap_mm' => 2.0,
            ],
        ];
    }

    /** @return list<string> */
    public static function levelOptions(): array
    {
        return [
            self::LEVEL_LOCATION => 'Lagerort',
            self::LEVEL_HALL => 'Halle',
            self::LEVEL_SHELF => 'Regal',
            self::LEVEL_PLACE => 'Stellplatz',
        ];
    }

    public static function sanitizeLevel(string $level): string
    {
        $allowed = array_keys(self::levelOptions());

        return in_array($level, $allowed, true) ? $level : self::LEVEL_PLACE;
    }

    public static function sanitizeFormat(string $format): string
    {
        $presets = self::formatPresets();

        return isset($presets[$format]) ? $format : '100x50';
    }

    /**
     * @param array{level?: string, location_id?: int, hall_id?: int, shelf_id?: int, ids?: list<int>} $filters
     * @return list<array{level: string, level_label: string, display_code: string, barcode: string, subtitle: string}>
     */
    public static function collectLabels(array $filters): array
    {
        $level = self::sanitizeLevel((string) ($filters['level'] ?? self::LEVEL_PLACE));
        $locationId = (int) ($filters['location_id'] ?? 0);
        $hallId = (int) ($filters['hall_id'] ?? 0);
        $shelfId = (int) ($filters['shelf_id'] ?? 0);
        $ids = array_values(array_filter(array_map('intval', (array) ($filters['ids'] ?? [])), static fn (int $id): bool => $id > 0));

        return match ($level) {
            self::LEVEL_LOCATION => self::labelsForLocations($ids, $locationId),
            self::LEVEL_HALL => self::labelsForHalls($ids, $locationId, $hallId),
            self::LEVEL_SHELF => self::labelsForShelves($ids, $locationId, $hallId, $shelfId),
            default => self::labelsForPlaces($ids, $locationId, $hallId, $shelfId),
        };
    }

    public static function displayCodeForLocation(array $row): string
    {
        return StockPositionCode::sanitizeSegment((string) ($row['code'] ?? ''));
    }

    public static function displayCodeForHall(array $row): string
    {
        return StockPositionCode::composeSegments([
            (string) ($row['location_code'] ?? ''),
            (string) ($row['code'] ?? ''),
        ]);
    }

    public static function displayCodeForShelf(array $row): string
    {
        $prefix = (string) ($row['position_prefix'] ?? '');
        if ($prefix !== '') {
            return $prefix;
        }

        return StockPositionCode::composeSegments([
            (string) ($row['location_code'] ?? ''),
            (string) ($row['hall_code'] ?? ''),
            (string) ($row['code'] ?? ''),
        ]);
    }

    public static function barcodeForLocation(array $row): string
    {
        return self::displayCodeForLocation($row);
    }

    public static function barcodeForHall(array $row): string
    {
        return self::displayCodeForHall($row);
    }

    public static function barcodeForShelf(array $row): string
    {
        return self::displayCodeForShelf($row);
    }

    public static function barcodeForPlace(array $row): string
    {
        $barcode = trim((string) ($row['barcode'] ?? ''));
        if ($barcode !== '') {
            return $barcode;
        }

        $placeId = (int) ($row['id'] ?? 0);
        if ($placeId > 0) {
            try {
                return StockBarcodeService::generatePlaceBarcode($placeId);
            } catch (Throwable) {
            }
        }

        return preg_replace('/[^0-9A-Za-z]/', '', self::displayCodeForPlace($row)) ?: self::displayCodeForPlace($row);
    }

    public static function displayCodeForPlace(array $row): string
    {
        $code = (string) ($row['position_code'] ?? '');
        if ($code !== '') {
            return $code;
        }

        return StockPositionCode::composeSegments([
            (string) ($row['location_code'] ?? ''),
            (string) ($row['hall_code'] ?? ''),
            (string) ($row['shelf_code'] ?? ''),
            (string) ($row['code'] ?? ''),
        ]);
    }

    /** @param list<int> $ids */
    private static function labelsForLocations(array $ids, int $locationId): array
    {
        $labels = [];
        foreach (StockStructureRepository::allLocations() as $row) {
            if (empty($row['is_active'])) {
                continue;
            }
            $id = (int) ($row['id'] ?? 0);
            if ($ids !== [] && !in_array($id, $ids, true)) {
                continue;
            }
            if ($locationId > 0 && $id !== $locationId) {
                continue;
            }
            $labels[] = self::labelRow(
                self::LEVEL_LOCATION,
                'Lagerort',
                self::displayCodeForLocation($row),
                self::barcodeForLocation($row),
                trim((string) ($row['name'] ?? '')) !== '' ? (string) $row['name'] : (string) ($row['function_text'] ?? ''),
            );
        }

        return $labels;
    }

    /** @param list<int> $ids */
    private static function labelsForHalls(array $ids, int $locationId, int $hallId): array
    {
        $labels = [];
        foreach (StockStructureRepository::allHalls($locationId > 0 ? $locationId : null) as $row) {
            if (empty($row['is_active'])) {
                continue;
            }
            $id = (int) ($row['id'] ?? 0);
            if ($ids !== [] && !in_array($id, $ids, true)) {
                continue;
            }
            if ($hallId > 0 && $id !== $hallId) {
                continue;
            }
            $labels[] = self::labelRow(
                self::LEVEL_HALL,
                'Halle',
                self::displayCodeForHall($row),
                self::barcodeForHall($row),
                (string) ($row['usage_text'] ?? '') !== '' ? (string) $row['usage_text'] : (string) ($row['location_code'] ?? ''),
            );
        }

        return $labels;
    }

    /** @param list<int> $ids */
    private static function labelsForShelves(array $ids, int $locationId, int $hallId, int $shelfId): array
    {
        $labels = [];
        foreach (StockStructureRepository::allShelves($hallId > 0 ? $hallId : null, $locationId > 0 ? $locationId : null) as $row) {
            if (empty($row['is_active'])) {
                continue;
            }
            $id = (int) ($row['id'] ?? 0);
            if ($ids !== [] && !in_array($id, $ids, true)) {
                continue;
            }
            if ($shelfId > 0 && $id !== $shelfId) {
                continue;
            }
            $labels[] = self::labelRow(
                self::LEVEL_SHELF,
                'Regal',
                self::displayCodeForShelf($row),
                self::barcodeForShelf($row),
                (string) ($row['capacity_summary'] ?? ''),
            );
        }

        return $labels;
    }

    /** @param list<int> $ids */
    private static function labelsForPlaces(array $ids, int $locationId, int $hallId, int $shelfId): array
    {
        $labels = [];
        foreach (StockStructureRepository::allPlaces($locationId, $hallId, $shelfId) as $row) {
            if (empty($row['is_active'])) {
                continue;
            }
            $id = (int) ($row['id'] ?? 0);
            if ($ids !== [] && !in_array($id, $ids, true)) {
                continue;
            }
            $kind = (string) ($row['kind_label'] ?? 'Einheit');
            $labels[] = self::labelRow(
                self::LEVEL_PLACE,
                'Stellplatz · ' . $kind,
                self::displayCodeForPlace($row),
                self::barcodeForPlace($row),
                $kind . ' · ' . (string) ($row['mode_label'] ?? ''),
            );
        }

        return $labels;
    }

    /** @return array{level: string, level_label: string, display_code: string, barcode: string, subtitle: string} */
    private static function labelRow(
        string $level,
        string $levelLabel,
        string $displayCode,
        string $barcode,
        string $subtitle,
    ): array {
        return [
            'level' => $level,
            'level_label' => $levelLabel,
            'display_code' => $displayCode,
            'barcode' => StockBarcodeService::normalize($barcode),
            'subtitle' => trim($subtitle),
        ];
    }
}
