<?php
declare(strict_types=1);

/** Lagerbewegungen — Audit-Log und Bestandsbasis. */
final class StockMovementRepository
{
    /**
     * @return list<array<string, mixed>>
     */
    public static function forArticle(int $articleId, int $limit = 50): array
    {
        if ($articleId < 1 || !Database::isConfigured()) {
            return [];
        }

        $stmt = Database::pdo()->prepare(
            'SELECT m.*, u.display_name AS created_by_name
             FROM dg_stock_movements m
             LEFT JOIN dg_users u ON u.id = m.created_by
             WHERE m.article_id = :article_id
             ORDER BY m.movement_date DESC, m.id DESC
             LIMIT ' . max(1, min(200, $limit))
        );
        $stmt->execute(['article_id' => $articleId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function recent(int $limit = 30): array
    {
        if (!Database::isConfigured()) {
            return [];
        }

        $stmt = Database::pdo()->query(
            'SELECT m.*, a.article_number, a.title, a.unit
             FROM dg_stock_movements m
             INNER JOIN dg_calendar_articles a ON a.id = m.article_id
             ORDER BY m.movement_date DESC, m.id DESC
             LIMIT ' . max(1, min(200, $limit))
        );

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public static function deleteForVoucher(int $voucherId): void
    {
        if ($voucherId < 1 || !Database::isConfigured()) {
            return;
        }

        $pdo = Database::pdo();
        $stmt = $pdo->prepare('SELECT DISTINCT article_id FROM dg_stock_movements WHERE voucher_id = :vid');
        $stmt->execute(['vid' => $voucherId]);
        $articleIds = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN) ?: []);

        $del = $pdo->prepare('DELETE FROM dg_stock_movements WHERE voucher_id = :vid');
        $del->execute(['vid' => $voucherId]);

        foreach ($articleIds as $articleId) {
            StockMovementRepository::recalculateArticleStock($articleId);
        }
    }

    public static function insert(
        int $articleId,
        string $movementDate,
        float $quantity,
        string $reason,
        ?int $voucherId = null,
        ?int $inventoryId = null,
        string $note = '',
        ?int $userId = null,
    ): int {
        if ($articleId < 1 || !Database::isConfigured()) {
            return 0;
        }

        $pdo = Database::pdo();
        $stmt = $pdo->prepare(
            'INSERT INTO dg_stock_movements
             (article_id, movement_date, quantity, reason, voucher_id, inventory_id, note, created_by)
             VALUES
             (:article_id, :movement_date, :quantity, :reason, :voucher_id, :inventory_id, :note, :created_by)'
        );
        $stmt->execute([
            'article_id' => $articleId,
            'movement_date' => $movementDate,
            'quantity' => round($quantity, 3),
            'reason' => self::sanitizeReason($reason),
            'voucher_id' => $voucherId !== null && $voucherId > 0 ? $voucherId : null,
            'inventory_id' => $inventoryId !== null && $inventoryId > 0 ? $inventoryId : null,
            'note' => mb_substr(trim($note), 0, 500),
            'created_by' => $userId !== null && $userId > 0 ? $userId : null,
        ]);

        self::recalculateArticleStock($articleId);

        return (int) $pdo->lastInsertId();
    }

    public static function recalculateArticleStock(int $articleId): void
    {
        if ($articleId < 1 || !Database::isConfigured()) {
            return;
        }

        $stmt = Database::pdo()->prepare(
            'SELECT COALESCE(SUM(quantity), 0) FROM dg_stock_movements WHERE article_id = :id'
        );
        $stmt->execute(['id' => $articleId]);
        $sum = round((float) ($stmt->fetchColumn() ?: 0), 3);

        $upd = Database::pdo()->prepare(
            'UPDATE dg_calendar_articles SET stock_qty = :qty WHERE id = :id AND track_stock = 1'
        );
        $upd->execute(['qty' => $sum, 'id' => $articleId]);
    }

    public static function sanitizeReason(string $reason): string
    {
        $reason = strtolower(trim($reason));
        $allowed = ['purchase', 'sale', 'adjustment', 'inventory', 'opening', 'reversal'];

        return in_array($reason, $allowed, true) ? $reason : 'adjustment';
    }

    public static function reasonLabel(string $reason): string
    {
        return match (self::sanitizeReason($reason)) {
            'purchase' => 'Wareneingang (Einkauf)',
            'sale' => 'Warenausgang (Verkauf)',
            'adjustment' => 'Manuelle Korrektur',
            'inventory' => 'Inventur',
            'opening' => 'Anfangsbestand',
            'reversal' => 'Storno / Beleg gelöscht',
            default => 'Bewegung',
        };
    }
}
