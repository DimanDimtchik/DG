<?php
declare(strict_types=1);

/** Inventur — gezählter Bestand vs. Buchbestand. */
final class StockInventoryService
{
    /**
     * @return list<array<string, mixed>>
     */
    public static function openInventories(): array
    {
        if (!Database::isConfigured()) {
            return [];
        }

        MigrationRunner::runPending();

        $stmt = Database::pdo()->query(
            "SELECT * FROM dg_stock_inventories WHERE status = 'open' ORDER BY inventory_date DESC, id DESC"
        );

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function find(int $inventoryId): ?array
    {
        if ($inventoryId < 1 || !Database::isConfigured()) {
            return null;
        }

        $stmt = Database::pdo()->prepare('SELECT * FROM dg_stock_inventories WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $inventoryId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function linesForInventory(int $inventoryId): array
    {
        if ($inventoryId < 1 || !Database::isConfigured()) {
            return [];
        }

        $stmt = Database::pdo()->prepare(
            'SELECT l.*, a.article_number, a.title, a.unit
             FROM dg_stock_inventory_lines l
             INNER JOIN dg_calendar_articles a ON a.id = l.article_id
             WHERE l.inventory_id = :id
             ORDER BY a.title ASC'
        );
        $stmt->execute(['id' => $inventoryId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public static function start(string $inventoryDate, string $note, ?int $userId = null): int
    {
        if (!Database::isConfigured()) {
            throw new RuntimeException('Datenbank nicht verbunden.');
        }

        MigrationRunner::runPending();

        if (strtotime($inventoryDate) === false) {
            throw new InvalidArgumentException('Ungültiges Inventurdatum.');
        }
        $inventoryDate = date('Y-m-d', strtotime($inventoryDate));

        $open = self::openInventories();
        if ($open !== []) {
            throw new InvalidArgumentException('Es gibt bereits eine offene Inventur. Bitte zuerst abschließen.');
        }

        $pdo = Database::pdo();
        $stmt = $pdo->prepare(
            'INSERT INTO dg_stock_inventories (inventory_date, note, status, created_by)
             VALUES (:inventory_date, :note, \'open\', :created_by)'
        );
        $stmt->execute([
            'inventory_date' => $inventoryDate,
            'note' => mb_substr(trim($note), 0, 500),
            'created_by' => $userId !== null && $userId > 0 ? $userId : null,
        ]);
        $inventoryId = (int) $pdo->lastInsertId();

        $articles = StockMovementService::stockOverview(false);
        $lineStmt = $pdo->prepare(
            'INSERT INTO dg_stock_inventory_lines
             (inventory_id, article_id, book_quantity, counted_quantity, diff_quantity)
             VALUES (:inventory_id, :article_id, :book_quantity, 0, 0)'
        );
        foreach ($articles as $article) {
            $lineStmt->execute([
                'inventory_id' => $inventoryId,
                'article_id' => (int) ($article['id'] ?? 0),
                'book_quantity' => (float) ($article['stock_qty'] ?? 0),
            ]);
        }

        return $inventoryId;
    }

    /**
     * @param array<int, float|string> $countedByArticleId
     */
    public static function saveCounts(int $inventoryId, array $countedByArticleId): void
    {
        $inventory = self::find($inventoryId);
        if ($inventory === null || ($inventory['status'] ?? '') !== 'open') {
            throw new InvalidArgumentException('Inventur nicht gefunden oder bereits abgeschlossen.');
        }

        $pdo = Database::pdo();
        $fetchBook = $pdo->prepare(
            'SELECT book_quantity FROM dg_stock_inventory_lines
             WHERE inventory_id = :inv AND article_id = :article LIMIT 1'
        );
        $stmt = $pdo->prepare(
            'UPDATE dg_stock_inventory_lines
             SET counted_quantity = :counted, diff_quantity = :diff
             WHERE inventory_id = :inv AND article_id = :article'
        );

        foreach ($countedByArticleId as $articleId => $counted) {
            $articleId = (int) $articleId;
            if ($articleId < 1) {
                continue;
            }
            $countedQty = round((float) str_replace(',', '.', (string) $counted), 3);
            $fetchBook->execute(['inv' => $inventoryId, 'article' => $articleId]);
            $bookQty = round((float) ($fetchBook->fetchColumn() ?: 0), 3);
            $stmt->execute([
                'counted' => $countedQty,
                'diff' => round($countedQty - $bookQty, 3),
                'inv' => $inventoryId,
                'article' => $articleId,
            ]);
        }
    }

    public static function close(int $inventoryId, ?int $userId = null): int
    {
        $inventory = self::find($inventoryId);
        if ($inventory === null || ($inventory['status'] ?? '') !== 'open') {
            throw new InvalidArgumentException('Inventur nicht gefunden oder bereits abgeschlossen.');
        }

        $movementDate = (string) ($inventory['inventory_date'] ?? date('Y-m-d'));
        $lines = self::linesForInventory($inventoryId);
        $applied = 0;

        foreach ($lines as $line) {
            $diff = round((float) ($line['diff_quantity'] ?? 0), 3);
            if (abs($diff) < 0.0005) {
                continue;
            }
            $articleId = (int) ($line['article_id'] ?? 0);
            if ($articleId < 1) {
                continue;
            }

            StockMovementRepository::insert(
                $articleId,
                $movementDate,
                $diff,
                'inventory',
                null,
                $inventoryId,
                'Inventur #' . $inventoryId,
                $userId,
            );
            $applied++;
        }

        $stmt = Database::pdo()->prepare(
            "UPDATE dg_stock_inventories SET status = 'closed', closed_at = NOW() WHERE id = :id"
        );
        $stmt->execute(['id' => $inventoryId]);

        return $applied;
    }

    /**
     * @return list<array<string, string>>
     */
    public static function exportInventoryCsv(int $inventoryId): array
    {
        $rows = [];
        foreach (self::linesForInventory($inventoryId) as $line) {
            $rows[] = [
                'article_number' => (string) ($line['article_number'] ?? ''),
                'title' => (string) ($line['title'] ?? ''),
                'unit' => (string) ($line['unit'] ?? ''),
                'book_quantity' => (string) ($line['book_quantity'] ?? '0'),
                'counted_quantity' => (string) ($line['counted_quantity'] ?? '0'),
                'diff_quantity' => (string) ($line['diff_quantity'] ?? '0'),
            ];
        }

        return $rows;
    }
}
