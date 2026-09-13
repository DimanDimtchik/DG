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

        $deltaSign = self::deltaSignForVoucherType($voucherType);
        if ($deltaSign === 0) {
            StockMovementRepository::deleteForVoucher($voucherId);

            return;
        }

        StockMovementRepository::deleteForVoucher($voucherId);

        $movementDate = (string) ($voucher['voucher_date'] ?? date('Y-m-d'));
        $reason = $deltaSign > 0 ? 'purchase' : 'sale';
        if ($voucherType === 'credit') {
            $reason = 'purchase';
        } elseif ($voucherType === 'expense_reduction') {
            $reason = 'sale';
        }

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
            $sql .= ' AND min_stock > 0 AND stock_qty <= min_stock';
        }
        $sql .= ' ORDER BY stock_ort ASC, stock_halle ASC, stock_regal ASC, stock_platz ASC, title ASC';

        $rows = Database::pdo()->query($sql)->fetchAll(PDO::FETCH_ASSOC) ?: [];
        foreach ($rows as &$row) {
            $row['stock_qty'] = round((float) ($row['stock_qty'] ?? 0), 3);
            $row['min_stock'] = round((float) ($row['min_stock'] ?? 0), 3);
            $row['is_low'] = (float) ($row['min_stock'] ?? 0) > 0
                && (float) ($row['stock_qty'] ?? 0) <= (float) ($row['min_stock'] ?? 0);
            $row['stock_label'] = self::formatQty((float) $row['stock_qty'], (string) ($row['unit'] ?? 'Stück'));
            $row['stock_position_code'] = StockPositionCode::fromRow($row);
        }
        unset($row);

        return $rows;
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
        if ($voucherType === 'income') {
            return VoucherDocumentKind::isBookable($documentKind, 'income');
        }

        return in_array($voucherType, ['expense', 'expense_reduction', 'credit'], true);
    }

    private static function deltaSignForVoucherType(string $voucherType): int
    {
        return match ($voucherType) {
            'expense', 'credit' => 1,
            'income', 'expense_reduction' => -1,
            default => 0,
        };
    }
}
