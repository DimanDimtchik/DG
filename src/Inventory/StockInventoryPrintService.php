<?php
declare(strict_types=1);

/**
 * Druckbare Inventur-Zähllisten (Browser → Drucken / PDF speichern).
 * Gruppiert nach Ort–Halle–Regal; optional mit Sollbestand.
 */
final class StockInventoryPrintService
{
    public const MODE_BLANK = 'blank';
    public const MODE_EXPECTED = 'expected';

    /**
     * @return array{
     *   mode: string,
     *   show_expected: bool,
     *   inventory_id: int|null,
     *   inventory_date: string,
     *   inventory_note: string,
     *   filter_label: string,
     *   groups: list<array{key: string, label: string, rows: list<array<string, mixed>>}>,
     *   row_count: int
     * }
     */
    public static function buildSheetData(
        ?int $inventoryId,
        string $mode,
        string $ort = '',
        string $halle = '',
        string $regal = ''
    ): array {
        $mode = $mode === self::MODE_EXPECTED ? self::MODE_EXPECTED : self::MODE_BLANK;
        $ort = StockPositionCode::sanitizeSegment($ort);
        $halle = StockPositionCode::sanitizeSegment($halle);
        $regal = StockPositionCode::sanitizeSegment($regal);

        $inventory = null;
        if ($inventoryId !== null && $inventoryId > 0) {
            $inventory = StockInventoryService::find($inventoryId);
            if ($inventory === null) {
                throw new InvalidArgumentException('Inventur nicht gefunden.');
            }
        }

        $rawRows = $inventory !== null
            ? self::rowsFromInventory((int) $inventory['id'])
            : self::rowsFromStockOverview();

        $filtered = [];
        foreach ($rawRows as $row) {
            if ($ort !== '' && StockPositionCode::sanitizeSegment((string) ($row['stock_ort'] ?? '')) !== $ort) {
                continue;
            }
            if ($halle !== '' && StockPositionCode::sanitizeSegment((string) ($row['stock_halle'] ?? '')) !== $halle) {
                continue;
            }
            if ($regal !== '' && StockPositionCode::sanitizeSegment((string) ($row['stock_regal'] ?? '')) !== $regal) {
                continue;
            }
            $filtered[] = $row;
        }

        $groupsMap = [];
        foreach ($filtered as $row) {
            $segOrt = StockPositionCode::sanitizeSegment((string) ($row['stock_ort'] ?? ''));
            $segHalle = StockPositionCode::sanitizeSegment((string) ($row['stock_halle'] ?? ''));
            $segRegal = StockPositionCode::sanitizeSegment((string) ($row['stock_regal'] ?? ''));
            $key = $segOrt . '|' . $segHalle . '|' . $segRegal;
            if (!isset($groupsMap[$key])) {
                $labelParts = array_values(array_filter([$segOrt, $segHalle, $segRegal], static fn (string $p): bool => $p !== ''));
                $groupsMap[$key] = [
                    'key' => $key,
                    'label' => $labelParts !== [] ? implode(' · ', $labelParts) : 'Ohne Lagerplatz',
                    'rows' => [],
                ];
            }
            $groupsMap[$key]['rows'][] = $row;
        }

        ksort($groupsMap, SORT_STRING);

        $filterParts = [];
        if ($ort !== '') {
            $filterParts[] = 'Ort ' . $ort;
        }
        if ($halle !== '') {
            $filterParts[] = 'Halle ' . $halle;
        }
        if ($regal !== '') {
            $filterParts[] = 'Regal ' . $regal;
        }

        return [
            'mode' => $mode,
            'show_expected' => $mode === self::MODE_EXPECTED,
            'inventory_id' => $inventory !== null ? (int) $inventory['id'] : null,
            'inventory_date' => $inventory !== null
                ? (string) ($inventory['inventory_date'] ?? '')
                : date('Y-m-d'),
            'inventory_note' => $inventory !== null ? (string) ($inventory['note'] ?? '') : '',
            'filter_label' => $filterParts !== [] ? implode(', ', $filterParts) : 'Alle Lagerplätze',
            'groups' => array_values($groupsMap),
            'row_count' => count($filtered),
        ];
    }

    public static function renderHtml(?int $inventoryId, string $mode, string $ort = '', string $halle = '', string $regal = ''): string
    {
        $data = self::buildSheetData($inventoryId, $mode, $ort, $halle, $regal);
        $title = $data['show_expected']
            ? 'Inventur-Zählliste (mit Sollbestand)'
            : 'Inventur-Zählliste (leer zum Auszählen)';

        return AccountingPrintService::render('inventur-zaehlliste', $data, $title);
    }

    public static function send(?int $inventoryId, string $mode, string $ort = '', string $halle = '', string $regal = ''): void
    {
        $mode = $mode === self::MODE_EXPECTED ? self::MODE_EXPECTED : self::MODE_BLANK;
        $suffix = $mode === self::MODE_EXPECTED ? 'soll' : 'leer';
        $idPart = $inventoryId !== null && $inventoryId > 0 ? (string) $inventoryId : 'bestand';
        $html = self::renderHtml($inventoryId, $mode, $ort, $halle, $regal);
        AccountingPrintService::send('Inventur-Zaehlliste_' . $idPart . '_' . $suffix . '.html', $html);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function rowsFromInventory(int $inventoryId): array
    {
        $rows = [];
        foreach (StockInventoryService::linesForInventory($inventoryId) as $line) {
            $rows[] = self::normalizeRow($line, (float) ($line['book_quantity'] ?? 0));
        }

        usort($rows, static function (array $a, array $b): int {
            return [$a['stock_ort'], $a['stock_halle'], $a['stock_regal'], $a['stock_platz'], $a['title']]
                <=> [$b['stock_ort'], $b['stock_halle'], $b['stock_regal'], $b['stock_platz'], $b['title']];
        });

        return $rows;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function rowsFromStockOverview(): array
    {
        $rows = [];
        foreach (StockMovementService::stockOverview(false) as $item) {
            $rows[] = self::normalizeRow($item, (float) ($item['stock_qty'] ?? 0));
        }

        return $rows;
    }

    /**
     * @param array<string, mixed> $src
     * @return array<string, mixed>
     */
    private static function normalizeRow(array $src, float $bookQty): array
    {
        $ort = (string) ($src['stock_ort'] ?? '');
        $halle = (string) ($src['stock_halle'] ?? '');
        $regal = (string) ($src['stock_regal'] ?? '');
        $platz = (string) ($src['stock_platz'] ?? '');

        return [
            'article_number' => (string) ($src['article_number'] ?? ''),
            'title' => (string) ($src['title'] ?? ''),
            'unit' => (string) ($src['unit'] ?? ''),
            'book_quantity' => round($bookQty, 3),
            'stock_ort' => StockPositionCode::sanitizeSegment($ort),
            'stock_halle' => StockPositionCode::sanitizeSegment($halle),
            'stock_regal' => StockPositionCode::sanitizeSegment($regal),
            'stock_platz' => StockPositionCode::sanitizeSegment($platz),
            'stock_position_code' => StockPositionCode::compose($ort, $halle, $regal, $platz),
        ];
    }
}
