<?php
declare(strict_types=1);

/** Anhänge für Beleg-Mail: PDF (+ XRechnung-XML bei Rechnungen). */
final class VoucherMailAttachmentBuilder
{
    /**
     * @param array<string, mixed> $voucher
     * @return list<array{filename: string, content: string, mime?: string}>
     */
    public static function forVoucher(array $voucher): array
    {
        $out = [];
        $pdf = VoucherDocumentPdfService::renderPdf($voucher);
        $out[] = [
            'filename' => VoucherDocumentPdfService::filename($voucher),
            'content' => $pdf,
            'mime' => 'application/pdf',
        ];
        if (VoucherEInvoiceXmlService::supports($voucher)) {
            try {
                $xml = VoucherEInvoiceXmlService::renderXml($voucher);
                $out[] = [
                    'filename' => VoucherEInvoiceXmlService::filename($voucher),
                    'content' => $xml,
                    'mime' => 'application/xml',
                ];
            } catch (Throwable) {
                // PDF bleibt; XML optional bei fehlenden Stammdaten
            }
        }

        return $out;
    }
}
