<?php
declare(strict_types=1);

/**
 * Amazon Business Stufe 2: Einkaufslisten-Eintrag → Ordering API → Persistenz.
 * Nutzt vorhandenen AmazonBusinessClient; kein Auto-Kauf.
 */
final class AmazonBusinessOrderService
{
    /**
     * Bestellt einen offenen Listeneintrag bei Amazon Business.
     *
     * @return array{ok: bool, message: string, external_order_id: string}
     */
    public static function orderPurchaseListItem(int $purchaseListId, ?float $qty = null): array
    {
        $fail = static fn (string $message): array => [
            'ok' => false,
            'message' => $message,
            'external_order_id' => '',
        ];

        if ($purchaseListId < 1) {
            return $fail('Ungültiger Einkaufslisten-Eintrag.');
        }
        if (!AmazonBusinessSettings::isReady()) {
            return $fail('Amazon Business ist nicht aktiv oder Credentials fehlen.');
        }

        MigrationRunner::runPending();
        if (!PurchaseListRepository::tableReady()) {
            return $fail('Einkaufsliste ist nicht bereit.');
        }

        $row = PurchaseListRepository::findById($purchaseListId);
        if ($row === null) {
            return $fail('Eintrag nicht gefunden.');
        }
        if ((string) ($row['status'] ?? '') !== PurchaseListRepository::STATUS_OPEN) {
            return $fail('Nur offene Einträge können bei Amazon bestellt werden.');
        }

        $articleId = (int) ($row['article_id'] ?? 0);
        if ($articleId < 1) {
            return $fail('Kein Artikel am Eintrag.');
        }

        $asin = self::asinFromPreferredSource($articleId);
        if ($asin === '') {
            return $fail('Keine ASIN in der bevorzugten Einkaufsquelle (Feld Artikelnummer Lieferant / external_sku).');
        }

        $orderQty = $qty !== null && $qty > 0.0005
            ? round($qty, 3)
            : round((float) ($row['suggested_qty'] ?? 0), 3);
        if ($orderQty < 0.0005) {
            return $fail('Menge muss größer als 0 sein.');
        }

        $externalOrderId = sprintf('CRM-PL-%d-%s', $purchaseListId, date('YmdHis'));
        $lineItems = [
            [
                'asin' => $asin,
                'quantity' => $orderQty,
                'external_line_id' => 'PL-' . $purchaseListId,
            ],
        ];

        try {
            $response = AmazonBusinessClient::placeOrder($externalOrderId, $lineItems);
        } catch (InvalidArgumentException $e) {
            return $fail($e->getMessage());
        } catch (Throwable $e) {
            $msg = trim($e->getMessage());

            return $fail($msg !== '' ? $msg : 'Amazon-Bestellung fehlgeschlagen.');
        }

        $savedId = (string) ($response['externalId'] ?? $externalOrderId);
        if ($savedId === '') {
            $savedId = $externalOrderId;
        }

        $payload = self::payloadSnippet($response);
        $stored = PurchaseListService::markAmazonOrdered(
            $purchaseListId,
            $savedId,
            $orderQty,
            $payload
        );
        if ($stored === '') {
            return [
                'ok' => false,
                'message' => 'Amazon hat angenommen, aber die Bestellreferenz konnte nicht gespeichert werden. Bitte Eintrag prüfen.',
                'external_order_id' => $savedId,
            ];
        }

        return [
            'ok' => true,
            'message' => 'Bei Amazon Business bestellt.',
            'external_order_id' => $stored,
        ];
    }

    /** ASIN aus bevorzugter Einkaufsquelle (`external_sku`). */
    public static function asinFromPreferredSource(int $articleId): string
    {
        if ($articleId < 1) {
            return '';
        }
        $preferred = ArticlePurchaseSourceRepository::preferredForArticle($articleId);
        if ($preferred === null) {
            return '';
        }
        $sku = strtoupper(trim((string) ($preferred['external_sku'] ?? '')));
        if ($sku === '') {
            return '';
        }
        // Typische ASIN: 10 alphanumerisch; erlauben auch längere Lieferanten-SKU falls Amazon sie akzeptiert
        if (!preg_match('/^[A-Z0-9]{10}$/', $sku)) {
            return '';
        }

        return $sku;
    }

    /** Ob der Listeneintrag theoretisch bestellbar ist (ohne API-Call). */
    public static function canOrderItem(array $purchaseListRow): bool
    {
        if (!AmazonBusinessSettings::isReady()) {
            return false;
        }
        if ((string) ($purchaseListRow['status'] ?? '') !== PurchaseListRepository::STATUS_OPEN) {
            return false;
        }
        $articleId = (int) ($purchaseListRow['article_id'] ?? 0);

        return self::asinFromPreferredSource($articleId) !== '';
    }

    /**
     * @param array<string, mixed> $response
     */
    private static function payloadSnippet(array $response): string
    {
        $safe = $response;
        unset($safe['access_token'], $safe['refresh_token'], $safe['client_secret']);
        try {
            $json = json_encode($safe, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            return '';
        }

        return mb_substr($json, 0, 4000);
    }
}
