<?php
declare(strict_types=1);

/** Strichcode-Auflösung: Artikel (GTIN), Palette/Platz, Karton */
final class StockBarcodeService
{
    /**
     * @return array{
     *   type: string,
     *   label: string,
     *   article?: array<string, mixed>,
     *   place?: array<string, mixed>,
     *   package?: array<string, mixed>
     * }
     */
    public static function resolve(string $code): array
    {
        $code = self::normalize($code);
        if ($code === '') {
            throw new InvalidArgumentException('Strichcode ist leer.');
        }

        $package = StockPackageRepository::findByBarcode($code);
        if ($package !== null) {
            $article = CalendarArticleRepository::findById((int) ($package['article_id'] ?? 0));

            return [
                'type' => 'package',
                'label' => 'Karton ' . $code,
                'package' => $package,
                'article' => $article,
                'place' => !empty($package['place_id'])
                    ? StockStructureRepository::findPlace((int) $package['place_id'])
                    : null,
            ];
        }

        $place = StockStructureRepository::findPlaceByBarcode($code);
        if ($place !== null) {
            return [
                'type' => 'place',
                'label' => 'Platz ' . (string) ($place['position_code'] ?? $code),
                'place' => $place,
            ];
        }

        $article = self::findArticleByBarcode($code);
        if ($article !== null) {
            return [
                'type' => 'article',
                'label' => (string) ($article['title'] ?? $code),
                'article' => $article,
            ];
        }

        throw new InvalidArgumentException('Strichcode nicht gefunden: ' . $code);
    }

    public static function normalize(string $code): string
    {
        $code = trim($code);
        if ($code === '') {
            return '';
        }
        $code = preg_replace('/\s+/u', '', $code) ?? '';

        return mb_substr($code, 0, 64, 'UTF-8');
    }

    public static function generatePlaceBarcode(int $placeId): string
    {
        $place = StockStructureRepository::findPlace($placeId);
        if ($place === null) {
            throw new InvalidArgumentException('Platz nicht gefunden.');
        }

        $existing = trim((string) ($place['barcode'] ?? ''));
        if ($existing !== '') {
            return $existing;
        }

        $positionCode = (string) ($place['position_code'] ?? '');
        $base = $positionCode !== ''
            ? preg_replace('/[^0-9A-Za-z]/', '', $positionCode)
            : ('PL' . $placeId);
        $barcode = self::ensureUniquePlaceBarcode((string) $base, $placeId);

        Database::pdo()->prepare('UPDATE dg_stock_places SET barcode = :barcode WHERE id = :id')
            ->execute(['barcode' => $barcode, 'id' => $placeId]);

        return $barcode;
    }

    public static function generatePackageBarcode(int $articleId): string
    {
        $article = CalendarArticleRepository::findById($articleId);
        if ($article === null) {
            throw new InvalidArgumentException('Artikel nicht gefunden.');
        }

        $prefix = 'KRT';
        $gtin = trim((string) ($article['gtin'] ?? ''));
        if ($gtin !== '') {
            $prefix = 'K' . preg_replace('/\D/', '', mb_substr($gtin, -6, 6, 'UTF-8'));
        }

        for ($attempt = 0; $attempt < 20; ++$attempt) {
            $candidate = $prefix . strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));
            if (StockPackageRepository::findByBarcode($candidate) === null
                && StockStructureRepository::findPlaceByBarcode($candidate) === null) {
                return $candidate;
            }
        }

        return 'KRT' . $articleId . 'T' . time();
    }

    /** @return array<string, mixed>|null */
    private static function findArticleByBarcode(string $code): ?array
    {
        if (!Database::isConfigured()) {
            return null;
        }

        $pdo = Database::pdo();
        $stmt = $pdo->prepare(
            "SELECT * FROM dg_calendar_articles
             WHERE track_stock = 1 AND catalog_kind = 'product'
               AND (gtin = :code OR article_number = :code2)
             LIMIT 1"
        );
        $stmt->execute(['code' => $code, 'code2' => $code]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    private static function ensureUniquePlaceBarcode(string $base, int $placeId): string
    {
        $base = $base !== '' ? $base : ('PL' . $placeId);
        $candidate = mb_substr($base, 0, 58, 'UTF-8');
        if (StockStructureRepository::findPlaceByBarcode($candidate) === null) {
            return $candidate;
        }

        return mb_substr($base, 0, 50, 'UTF-8') . 'P' . $placeId;
    }
}
