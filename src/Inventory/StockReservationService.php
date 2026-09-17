<?php
declare(strict_types=1);

/** Mengen-Reservierung: Angebot/AB ab Versendet/Angenommen. */
final class StockReservationService
{
    /** @var list<string> */
    private static array $lastWarnings = [];

    /** @return list<string> */
    public static function takeLastWarnings(): array
    {
        $warnings = self::$lastWarnings;
        self::$lastWarnings = [];

        return $warnings;
    }

    /**
     * Beleg speichern/Status ändern → Reservierungen idempotent neu aufbauen.
     *
     * @return list<string> Warnhinweise (Unterbestand), nie blockierend
     */
    public static function syncForVoucher(int $voucherId): array
    {
        self::$lastWarnings = [];
        if ($voucherId < 1 || !Database::isConfigured()) {
            return [];
        }

        MigrationRunner::runPending();

        $voucher = VoucherRepository::findById($voucherId);
        if ($voucher === null) {
            return [];
        }

        self::deleteForVoucher($voucherId);

        if (!empty($voucher['is_draft'])) {
            return [];
        }

        $voucherType = VoucherRepository::normalizeVoucherType((string) ($voucher['voucher_type'] ?? ''));
        if ($voucherType !== 'income') {
            return [];
        }

        $kind = VoucherDocumentKind::sanitize((string) ($voucher['document_kind'] ?? ''));
        $status = VoucherDocumentStatus::sanitize((string) ($voucher['document_status'] ?? ''));

        if ($status === VoucherDocumentStatus::CANCELLED) {
            return [];
        }

        // LS / Rechnung ohne LS: Reservierungen der Kette verbrauchen
        if (self::shouldConsumeChainReservations($kind, $voucherId)) {
            self::consumeChainReservations($voucherId);
            self::$lastWarnings = self::shortageWarningsForVoucher($voucherId);

            return self::$lastWarnings;
        }

        if (!self::isReservableKind($kind) || !self::isReservableStatus($status)) {
            return [];
        }

        // AB ersetzt Reservierung des Vorgänger-Angebots
        $parentId = (int) ($voucher['parent_voucher_id'] ?? 0);
        if ($kind === VoucherDocumentKind::ORDER_CONFIRMATION && $parentId > 0) {
            self::releaseForVoucher($parentId);
        }

        $items = VoucherRepository::itemsForVoucher($voucherId);
        $pdo = Database::pdo();
        $stmt = $pdo->prepare(
            'INSERT INTO dg_stock_reservations
             (article_id, voucher_id, voucher_item_id, quantity, status)
             VALUES (:article_id, :voucher_id, :voucher_item_id, :quantity, \'active\')'
        );

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
            if ($qty < 0.0005) {
                continue;
            }
            $stmt->execute([
                'article_id' => $articleId,
                'voucher_id' => $voucherId,
                'voucher_item_id' => (int) ($item['id'] ?? 0) ?: null,
                'quantity' => round($qty, 3),
            ]);
        }

        self::$lastWarnings = self::shortageWarningsForVoucher($voucherId);

        return self::$lastWarnings;
    }

    public static function revertForVoucher(int $voucherId): void
    {
        self::deleteForVoucher($voucherId);
    }

    public static function deleteForVoucher(int $voucherId): void
    {
        if ($voucherId < 1 || !Database::isConfigured() || !self::tableReady()) {
            return;
        }
        $stmt = Database::pdo()->prepare('DELETE FROM dg_stock_reservations WHERE voucher_id = :vid');
        $stmt->execute(['vid' => $voucherId]);
    }

    public static function releaseForVoucher(int $voucherId): void
    {
        if ($voucherId < 1 || !Database::isConfigured() || !self::tableReady()) {
            return;
        }
        $stmt = Database::pdo()->prepare(
            "UPDATE dg_stock_reservations SET status = 'released'
             WHERE voucher_id = :vid AND status = 'active'"
        );
        $stmt->execute(['vid' => $voucherId]);
    }

    public static function reservedQty(int $articleId): float
    {
        if ($articleId < 1 || !Database::isConfigured() || !self::tableReady()) {
            return 0.0;
        }
        $stmt = Database::pdo()->prepare(
            "SELECT COALESCE(SUM(quantity), 0) FROM dg_stock_reservations
             WHERE article_id = :aid AND status = 'active'"
        );
        $stmt->execute(['aid' => $articleId]);

        return round((float) $stmt->fetchColumn(), 3);
    }

    /**
     * @param list<int> $articleIds
     * @return array<int, float>
     */
    public static function reservedQtyByArticleIds(array $articleIds): array
    {
        $articleIds = array_values(array_unique(array_filter(array_map('intval', $articleIds))));
        if ($articleIds === [] || !Database::isConfigured() || !self::tableReady()) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($articleIds), '?'));
        $stmt = Database::pdo()->prepare(
            "SELECT article_id, COALESCE(SUM(quantity), 0) AS qty
             FROM dg_stock_reservations
             WHERE status = 'active' AND article_id IN ({$placeholders})
             GROUP BY article_id"
        );
        $stmt->execute($articleIds);
        $map = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $map[(int) $row['article_id']] = round((float) $row['qty'], 3);
        }

        return $map;
    }

    /**
     * @return list<string>
     */
    public static function shortageWarningsForVoucher(int $voucherId): array
    {
        $warnings = [];
        $items = VoucherRepository::itemsForVoucher($voucherId);
        $needByArticle = [];
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }
            $articleId = (int) ($item['article_id'] ?? 0);
            $qty = abs((float) ($item['quantity'] ?? 0));
            if ($articleId < 1 || $qty < 0.0005) {
                continue;
            }
            $needByArticle[$articleId] = ($needByArticle[$articleId] ?? 0.0) + $qty;
        }

        foreach ($needByArticle as $articleId => $need) {
            $snap = StockAvailabilityService::snapshot($articleId);
            if ($snap === null) {
                continue;
            }
            // Nach Sync: Reservierung dieses Belegs steckt schon in reserved — Verfügbarkeit
            // vor diesem Beleg ≈ available + Anteil dieses Belegs an reserved (vereinfacht: available < 0)
            if ($snap['available'] < -0.0005) {
                $shortageQty = round(abs((float) $snap['available']), 3);
                if ($shortageQty < 0.0005) {
                    $shortageQty = round((float) $need, 3);
                }
                PurchaseListService::ensureForShortage($articleId, $shortageQty, $voucherId);
                $suffix = StockPurchaseSettings::isBlocking()
                    ? ' Bitte Nachbestellen (Policy: blockieren).'
                    : ' Speichern trotzdem möglich.';
                $warnings[] = sprintf(
                    'Unterbestand: %s — Bestand %s, reserviert %s, verfügbar %s (Bedarf dieses Belegs %s).%s',
                    $snap['title'] !== '' ? $snap['title'] : ('Artikel #' . $articleId),
                    StockMovementService::formatQty($snap['stock_qty'], $snap['unit']),
                    StockMovementService::formatQty($snap['reserved'], $snap['unit']),
                    StockMovementService::formatQty($snap['available'], $snap['unit']),
                    StockMovementService::formatQty((float) $need, $snap['unit']),
                    $suffix
                );
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
                $warnings[] = sprintf(
                    'Nachbestellbedarf: %s — verfügbar %s, Zielbestand %s.',
                    $snap['title'] !== '' ? $snap['title'] : ('Artikel #' . $articleId),
                    StockMovementService::formatQty($snap['available'], $snap['unit']),
                    StockMovementService::formatQty(
                        PurchaseListService::restockTarget($snap['min_stock']),
                        $snap['unit']
                    )
                );
            }
        }

        return $warnings;
    }

    private static function isReservableKind(string $kind): bool
    {
        return in_array($kind, [
            VoucherDocumentKind::OFFER,
            VoucherDocumentKind::ORDER_CONFIRMATION,
        ], true);
    }

    private static function isReservableStatus(string $status): bool
    {
        return in_array($status, [
            VoucherDocumentStatus::SENT,
            VoucherDocumentStatus::ACCEPTED,
        ], true);
    }

    private static function shouldConsumeChainReservations(string $kind, int $voucherId): bool
    {
        if ($kind === VoucherDocumentKind::DELIVERY_NOTE) {
            return true;
        }

        if (!in_array($kind, [
            VoucherDocumentKind::PARTIAL_INVOICE,
            VoucherDocumentKind::INVOICE,
            VoucherDocumentKind::FINAL_INVOICE,
        ], true)) {
            return false;
        }

        // Rechnung ohne LS in der Kette bucht Bestand → Reserve verbrauchen
        return !VoucherDocumentChain::subtreeHasKind($voucherId, VoucherDocumentKind::DELIVERY_NOTE);
    }

    private static function consumeChainReservations(int $voucherId): void
    {
        $ids = [];
        $currentId = $voucherId;
        $guard = 0;
        while ($currentId > 0 && $guard < 50) {
            $guard++;
            $ids[] = $currentId;
            $row = VoucherRepository::findById($currentId);
            if ($row === null) {
                break;
            }
            $currentId = (int) ($row['parent_voucher_id'] ?? 0);
        }
        $ids = array_values(array_unique(array_filter($ids)));
        if ($ids === [] || !self::tableReady()) {
            return;
        }
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = Database::pdo()->prepare(
            "UPDATE dg_stock_reservations SET status = 'consumed'
             WHERE status = 'active' AND voucher_id IN ({$placeholders})"
        );
        $stmt->execute($ids);
    }

    private static function tableReady(): bool
    {
        static $ready = null;
        if ($ready !== null) {
            return $ready;
        }
        try {
            $ready = Database::pdo()->query("SHOW TABLES LIKE 'dg_stock_reservations'")->fetchColumn() !== false;
        } catch (Throwable) {
            $ready = false;
        }

        return $ready;
    }
}
