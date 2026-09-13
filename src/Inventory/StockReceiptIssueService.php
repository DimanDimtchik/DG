<?php
declare(strict_types=1);

/** Wareneingang und Warenausgang mit Strichcode und Belegbezug */
final class StockReceiptIssueService
{
    /**
     * @param list<array<string, mixed>> $lines
     * @return list<int> movement ids
     */
    public static function processReceipt(array $lines, ?int $voucherId, ?int $userId, string $note = ''): array
    {
        $ids = [];
        foreach ($lines as $line) {
            if (!is_array($line)) {
                continue;
            }
            $ids[] = self::receiptLine($line, $voucherId, $userId, $note);
        }

        return array_values(array_filter($ids));
    }

    /**
     * @param list<array<string, mixed>> $lines
     * @return list<int>
     */
    public static function processIssue(array $lines, ?int $voucherId, ?int $userId, string $note = ''): array
    {
        $ids = [];
        foreach ($lines as $line) {
            if (!is_array($line)) {
                continue;
            }
            $ids[] = self::issueLine($line, $voucherId, $userId, $note);
        }

        return array_values(array_filter($ids));
    }

    /** @param array<string, mixed> $line */
    public static function receiptLine(array $line, ?int $voucherId, ?int $userId, string $note = ''): int
    {
        $articleId = (int) ($line['article_id'] ?? 0);
        $qty = round((float) str_replace(',', '.', (string) ($line['quantity'] ?? 0)), 3);
        $placeId = (int) ($line['place_id'] ?? 0);
        $packageBarcode = StockBarcodeService::normalize((string) ($line['package_barcode'] ?? ''));
        $createPackage = !empty($line['create_package']);

        if ($articleId < 1 && $packageBarcode !== '') {
            $package = StockPackageRepository::findByBarcode($packageBarcode);
            if ($package !== null) {
                throw new InvalidArgumentException('Karton-Strichcode existiert bereits.');
            }
        }

        if ($articleId < 1) {
            throw new InvalidArgumentException('Artikel fehlt.');
        }
        if ($qty <= 0) {
            throw new InvalidArgumentException('Menge muss größer als 0 sein.');
        }

        self::assertTrackableArticle($articleId);

        if ($placeId < 1) {
            $suggested = StockPlaceService::suggestFreePlaces(null, null, 1);
            if ($suggested !== []) {
                $placeId = (int) ($suggested[0]['id'] ?? 0);
            }
        }

        $packageId = null;
        if ($createPackage || $packageBarcode !== '') {
            $packageId = StockPackageRepository::create(
                $articleId,
                $qty,
                $placeId > 0 ? $placeId : null,
                $packageBarcode !== '' ? $packageBarcode : null,
            );
            if ($placeId > 0) {
                StockPlaceService::assignFlexiblePlace($articleId, $placeId, $qty);
            }
        } elseif ($placeId > 0) {
            StockPlaceService::assignFlexiblePlace($articleId, $placeId, $qty);
        }

        return StockMovementRepository::insert(
            $articleId,
            date('Y-m-d'),
            $qty,
            'receipt',
            $voucherId !== null && $voucherId > 0 ? $voucherId : null,
            null,
            $note !== '' ? $note : 'Wareneingang',
            $userId,
            $placeId > 0 ? $placeId : null,
            $packageId,
        );
    }

    /** @param array<string, mixed> $line */
    public static function issueLine(array $line, ?int $voucherId, ?int $userId, string $note = ''): int
    {
        $articleId = (int) ($line['article_id'] ?? 0);
        $qty = round((float) str_replace(',', '.', (string) ($line['quantity'] ?? 0)), 3);
        $placeId = (int) ($line['place_id'] ?? 0);
        $packageBarcode = StockBarcodeService::normalize((string) ($line['package_barcode'] ?? ''));

        $packageId = null;
        if ($packageBarcode !== '') {
            $package = StockPackageRepository::findByBarcode($packageBarcode);
            if ($package === null) {
                throw new InvalidArgumentException('Karton-Strichcode nicht gefunden.');
            }
            if (($package['status'] ?? '') !== 'in_stock') {
                throw new InvalidArgumentException('Karton ist nicht mehr im Bestand.');
            }
            $packageId = (int) $package['id'];
            $articleId = (int) ($package['article_id'] ?? 0);
            $qty = round((float) ($package['quantity'] ?? $qty), 3);
            $placeId = (int) ($package['place_id'] ?? $placeId);
            StockPackageRepository::markIssued($packageId);
            if ($placeId > 0) {
                StockPlaceService::releasePlace($placeId, $articleId);
            }
        }

        if ($articleId < 1) {
            throw new InvalidArgumentException('Artikel fehlt.');
        }
        if ($qty <= 0) {
            throw new InvalidArgumentException('Menge muss größer als 0 sein.');
        }

        self::assertTrackableArticle($articleId);

        return StockMovementRepository::insert(
            $articleId,
            date('Y-m-d'),
            -abs($qty),
            'issue',
            $voucherId !== null && $voucherId > 0 ? $voucherId : null,
            null,
            $note !== '' ? $note : 'Warenausgang',
            $userId,
            $placeId > 0 ? $placeId : null,
            $packageId,
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function outboundVoucherOptions(): array
    {
        if (!Database::isConfigured()) {
            return [];
        }

        $stmt = Database::pdo()->query(
            "SELECT v.id, v.voucher_date, v.document_kind, v.invoice_number, v.description,
                    c.display_name AS contact_name
             FROM dg_vouchers v
             LEFT JOIN dg_contacts c ON c.id = v.contact_id
             WHERE v.is_draft = 0 AND v.voucher_type = 'income'
               AND v.document_kind IN ('delivery_note', 'order_confirmation')
             ORDER BY v.voucher_date DESC, v.id DESC
             LIMIT 100"
        );
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        foreach ($rows as &$row) {
            $kind = (string) ($row['document_kind'] ?? '');
            $row['label'] = VoucherDocumentKind::label($kind)
                . ' #' . (int) ($row['id'])
                . ' · ' . (string) ($row['voucher_date'] ?? '')
                . (($row['contact_name'] ?? '') !== '' ? ' · ' . (string) $row['contact_name'] : '');
        }
        unset($row);

        return $rows;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function linesFromVoucher(int $voucherId): array
    {
        if ($voucherId < 1) {
            return [];
        }

        $items = VoucherRepository::itemsForVoucher($voucherId);
        $lines = [];
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
            $lines[] = [
                'article_id' => $articleId,
                'article_number' => (string) ($article['article_number'] ?? ''),
                'title' => (string) ($article['title'] ?? ''),
                'gtin' => (string) ($article['gtin'] ?? ''),
                'quantity' => round((float) ($item['quantity'] ?? 0), 3),
                'unit' => (string) ($article['unit'] ?? ''),
            ];
        }

        return $lines;
    }

    /** @param array<string, mixed> $post */
    public static function receiptFromPost(array $post, ?int $userId): int
    {
        $lines = is_array($post['lines'] ?? null) ? $post['lines'] : [];
        if ($lines === [] && isset($post['scan_article_id'])) {
            $lines[] = [
                'article_id' => (int) $post['scan_article_id'],
                'quantity' => (float) str_replace(',', '.', (string) ($post['scan_quantity'] ?? '1')),
                'place_id' => (int) ($post['scan_place_id'] ?? 0),
                'package_barcode' => (string) ($post['scan_package_barcode'] ?? ''),
                'create_package' => !empty($post['scan_create_package']),
            ];
        }
        if ($lines === []) {
            throw new InvalidArgumentException('Keine Positionen für Wareneingang.');
        }

        $voucherId = (int) ($post['voucher_id'] ?? 0);
        $note = trim((string) ($post['note'] ?? ''));
        $processed = self::processReceipt($lines, $voucherId > 0 ? $voucherId : null, $userId, $note);

        return count($processed);
    }

    /** @param array<string, mixed> $post */
    public static function issueFromPost(array $post, ?int $userId): int
    {
        $voucherId = (int) ($post['voucher_id'] ?? 0);
        $lines = is_array($post['lines'] ?? null) ? $post['lines'] : [];

        if ($lines === [] && $voucherId > 0) {
            foreach (self::linesFromVoucher($voucherId) as $vLine) {
                $lines[] = [
                    'article_id' => (int) $vLine['article_id'],
                    'quantity' => (float) $vLine['quantity'],
                ];
            }
        }

        if ($lines === [] && isset($post['scan_article_id'])) {
            $lines[] = [
                'article_id' => (int) $post['scan_article_id'],
                'quantity' => (float) str_replace(',', '.', (string) ($post['scan_quantity'] ?? '1')),
                'package_barcode' => (string) ($post['scan_package_barcode'] ?? ''),
                'place_id' => (int) ($post['scan_place_id'] ?? 0),
            ];
        }

        if ($lines === []) {
            throw new InvalidArgumentException('Keine Positionen für Warenausgang.');
        }

        $note = trim((string) ($post['note'] ?? ''));
        if ($voucherId > 0 && $note === '') {
            $voucher = VoucherRepository::findById($voucherId);
            if ($voucher !== null) {
                $note = 'Warenausgang Beleg #' . $voucherId . ' (' . VoucherDocumentKind::label((string) ($voucher['document_kind'] ?? '')) . ')';
            }
        }

        $processed = self::processIssue($lines, $voucherId > 0 ? $voucherId : null, $userId, $note);

        return count($processed);
    }

    private static function assertTrackableArticle(int $articleId): void
    {
        $article = CalendarArticleRepository::findById($articleId);
        if ($article === null || empty($article['track_stock'])) {
            throw new InvalidArgumentException('Artikel nicht gefunden oder ohne Lagerführung.');
        }
    }
}
