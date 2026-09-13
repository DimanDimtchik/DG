<?php
declare(strict_types=1);

/** Lager-Kartons mit eigenem Strichcode */
final class StockPackageRepository
{
    /** @return array<string, mixed>|null */
    public static function findByBarcode(string $barcode): ?array
    {
        $barcode = StockBarcodeService::normalize($barcode);
        if ($barcode === '' || !Database::isConfigured()) {
            return null;
        }

        $stmt = Database::pdo()->prepare(
            'SELECT p.*, a.article_number, a.title, a.unit
             FROM dg_stock_packages p
             INNER JOIN dg_calendar_articles a ON a.id = p.article_id
             WHERE p.barcode = :barcode
             LIMIT 1'
        );
        $stmt->execute(['barcode' => $barcode]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    /** @return array<string, mixed>|null */
    public static function findById(int $id): ?array
    {
        if ($id < 1 || !Database::isConfigured()) {
            return null;
        }

        $stmt = Database::pdo()->prepare(
            'SELECT p.*, a.article_number, a.title, a.unit
             FROM dg_stock_packages p
             INNER JOIN dg_calendar_articles a ON a.id = p.article_id
             WHERE p.id = :id LIMIT 1'
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public static function create(
        int $articleId,
        float $quantity,
        ?int $placeId = null,
        ?string $barcode = null,
    ): int {
        if ($articleId < 1) {
            throw new InvalidArgumentException('Artikel fehlt.');
        }
        if ($quantity <= 0) {
            throw new InvalidArgumentException('Menge muss größer als 0 sein.');
        }

        $barcode = StockBarcodeService::normalize((string) ($barcode ?? ''));
        if ($barcode === '') {
            $barcode = StockBarcodeService::generatePackageBarcode($articleId);
        }
        if (self::findByBarcode($barcode) !== null) {
            throw new InvalidArgumentException('Karton-Strichcode ist bereits vergeben.');
        }

        $pdo = Database::pdo();
        $pdo->prepare(
            'INSERT INTO dg_stock_packages (article_id, place_id, barcode, quantity, status)
             VALUES (:article_id, :place_id, :barcode, :quantity, \'in_stock\')'
        )->execute([
            'article_id' => $articleId,
            'place_id' => $placeId !== null && $placeId > 0 ? $placeId : null,
            'barcode' => $barcode,
            'quantity' => round($quantity, 3),
        ]);

        return (int) $pdo->lastInsertId();
    }

    public static function markIssued(int $packageId): void
    {
        if ($packageId < 1) {
            return;
        }

        Database::pdo()->prepare(
            'UPDATE dg_stock_packages SET status = \'issued\', issued_at = NOW() WHERE id = :id'
        )->execute(['id' => $packageId]);
    }
}
