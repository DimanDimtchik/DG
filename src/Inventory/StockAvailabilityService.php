<?php
declare(strict_types=1);

/** Verfügbarkeit: Bestand − Reserviert; In-Auslieferung als Info. */
final class StockAvailabilityService
{
    /**
     * @return array{
     *   article_id: int,
     *   title: string,
     *   unit: string,
     *   stock_qty: float,
     *   reserved: float,
     *   in_transit: float,
     *   available: float,
     *   min_stock: float,
     *   is_low: bool,
     *   track_stock: bool
     * }|null
     */
    public static function snapshot(int $articleId): ?array
    {
        if ($articleId < 1 || !Database::isConfigured()) {
            return null;
        }

        $article = CalendarArticleRepository::findById($articleId);
        if ($article === null) {
            return null;
        }
        if (empty($article['track_stock'])) {
            return [
                'article_id' => $articleId,
                'title' => (string) ($article['title'] ?? ''),
                'unit' => (string) ($article['unit'] ?? 'Stück'),
                'stock_qty' => 0.0,
                'reserved' => 0.0,
                'in_transit' => 0.0,
                'available' => 0.0,
                'min_stock' => 0.0,
                'is_low' => false,
                'track_stock' => false,
            ];
        }

        $stockQty = round((float) ($article['stock_qty'] ?? 0), 3);
        $minStock = round((float) ($article['min_stock'] ?? 0), 3);
        $reserved = StockReservationService::reservedQty($articleId);
        $inTransit = self::inTransitQty($articleId);
        $available = round($stockQty - $reserved, 3);
        $unit = (string) ($article['unit'] ?? 'Stück');

        return [
            'article_id' => $articleId,
            'title' => (string) ($article['title'] ?? ''),
            'unit' => $unit !== '' ? $unit : 'Stück',
            'stock_qty' => $stockQty,
            'reserved' => $reserved,
            'in_transit' => $inTransit,
            'available' => $available,
            'min_stock' => $minStock,
            'is_low' => $minStock > 0 && $available <= $minStock,
            'track_stock' => true,
        ];
    }

    /**
     * Enrichiert stockOverview-Zeilen.
     *
     * @param list<array<string, mixed>> $rows
     * @return list<array<string, mixed>>
     */
    public static function enrichOverviewRows(array $rows): array
    {
        $ids = [];
        foreach ($rows as $row) {
            $ids[] = (int) ($row['id'] ?? 0);
        }
        $reservedMap = StockReservationService::reservedQtyByArticleIds($ids);
        $transitMap = self::inTransitQtyByArticleIds($ids);

        foreach ($rows as &$row) {
            $id = (int) ($row['id'] ?? 0);
            $stockQty = round((float) ($row['stock_qty'] ?? 0), 3);
            $reserved = round((float) ($reservedMap[$id] ?? 0), 3);
            $inTransit = round((float) ($transitMap[$id] ?? 0), 3);
            $available = round($stockQty - $reserved, 3);
            $minStock = round((float) ($row['min_stock'] ?? 0), 3);
            $unit = (string) ($row['unit'] ?? 'Stück');

            $row['reserved_qty'] = $reserved;
            $row['in_transit_qty'] = $inTransit;
            $row['available_qty'] = $available;
            $row['reserved_label'] = StockMovementService::formatQty($reserved, $unit);
            $row['in_transit_label'] = StockMovementService::formatQty($inTransit, $unit);
            $row['available_label'] = StockMovementService::formatQty($available, $unit);
            $row['is_low'] = $minStock > 0 && $available <= $minStock;
        }
        unset($row);

        return $rows;
    }

    /**
     * Offene Lieferscheine (nicht abgerechnet/storniert) — nur Anzeige.
     * Bestand ist bei LS-Speichern bereits gemindert.
     */
    public static function inTransitQty(int $articleId): float
    {
        $map = self::inTransitQtyByArticleIds([$articleId]);

        return round((float) ($map[$articleId] ?? 0), 3);
    }

    /**
     * @param list<int> $articleIds
     * @return array<int, float>
     */
    public static function inTransitQtyByArticleIds(array $articleIds): array
    {
        $articleIds = array_values(array_unique(array_filter(array_map('intval', $articleIds))));
        if ($articleIds === [] || !Database::isConfigured()) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($articleIds), '?'));
        $sent = VoucherDocumentStatus::SENT;
        $draft = VoucherDocumentStatus::DRAFT;
        $kind = VoucherDocumentKind::DELIVERY_NOTE;

        $sql = "SELECT i.article_id, COALESCE(SUM(ABS(i.quantity)), 0) AS qty
                FROM dg_voucher_items i
                INNER JOIN dg_vouchers v ON v.id = i.voucher_id
                WHERE i.article_id IN ({$placeholders})
                  AND v.voucher_type = 'income'
                  AND v.document_kind = ?
                  AND v.is_draft = 0
                  AND v.document_status IN (?, ?)
                GROUP BY i.article_id";

        $params = array_merge($articleIds, [$kind, $draft, $sent]);
        try {
            $stmt = Database::pdo()->prepare($sql);
            $stmt->execute($params);
        } catch (Throwable) {
            return [];
        }

        $map = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $map[(int) $row['article_id']] = round((float) $row['qty'], 3);
        }

        return $map;
    }
}
