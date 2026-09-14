<?php
declare(strict_types=1);

/** API: Strichcode auflösen und Belegpositionen für Warenausgang */
final class StockScanApi
{
    public static function handle(): void
    {
        header('Content-Type: application/json; charset=utf-8');

        $user = AuthService::user();
        if (!$user || !MenuRegistry::canAccess($user, 'lager')) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Keine Berechtigung.'], JSON_UNESCAPED_UNICODE);

            return;
        }

        $action = trim((string) ($_GET['action'] ?? $_POST['action'] ?? 'scan'));
        if ($action === 'voucher-lines') {
            self::handleVoucherLines();

            return;
        }
        if ($action === 'audit') {
            self::handleAudit();

            return;
        }

        self::handleScan();
    }

    private static function handleScan(): void
    {
        $code = (string) ($_GET['code'] ?? $_POST['code'] ?? '');
        if (trim($code) === '') {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Strichcode fehlt.'], JSON_UNESCAPED_UNICODE);

            return;
        }

        try {
            $resolved = StockBarcodeService::resolve($code);
            echo json_encode([
                'success' => true,
                'data' => self::publicScan($resolved),
            ], JSON_UNESCAPED_UNICODE);
        } catch (InvalidArgumentException $e) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
        } catch (Throwable $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
        }
    }

    private static function handleAudit(): void
    {
        $code = trim((string) ($_GET['code'] ?? $_POST['code'] ?? ''));
        $level = trim((string) ($_GET['level'] ?? $_POST['level'] ?? ''));
        $entityId = (int) ($_GET['entity_id'] ?? $_POST['entity_id'] ?? 0);

        if ($code === '' && ($level === '' || $entityId < 1)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Strichcode oder manuelle Auswahl fehlt.'], JSON_UNESCAPED_UNICODE);

            return;
        }

        try {
            $audit = $code !== ''
                ? StockPlaceAuditService::audit($code)
                : StockPlaceAuditService::auditById($level, $entityId);
            echo json_encode([
                'success' => true,
                'data' => $audit,
            ], JSON_UNESCAPED_UNICODE);
        } catch (InvalidArgumentException $e) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
        } catch (Throwable $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
        }
    }

    private static function handleVoucherLines(): void
    {
        $voucherId = (int) ($_GET['voucher_id'] ?? $_POST['voucher_id'] ?? 0);
        if ($voucherId < 1) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Beleg-ID fehlt.'], JSON_UNESCAPED_UNICODE);

            return;
        }

        try {
            $lines = StockReceiptIssueService::linesFromVoucher($voucherId);
            echo json_encode([
                'success' => true,
                'data' => ['lines' => array_map(self::publicVoucherLine(...), $lines)],
            ], JSON_UNESCAPED_UNICODE);
        } catch (Throwable $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
        }
    }

    /** @param array<string, mixed> $resolved */
    private static function publicScan(array $resolved): array
    {
        $out = [
            'type' => (string) ($resolved['type'] ?? ''),
            'label' => (string) ($resolved['label'] ?? ''),
        ];

        if (!empty($resolved['article']) && is_array($resolved['article'])) {
            $article = $resolved['article'];
            $out['article'] = [
                'id' => (int) ($article['id'] ?? 0),
                'article_number' => (string) ($article['article_number'] ?? ''),
                'title' => (string) ($article['title'] ?? ''),
                'unit' => (string) ($article['unit'] ?? ''),
                'gtin' => (string) ($article['gtin'] ?? ''),
                'stock_qty' => round((float) ($article['stock_qty'] ?? 0), 3),
            ];
        }

        if (!empty($resolved['place']) && is_array($resolved['place'])) {
            $place = $resolved['place'];
            $out['place'] = [
                'id' => (int) ($place['id'] ?? 0),
                'code' => (string) ($place['code'] ?? ''),
                'position_code' => (string) ($place['position_code'] ?? ''),
                'place_kind' => (string) ($place['place_kind'] ?? ''),
                'kind_label' => (string) ($place['kind_label'] ?? ''),
                'barcode' => (string) ($place['barcode'] ?? ''),
            ];
        }

        if (!empty($resolved['package']) && is_array($resolved['package'])) {
            $package = $resolved['package'];
            $out['package'] = [
                'id' => (int) ($package['id'] ?? 0),
                'barcode' => (string) ($package['barcode'] ?? ''),
                'quantity' => round((float) ($package['quantity'] ?? 0), 3),
                'status' => (string) ($package['status'] ?? ''),
                'place_id' => (int) ($package['place_id'] ?? 0),
            ];
        }

        return $out;
    }

    /** @param array<string, mixed> $line */
    private static function publicVoucherLine(array $line): array
    {
        return [
            'article_id' => (int) ($line['article_id'] ?? 0),
            'article_number' => (string) ($line['article_number'] ?? ''),
            'title' => (string) ($line['title'] ?? ''),
            'gtin' => (string) ($line['gtin'] ?? ''),
            'quantity' => round((float) ($line['quantity'] ?? 0), 3),
            'unit' => (string) ($line['unit'] ?? ''),
        ];
    }
}
