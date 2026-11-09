<?php
declare(strict_types=1);

/**
 * K9c — aus Auftrag/Rechnung einen Registerkassen-Bon erzeugen (gleiche Pipeline).
 */
final class CashVoucherToReceiptService
{
    /**
     * Erzeugt Draft-Bon mit Positionen aus dem Beleg; Zahlung/Finish = K7/K8.
     *
     * @return int receipt id
     */
    public static function createDraftFromVoucher(int $voucherId, int $registerId, int $operatorId): int
    {
        if ($voucherId <= 0) {
            throw new RuntimeException('Beleg/Auftrag fehlt.');
        }
        if ($registerId <= 0) {
            throw new RuntimeException('Kasse fehlt.');
        }
        if (!class_exists('VoucherRepository')) {
            throw new RuntimeException('Belegmodul nicht verfügbar.');
        }

        $voucher = VoucherRepository::findById($voucherId);
        if ($voucher === null) {
            throw new RuntimeException('Beleg nicht gefunden.');
        }

        $type = VoucherRepository::normalizeVoucherType((string) ($voucher['voucher_type'] ?? ''));
        if ($type !== 'income') {
            throw new RuntimeException('Nur Ausgangsbelege können als Kassenbon erzeugt werden.');
        }

        // K9d: wenn schon Beleg-TSE, kein paralleler Bon-Pfad
        if (class_exists('TseTransactionRepository') && TseTransactionRepository::hasFinishedForVoucher($voucherId)) {
            throw new RuntimeException(
                'Beleg ist bereits TSE-signiert (Barzahlung). Kein zusätzlicher Kassenbon.'
            );
        }

        // Schon ein offener/finaler Bon zu diesem Beleg?
        if (CashReceiptRepository::tableExists()) {
            $stmt = Database::pdo()->prepare(
                "SELECT id, status, receipt_number_display FROM dg_cash_receipts
                 WHERE voucher_id = :vid AND status <> 'cancelled'
                 ORDER BY id DESC LIMIT 1"
            );
            $stmt->execute(['vid' => $voucherId]);
            $existing = $stmt->fetch(PDO::FETCH_ASSOC);
            if (is_array($existing)) {
                $st = (string) ($existing['status'] ?? '');
                $disp = trim((string) ($existing['receipt_number_display'] ?? ''));
                if ($disp === '') {
                    $disp = '#' . (int) ($existing['id'] ?? 0);
                }
                if ($st === 'finalized') {
                    throw new RuntimeException('Zu diesem Beleg gibt es bereits Bon ' . $disp . '.');
                }
                throw new RuntimeException(
                    'Zu diesem Beleg gibt es bereits einen offenen Bon ' . $disp . ' — bitte dort fortsetzen.'
                );
            }
        }

        $receiptId = CashReceiptService::startDraft($registerId, $operatorId);
        CashReceiptRepository::updateReceipt($receiptId, [
            'voucher_id' => $voucherId,
            'notes' => self::defaultNote($voucher),
        ]);

        $items = is_array($voucher['items'] ?? null) ? $voucher['items'] : [];
        if ($items === []) {
            $items = VoucherRepository::itemsForVoucher($voucherId);
        }
        if ($items === []) {
            // Fallback: eine Sammelposition aus Belegsumme
            $gross = round((float) ($voucher['gross_total'] ?? $voucher['amount_gross'] ?? 0), 2);
            if ($gross <= 0 && class_exists('VoucherDocumentPrintService')) {
                $breakdown = VoucherDocumentPrintService::totalsBreakdown([], $voucher);
                $gross = round((float) ($breakdown['gross'] ?? 0), 2);
            }
            if ($gross <= 0) {
                throw new RuntimeException('Beleg hat keine Positionen und keine Summe.');
            }
            CashReceiptService::addLine($receiptId, [
                'title' => self::fallbackTitle($voucher),
                'quantity' => 1,
                'unit_price_gross' => $gross,
                'tax_rate' => (int) ($voucher['tax_rate'] ?? 19),
                'unit' => 'Pauschal',
            ]);

            return $receiptId;
        }

        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }
            $qty = round((float) ($item['quantity'] ?? 1), 3);
            if ($qty == 0.0) {
                continue;
            }
            $unitGross = round((float) ($item['unit_price_gross'] ?? $item['price_gross'] ?? 0), 2);
            $lineGross = isset($item['gross_amount'])
                ? round((float) $item['gross_amount'], 2)
                : round($qty * $unitGross, 2);
            if ($lineGross == 0.0 && $unitGross == 0.0) {
                continue;
            }
            if ($unitGross == 0.0 && $qty != 0.0) {
                $unitGross = round($lineGross / $qty, 2);
            }
            CashReceiptService::addLine($receiptId, [
                'title' => trim((string) ($item['title'] ?? $item['description'] ?? 'Position')) ?: 'Position',
                'quantity' => $qty,
                'unit_price_gross' => $unitGross,
                'tax_rate' => (int) ($item['tax_rate'] ?? $voucher['tax_rate'] ?? 19),
                'unit' => trim((string) ($item['unit'] ?? 'Stück')) ?: 'Stück',
                'article_number' => trim((string) ($item['article_number'] ?? $item['sku'] ?? '')),
                'article_id' => (int) ($item['article_id'] ?? 0),
            ]);
        }

        if (CashReceiptRepository::listLines($receiptId) === []) {
            throw new RuntimeException('Aus dem Beleg konnten keine Bon-Positionen erzeugt werden.');
        }

        return $receiptId;
    }

    /**
     * @param array<string, mixed> $voucher
     */
    private static function defaultNote(array $voucher): string
    {
        $num = trim((string) ($voucher['invoice_number'] ?? $voucher['document_number'] ?? ''));
        $kind = trim((string) ($voucher['document_kind'] ?? ''));
        $parts = ['Aus Beleg'];
        if ($kind !== '') {
            $parts[] = $kind;
        }
        if ($num !== '') {
            $parts[] = $num;
        } else {
            $parts[] = '#' . (int) ($voucher['id'] ?? 0);
        }

        return mb_substr(implode(' ', $parts), 0, 500);
    }

    /**
     * @param array<string, mixed> $voucher
     */
    private static function fallbackTitle(array $voucher): string
    {
        $desc = trim((string) ($voucher['description'] ?? ''));
        if ($desc !== '') {
            return mb_substr($desc, 0, 255);
        }
        $num = trim((string) ($voucher['invoice_number'] ?? ''));

        return $num !== '' ? ('Beleg ' . $num) : ('Beleg #' . (int) ($voucher['id'] ?? 0));
    }
}
