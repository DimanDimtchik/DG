<?php
declare(strict_types=1);

/** Lagerbestand aus Belegen und manuellen Buchungen. */
final class StockMovementService
{
    /**
     * Beleg speichern → Bewegungen neu aufbauen (idempotent).
     */
    public static function syncForVoucher(int $voucherId, ?int $userId = null): void
    {
        if ($voucherId < 1 || !Database::isConfigured()) {
            return;
        }

        MigrationRunner::runPending();

        $voucher = VoucherRepository::findById($voucherId);
        if ($voucher === null || !empty($voucher['is_draft'])) {
            return;
        }

        $voucherType = VoucherRepository::normalizeVoucherType((string) ($voucher['voucher_type'] ?? ''));
        $documentKind = (string) ($voucher['document_kind'] ?? '');
        if (!self::voucherAffectsStock($voucherType, $documentKind)) {
            StockMovementRepository::deleteForVoucher($voucherId);

            return;
        }

        if ($voucherType === 'income' && self::shouldSkipIncomeStockSync($documentKind, $voucherId)) {
            StockMovementRepository::deleteForVoucher($voucherId);

            return;
        }

        $deltaSign = self::deltaSignForVoucherType($voucherType, $documentKind);
        if ($deltaSign === 0) {
            StockMovementRepository::deleteForVoucher($voucherId);

            return;
        }

        StockMovementRepository::deleteForVoucher($voucherId);

        $movementDate = (string) ($voucher['voucher_date'] ?? date('Y-m-d'));
        $reason = self::reasonForVoucherMovement($voucherType, $documentKind, $deltaSign);

        $items = VoucherRepository::itemsForVoucher($voucherId);
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }
            $articleId = (int) ($item['article_id'] ?? 0);
            if ($articleId < 1) {
                continue;
            }
            $article = CalendarArticleRepository::findById($articleId);
            if ($article === null || empty($article['track_stock'])) {
                continue;
            }
            if ((string) ($article['catalog_kind'] ?? '') !== CalendarArticleCatalog::KIND_PRODUCT) {
                continue;
            }

            $qty = abs((float) ($item['quantity'] ?? 0));
            if ($qty <= 0) {
                continue;
            }

            StockMovementRepository::insert(
                $articleId,
                $movementDate,
                round($qty * $deltaSign, 3),
                $reason,
                $voucherId,
                null,
                'Beleg #' . $voucherId,
                $userId,
            );

            // Nach Abgang: Unterbestand → Einkaufsliste
            if ($deltaSign < 0) {
                $snap = StockAvailabilityService::snapshot($articleId);
                if ($snap !== null && $snap['track_stock']) {
                    if ($snap['available'] < -0.0005) {
                        PurchaseListService::ensureForShortage($articleId, abs($snap['available']), $voucherId);
                    } elseif (PurchaseListService::needsRestock(
                        $snap['stock_qty'],
                        $snap['available'],
                        $snap['min_stock'],
                        (float) ($snap['on_order'] ?? 0)
                    )) {
                        $toTarget = PurchaseListService::suggestedRestockQty(
                            $snap['stock_qty'],
                            $snap['available'],
                            $snap['min_stock'],
                            (float) ($snap['on_order'] ?? 0)
                        );
                        if ($toTarget > 0.0005) {
                            PurchaseListService::ensureForShortage($articleId, $toTarget, $voucherId);
                        }
                    }
                }
            }
        }
    }

    public static function revertForVoucher(int $voucherId): void
    {
        StockMovementRepository::deleteForVoucher($voucherId);
    }

    public static function manualAdjust(int $articleId, float $quantityDelta, string $note, ?int $userId = null): void
    {
        self::assertTrackableArticle($articleId);
        if (abs($quantityDelta) < 0.0005) {
            throw new InvalidArgumentException('Menge darf nicht 0 sein.');
        }

        StockMovementRepository::insert(
            $articleId,
            date('Y-m-d'),
            round($quantityDelta, 3),
            'adjustment',
            null,
            null,
            $note !== '' ? $note : 'Manuelle Korrektur',
            $userId,
        );
    }

    public static function recordOpeningBalance(int $articleId, float $quantity, ?int $userId = null): void
    {
        self::assertTrackableArticle($articleId);
        if ($quantity <= 0) {
            return;
        }

        StockMovementRepository::insert(
            $articleId,
            date('Y-m-d'),
            round($quantity, 3),
            'opening',
            null,
            null,
            'Anfangsbestand',
            $userId,
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function stockOverview(bool $lowStockOnly = false): array
    {
        if (!Database::isConfigured()) {
            return [];
        }

        MigrationRunner::runPending();

        $sql = "SELECT id, article_number, title, unit, stock_qty, min_stock, track_stock,
                       stock_ort, stock_halle, stock_regal, stock_platz
                FROM dg_calendar_articles
                WHERE catalog_kind = 'product' AND track_stock = 1";
        if ($lowStockOnly) {
            $sql .= ' AND (stock_qty < 0 OR (min_stock > 0 AND stock_qty <= min_stock))';
        }
        $sql .= ' ORDER BY stock_ort ASC, stock_halle ASC, stock_regal ASC, stock_platz ASC, title ASC';

        $rows = Database::pdo()->query($sql)->fetchAll(PDO::FETCH_ASSOC) ?: [];
        foreach ($rows as &$row) {
            $row['stock_qty'] = round((float) ($row['stock_qty'] ?? 0), 3);
            $row['min_stock'] = round((float) ($row['min_stock'] ?? 0), 3);
            $row['is_low'] = PurchaseListService::needsRestock(
                (float) $row['stock_qty'],
                (float) $row['stock_qty'],
                (float) $row['min_stock'],
                0.0
            );
            $row['stock_label'] = self::formatQty((float) $row['stock_qty'], (string) ($row['unit'] ?? 'Stück'));
            $row['stock_position_code'] = StockPositionCode::fromRow($row);
        }
        unset($row);

        return StockAvailabilityService::enrichOverviewRows($rows);
    }

    public static function formatQty(float $qty, string $unit = 'Stück'): string
    {
        $formatted = rtrim(rtrim(number_format($qty, 3, ',', '.'), '0'), ',');

        return $formatted . ' ' . ($unit !== '' ? $unit : 'Stück');
    }

    /**
     * @return list<array<string, string>>
     */
    public static function exportOverviewCsvRows(): array
    {
        $rows = [];
        foreach (self::stockOverview(false) as $item) {
            $rows[] = [
                'article_number' => (string) ($item['article_number'] ?? ''),
                'title' => (string) ($item['title'] ?? ''),
                'position_code' => (string) ($item['stock_position_code'] ?? ''),
                'unit' => (string) ($item['unit'] ?? ''),
                'stock_qty' => (string) ($item['stock_qty'] ?? '0'),
                'reserved_qty' => (string) ($item['reserved_qty'] ?? '0'),
                'in_transit_qty' => (string) ($item['in_transit_qty'] ?? '0'),
                'on_order_qty' => (string) ($item['on_order_qty'] ?? '0'),
                'available_qty' => (string) ($item['available_qty'] ?? $item['stock_qty'] ?? '0'),
                'min_stock' => (string) ($item['min_stock'] ?? '0'),
                'low_stock' => !empty($item['is_low']) ? 'ja' : 'nein',
            ];
        }

        return $rows;
    }

    private static function assertTrackableArticle(int $articleId): void
    {
        $article = CalendarArticleRepository::findById($articleId);
        if ($article === null) {
            throw new InvalidArgumentException('Artikel nicht gefunden.');
        }
        if ((string) ($article['catalog_kind'] ?? '') !== CalendarArticleCatalog::KIND_PRODUCT) {
            throw new InvalidArgumentException('Lagerführung gilt nur für Artikel (keine Leistungen).');
        }
        if (empty($article['track_stock'])) {
            throw new InvalidArgumentException('Für diesen Artikel ist keine Lagerführung aktiviert.');
        }
    }

    private static function voucherAffectsStock(string $voucherType, string $documentKind): bool
    {
        $documentKind = VoucherDocumentKind::sanitize($documentKind);
        if ($voucherType === 'income') {
            if ($documentKind === VoucherDocumentKind::DELIVERY_NOTE) {
                return true;
            }

            return VoucherDocumentKind::isBookable($documentKind, 'income');
        }

        return in_array($voucherType, ['expense', 'expense_reduction', 'credit'], true);
    }

    private static function shouldSkipIncomeStockSync(string $documentKind, int $voucherId): bool
    {
        $documentKind = VoucherDocumentKind::sanitize($documentKind);
        if ($documentKind === VoucherDocumentKind::DELIVERY_NOTE) {
            return false;
        }

        if (!in_array($documentKind, [
            VoucherDocumentKind::PARTIAL_INVOICE,
            VoucherDocumentKind::INVOICE,
            VoucherDocumentKind::FINAL_INVOICE,
        ], true)) {
            return false;
        }

        return VoucherDocumentChain::subtreeHasKind($voucherId, VoucherDocumentKind::DELIVERY_NOTE);
    }

    private static function deltaSignForVoucherType(string $voucherType, string $documentKind = ''): int
    {
        $documentKind = VoucherDocumentKind::sanitize($documentKind);
        if ($voucherType === 'income' && $documentKind === VoucherDocumentKind::DELIVERY_NOTE) {
            return -1;
        }

        return match ($voucherType) {
            'expense', 'credit' => 1,
            'income', 'expense_reduction' => -1,
            default => 0,
        };
    }

    private static function reasonForVoucherMovement(string $voucherType, string $documentKind, int $deltaSign): string
    {
        $documentKind = VoucherDocumentKind::sanitize($documentKind);
        if ($voucherType === 'income' && $documentKind === VoucherDocumentKind::DELIVERY_NOTE) {
            return 'issue';
        }
        if ($deltaSign > 0) {
            return $voucherType === 'credit' ? 'purchase' : 'receipt';
        }

        return $voucherType === 'expense_reduction' ? 'sale' : 'issue';
    }
}
