<?php

declare(strict_types=1);

use Dompdf\Dompdf;
use Dompdf\Options;

/** K7e — Bon-HTML → PDF (Dompdf), Stub bis TSE-Finish. */
final class CashReceiptPdfService
{
    public static function isAvailable(): bool
    {
        return class_exists(Dompdf::class);
    }

    /**
     * @param array<string, mixed> $receipt
     * @param list<array<string, mixed>> $lines
     * @param list<array<string, mixed>> $payments
     * @param array<string, mixed>|null $register
     */
    public static function renderPdf(
        array $receipt,
        array $lines,
        array $payments,
        ?array $register = null
    ): string {
        if (!self::isAvailable()) {
            throw new RuntimeException('PDF-Bibliothek (Dompdf) nicht geladen.');
        }
        $html = CashReceiptPrintService::renderHtml($receipt, $lines, $payments, $register);
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
}
