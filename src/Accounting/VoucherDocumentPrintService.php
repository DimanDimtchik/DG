<?php
declare(strict_types=1);

/** Druck/PDF für Ausgangsbelege inkl. Belegkette und Abschlagsabzug. */
final class VoucherDocumentPrintService
{
    /**
     * @param array<string, mixed> $voucher
     */
    public static function render(array $voucher): string
    {
        $voucherId = (int) ($voucher['id'] ?? 0);
        $kind = (string) ($voucher['document_kind'] ?? '');
        $kindLabel = VoucherDocumentKind::label($kind);
        if ($kindLabel === '') {
            $kindLabel = 'Beleg';
        }

        $title = $kindLabel;
        $number = trim((string) ($voucher['invoice_number'] ?? ''));
        if ($number !== '') {
            $title .= ' ' . $number;
        }

        $chain = $voucherId > 0 ? VoucherDocumentChain::chainView($voucherId) : ['documents' => []];
        $finalSummary = null;
        if ($kind === VoucherDocumentKind::FINAL_INVOICE && $voucherId > 0) {
            $parentId = (int) ($voucher['parent_voucher_id'] ?? 0);
            if ($parentId > 0) {
                $finalSummary = VoucherDocumentChain::finalInvoiceSummary($parentId, $voucherId);
            }
        }

        $items = is_array($voucher['items'] ?? null) ? $voucher['items'] : [];
        if ($items === []) {
            $items = VoucherRepository::itemsForVoucher($voucherId);
        }

        return AccountingPrintService::render('voucher-document', [
            'voucher' => $voucher,
            'kindLabel' => $kindLabel,
            'items' => $items,
            'chain' => $chain,
            'finalSummary' => $finalSummary,
            'books' => VoucherDocumentKind::isBookable($kind, (string) ($voucher['voucher_type'] ?? 'income')),
        ], $title);
    }
}
