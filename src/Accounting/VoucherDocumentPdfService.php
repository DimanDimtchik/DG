<?php
declare(strict_types=1);

use Dompdf\Dompdf;
use Dompdf\Options;

/** Beleg-HTML → PDF (Dompdf). */
final class VoucherDocumentPdfService
{
    public static function isAvailable(): bool
    {
        return class_exists(Dompdf::class);
    }

    /**
     * @param array<string, mixed> $voucher
     * @throws RuntimeException
     */
    public static function renderPdf(array $voucher): string
    {
        if (!self::isAvailable()) {
            throw new RuntimeException('PDF-Bibliothek (Dompdf) nicht geladen.');
        }
        $html = VoucherDocumentPrintService::renderAttachmentHtml($voucher);
        $options = new Options();
        $options->set('isRemoteEnabled', false);
        $options->set('isHtml5ParserEnabled', true);
        $options->set('defaultFont', 'DejaVu Sans');
        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();
        $out = $dompdf->output();
        if (!is_string($out) || $out === '') {
            throw new RuntimeException('PDF-Erzeugung fehlgeschlagen.');
        }

        return $out;
    }

    /**
     * @param array<string, mixed> $voucher
     */
    public static function filename(array $voucher): string
    {
        $base = VoucherDocumentPrintService::attachmentFilename($voucher);
        if (str_ends_with(strtolower($base), '.html')) {
            return substr($base, 0, -5) . '.pdf';
        }
        if (str_ends_with(strtolower($base), '.pdf')) {
            return $base;
        }

        return $base . '.pdf';
    }
}
