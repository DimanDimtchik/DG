<?php
declare(strict_types=1);

/** Einkaufsliste: Rebuild aus Mindestbestand, Fehlmenge aus Beleg, Ignore/Status. */
final class PurchaseListService
{
    /**
     * Artikel mit track_stock und Nachbestellbedarf → open-Eintrag
     * (überspringt status=ignored und status=ordered).
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
                   AND is_active = 1"
            );
        } catch (Throwable) {
            return 0;
        }

        $count = 0;
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $ids = array_map(static fn (array $r): int => (int) ($r['id'] ?? 0), $rows);
        $reservedMap = StockReservationService::reservedQtyByArticleIds($ids);
        $onOrderMap = PurchaseListRepository::onOrderQtyByArticleIds($ids);

        foreach ($rows as $row) {
            $articleId = (int) ($row['id'] ?? 0);
            if ($articleId < 1) {
                continue;
            }
            $stockQty = round((float) ($row['stock_qty'] ?? 0), 3);
            $minStock = round((float) ($row['min_stock'] ?? 0), 3);
            $reserved = round((float) ($reservedMap[$articleId] ?? 0), 3);
            $onOrder = round((float) ($onOrderMap[$articleId] ?? 0), 3);
            $available = round($stockQty - $reserved, 3);

            if (!self::needsRestock($stockQty, $available, $minStock, $onOrder)) {
                continue;
            }

            $suggested = self::suggestedRestockQty($stockQty, $available, $minStock, $onOrder);
            if ($suggested < 0.0005) {
                continue;
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

    /**
     * Zielbestand: ohne Mindestmenge → 1 Stück im Lager;
     * mit Mindestmenge → Mindestmenge + 1.
     */
    public static function restockTarget(float $minStock): float
    {
        return $minStock > 0.0005 ? round($minStock + 1.0, 3) : 1.0;
    }

    /** Nachbestellbedarf unter Berücksichtigung bereits nachbestellter Mengen. */
    public static function needsRestock(
        float $stockQty,
        float $available,
        float $minStock,
        float $onOrder = 0.0
    ): bool {
        return self::suggestedRestockQty($stockQty, $available, $minStock, $onOrder) > 0.0005;
    }

    /**
     * Vorschlag: Differenz bis Zielbestand (Min+1 bzw. 1), abzüglich Nachbestellt.
     */
    public static function suggestedRestockQty(
        float $stockQty,
        float $available,
        float $minStock,
        float $onOrder = 0.0
    ): float {
        $target = self::restockTarget($minStock);
        $onOrder = max(0.0, round($onOrder, 3));
        $suggested = max(
            0.0,
            round($target - ($available + $onOrder), 3),
            round($target - ($stockQty + $onOrder), 3)
        );

        return $suggested;
    }

    /** Hinweistext ohne Shop-URL. */
    public static function manualOrderHint(string $supplierLabel, float $suggestedQty, string $unit): string
    {
        $supplier = trim($supplierLabel);
        if ($supplier === '') {
            $supplier = 'Ihrem Lieferanten (keine Einkaufsquelle hinterlegt)';
        }
        $qtyLabel = StockMovementService::formatQty($suggestedQty, $unit !== '' ? $unit : 'Stück');

        return 'Bitte bestellen Sie bei ' . $supplier . ' — ' . $qtyLabel . '.';
    }

    /** Fehlmenge aus Beleg → Einkaufsliste (wenn nicht ignored/ordered). */
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

        $stockQty = round((float) ($article['stock_qty'] ?? 0), 3);
        $minStock = round((float) ($article['min_stock'] ?? 0), 3);
        $reserved = StockReservationService::reservedQty($articleId);
        $available = round($stockQty - $reserved, 3);
        $onOrder = PurchaseListRepository::onOrderQty($articleId);
        $suggested = max(
            round($qty, 3),
            self::suggestedRestockQty($stockQty, $available, $minStock, $onOrder)
        );

        return PurchaseListRepository::upsertOpen(
            $articleId,
            PurchaseListRepository::REASON_MISSING_FOR_VOUCHER,
            $suggested,
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

    /**
     * Markiert als bestellt; optional tatsächliche Bestellmenge setzen.
     * Liefert bevorzugte Shop-URL (leer = manuell).
     */
    public static function markOrdered(int $id, ?float $orderedQty = null): string
    {
        $row = PurchaseListRepository::findById($id);
        if ($row === null) {
            return '';
        }
        if ($orderedQty !== null && $orderedQty > 0.0005) {
            PurchaseListRepository::updateSuggestedQty($id, $orderedQty);
        }
        PurchaseListRepository::setStatus($id, PurchaseListRepository::STATUS_ORDERED);
        $articleId = (int) ($row['article_id'] ?? 0);

        return $articleId > 0 ? ArticlePurchaseSourceRepository::preferredOrderUrl($articleId) : '';
    }

    /** Nachbestellte Menge korrigieren (status=ordered oder open). */
    public static function updateQty(int $id, float $qty): bool
    {
        if ($id < 1 || $qty < 0.0005) {
            return false;
        }
        $row = PurchaseListRepository::findById($id);
        if ($row === null) {
            return false;
        }
        $status = (string) ($row['status'] ?? '');
        if ($status !== PurchaseListRepository::STATUS_ORDERED
            && $status !== PurchaseListRepository::STATUS_OPEN) {
            return false;
        }

        return PurchaseListRepository::updateSuggestedQty($id, $qty);
    }

    /**
     * Manuell Nachbestellung erfassen (z. B. Jahresvorrat / Sonderangebot),
     * auch wenn der Artikel nicht auf der Einkaufsliste stand.
     */
    public static function registerManualOrder(int $articleId, float $qty, string $note = ''): bool
    {
        if ($articleId < 1 || $qty < 0.0005) {
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
        if ((string) ($article['catalog_kind'] ?? '') !== CalendarArticleCatalog::KIND_PRODUCT) {
            return false;
        }

        return PurchaseListRepository::upsertOrdered($articleId, $qty, $note);
    }

    /** @param mixed $raw */
    public static function parseQtyInput(mixed $raw): ?float
    {
        $s = trim((string) $raw);
        if ($s === '') {
            return null;
        }
        $s = str_replace([' ', "\xc2\xa0"], '', $s);
        $s = str_replace(',', '.', $s);
        if (!is_numeric($s)) {
            return null;
        }
        $qty = round((float) $s, 3);

        return $qty > 0.0005 ? $qty : null;
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
    public static function listOrdered(): array
    {
        return self::enrichRows(PurchaseListRepository::listByStatus(PurchaseListRepository::STATUS_ORDERED));
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
            $suggestedQty = round((float) ($row['suggested_qty'] ?? 0), 3);
            $reorderLabel = (string) ($preferred['label'] ?? '');
            $reorderUrl = (string) ($preferred['order_url'] ?? '');
            $row['article_number'] = (string) ($article['article_number'] ?? '');
            $row['article_title'] = (string) ($snap['title'] ?? ($article['title'] ?? ''));
            $row['unit'] = $unit;
            $row['stock_qty'] = $snap['stock_qty'] ?? 0.0;
            $row['available_qty'] = $snap['available'] ?? 0.0;
            $row['min_stock'] = $snap['min_stock'] ?? 0.0;
            $row['reserved_qty'] = $snap['reserved'] ?? 0.0;
            $row['on_order_qty'] = $snap['on_order'] ?? 0.0;
            $row['stock_label'] = StockMovementService::formatQty((float) $row['stock_qty'], $unit);
            $row['available_label'] = StockMovementService::formatQty((float) $row['available_qty'], $unit);
            $row['min_label'] = StockMovementService::formatQty((float) $row['min_stock'], $unit);
            $row['on_order_label'] = StockMovementService::formatQty((float) $row['on_order_qty'], $unit);
            $row['suggested_label'] = StockMovementService::formatQty($suggestedQty, $unit);
            $row['reason_label'] = PurchaseListRepository::reasonLabel((string) ($row['reason'] ?? ''));
            $row['reorder_url'] = $reorderUrl;
            $row['reorder_label'] = $reorderLabel;
            $row['has_purchase_source'] = $preferred !== null;
            $row['manual_order_hint'] = self::manualOrderHint($reorderLabel, $suggestedQty, $unit);
            $out[] = $row;
        }

        return $out;
    }
}
