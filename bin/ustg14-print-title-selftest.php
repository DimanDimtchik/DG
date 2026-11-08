<?php
declare(strict_types=1);

/**
 * PA1a: Kundengutschrift / Belege ohne document_kind bekommen den Belegart-Titel im Druck.
 */
require dirname(__DIR__) . '/bootstrap.php';

function ok(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "FEHLER: {$message}\n");
        exit(1);
    }
    echo "OK: {$message}\n";
}

$creditFile = VoucherDocumentPrintService::attachmentFilename([
    'document_kind' => '',
    'voucher_type' => 'credit',
    'invoice_number' => 'GS-1',
]);
ok(
    str_contains($creditFile, 'Kundengutschrift') && str_ends_with($creditFile, '.pdf'),
    'Gutschrift-Dateiname nutzt Belegart-Label'
);

$invoiceFile = VoucherDocumentPrintService::attachmentFilename([
    'document_kind' => VoucherDocumentKind::INVOICE,
    'voucher_type' => 'income',
    'invoice_number' => 'RE-1',
]);
ok(
    str_contains($invoiceFile, 'Rechnung') && str_ends_with($invoiceFile, '.pdf'),
    'Rechnung behält document_kind-Label'
);

echo "Fertig.\n";
