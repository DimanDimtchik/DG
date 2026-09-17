<?php
declare(strict_types=1);

/** Einkaufsliste: Rebuild aus Mindestbestand, Fehlmenge aus Beleg, Ignore/Status. */
final class PurchaseListService
{
    /**
     * Artikel mit track_stock und verfügbar/Bestand unter min_stock → open-Eintrag
     * (überspringt status=ignored).
     *
     * @return int Anzahl neu angelegter oder aktualisierter Einträge
     */
    public static function rebuildFromStock(): int
    {
        if (!Database::isConfigured() || !PurchaseListRepository::tableReady()) {
            return 0;
        }

        MigrationRunner::runPending();

        try {
            $stmt = Database::pdo()->query(
                "SELECT id, stock_qty, min_stock, unit
                 FROM dg_calendar_articles
                 WHERE track_stock = 1
                   AND catalog_kind = 'product'
                   AND is_active = 1
                   AND min_stock > 0"
            );
        } catch (Throwable) {
            return 0;
        }

        $count = 0;
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $ids = array_map(static fn (array $r): int => (int) ($r['id'] ?? 0), $rows);
        $reservedMap = StockReservationService::reservedQtyByArticleIds($ids);

        foreach ($rows as $row) {
            $articleId = (int) ($row['id'] ?? 0);
            if ($articleId < 1) {
                continue;
            }
            $stockQty = round((float) ($row['stock_qty'] ?? 0), 3);
            $minStock = round((float) ($row['min_stock'] ?? 0), 3);
            $reserved = round((float) ($reservedMap[$articleId] ?? 0), 3);
            $available = round($stockQty - $reserved, 3);
            if ($available > $minStock + 0.0005 && $stockQty > $minStock + 0.0005) {
                continue;
            }
            $suggested = max(0.0, round($minStock - $available, 3));
            if ($suggested < 0.0005) {
                $suggested = max(0.0, round($minStock - $stockQty, 3));
            }
            if ($suggested < 0.0005) {
                $suggested = $minStock > 0 ? $minStock : 1.0;
            }
            if (PurchaseListRepository::upsertOpen(
                $articleId,
                PurchaseListRepository::REASON_BELOW_MIN,
                $suggested
            )) {
                $count++;
            }
        }

        return $count;
    }

    /** Fehlmenge aus Beleg → Einkaufsliste (wenn nicht ignored). */
    public static function ensureForShortage(int $articleId, float $qty, ?int $voucherId = null): bool
    {
        if ($articleId < 1 || $qty < 0.0005) {
            return false;
        }
        if (!StockPurchaseSettings::usesPurchaseList()) {
            return false;
        }

        MigrationRunner::runPending();
        if (!PurchaseListRepository::tableReady()) {
            return false;
        }

        $article = CalendarArticleRepository::findById($articleId);
        if ($article === null || empty($article['track_stock'])) {
            return false;
        }

        return PurchaseListRepository::upsertOpen(
            $articleId,
            PurchaseListRepository::REASON_MISSING_FOR_VOUCHER,
            round($qty, 3),
            $voucherId,
            $voucherId !== null && $voucherId > 0 ? ('Beleg #' . $voucherId) : ''
        );
    }

    public static function ignore(int $id, ?int $userId = null): void
    {
        PurchaseListRepository::setStatus($id, PurchaseListRepository::STATUS_IGNORED, $userId);
    }

    public static function restore(int $id): void
    {
        PurchaseListRepository::setStatus($id, PurchaseListRepository::STATUS_OPEN);
    }

    public static function markOrdered(int $id): void
    {
        PurchaseListRepository::setStatus($id, PurchaseListRepository::STATUS_ORDERED);
    }

    public static function markDone(int $id): void
    {
        PurchaseListRepository::setStatus($id, PurchaseListRepository::STATUS_DONE);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function listOpen(): array
    {
        return self::enrichRows(PurchaseListRepository::listByStatus(PurchaseListRepository::STATUS_OPEN));
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function listIgnored(): array
    {
        return self::enrichRows(PurchaseListRepository::listByStatus(PurchaseListRepository::STATUS_IGNORED));
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return list<array<string, mixed>>
     */
    private static function enrichRows(array $rows): array
    {
        if ($rows === []) {
            return [];
        }
        $ids = array_map(static fn (array $r): int => (int) ($r['article_id'] ?? 0), $rows);
        $sourcesMap = ArticlePurchaseSourceRepository::forArticles($ids);
        $out = [];
        foreach ($rows as $row) {
            $articleId = (int) ($row['article_id'] ?? 0);
            $snap = StockAvailabilityService::snapshot($articleId);
            $article = CalendarArticleRepository::findById($articleId);
            $sources = $sourcesMap[$articleId] ?? [];
            $preferred = null;
            foreach ($sources as $src) {
                if (!empty($src['is_preferred'])) {
                    $preferred = $src;
                    break;
                }
            }
            if ($preferred === null && $sources !== []) {
                $preferred = $sources[0];
            }
            $unit = $snap['unit'] ?? ((string) ($article['unit'] ?? 'Stück'));
            $row['article_number'] = (string) ($article['article_number'] ?? '');
            $row['article_title'] = (string) ($snap['title'] ?? ($article['title'] ?? ''));
            $row['unit'] = $unit;
            $row['stock_qty'] = $snap['stock_qty'] ?? 0.0;
            $row['available_qty'] = $snap['available'] ?? 0.0;
            $row['min_stock'] = $snap['min_stock'] ?? 0.0;
            $row['reserved_qty'] = $snap['reserved'] ?? 0.0;
            $row['stock_label'] = StockMovementService::formatQty((float) $row['stock_qty'], $unit);
            $row['available_label'] = StockMovementService::formatQty((float) $row['available_qty'], $unit);
            $row['min_label'] = StockMovementService::formatQty((float) $row['min_stock'], $unit);
            $row['suggested_label'] = StockMovementService::formatQty((float) ($row['suggested_qty'] ?? 0), $unit);
            $row['reason_label'] = PurchaseListRepository::reasonLabel((string) ($row['reason'] ?? ''));
            $row['reorder_url'] = (string) ($preferred['order_url'] ?? '');
            $row['reorder_label'] = (string) ($preferred['label'] ?? '');
            $row['has_purchase_source'] = $preferred !== null;
            $out[] = $row;
        }

        return $out;
    }
}
