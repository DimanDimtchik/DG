<?php
declare(strict_types=1);

/**
 * K7e — Selftest Bon-Druck/PDF-Stub (Pflichtfelder + QR-Platz).
 * php bin/cash-receipt-print-selftest.php
 */
require dirname(__DIR__) . '/bootstrap.php';

$fail = 0;
$expect_true = static function (bool $cond, string $label) use (&$fail): void {
    if ($cond) {
        echo "OK  {$label}\n";
        return;
    }
    echo "FAIL {$label}\n";
    $fail++;
};

$expect_true(class_exists('CashReceiptPrintService'), 'CashReceiptPrintService');
$expect_true(class_exists('CashReceiptPdfService'), 'CashReceiptPdfService');
$expect_true(method_exists('CashReceiptPrintService', 'renderHtml'), 'renderHtml');
$expect_true(method_exists('CashReceiptPrintService', 'tsePrintBlock'), 'tsePrintBlock');

$receipt = [
    'id' => 42,
    'receipt_number' => 7,
    'receipt_number_display' => 'KR-7',
    'status' => 'finalized',
    'receipt_type' => 'sale',
    'started_at' => '2026-11-07 10:00:00',
    'finished_at' => '2026-11-07 10:05:00',
    'total_gross' => 11.90,
    'total_net' => 10.00,
    'vat_summary_json' => json_encode([
        ['rate' => 19, 'gross' => 11.90, 'net' => 10.00, 'tax' => 1.90],
    ], JSON_THROW_ON_ERROR),
    'qr_code_data' => null,
    'tse_serial' => '',
    'signature_counter' => '',
];
$lines = [[
    'line_no' => 1,
    'title' => 'Kaffee',
    'article_number' => 'A1',
    'quantity' => 1,
    'unit' => 'Stück',
    'unit_price_gross' => 11.90,
    'gross_amount' => 11.90,
    'tax_rate' => 19,
]];
$payments = [[
    'payment_method' => 'cash',
    'amount' => 11.90,
    'external_ref' => '',
]];
$register = ['code' => 'K1', 'name' => 'Theke'];

$html = CashReceiptPrintService::renderHtml($receipt, $lines, $payments, $register);
$expect_true(str_contains($html, 'Kassenbeleg'), 'Titel Kassenbeleg');
$expect_true(str_contains($html, 'KR-7'), 'Bonnummer');
$expect_true(str_contains($html, 'Kaffee'), 'Position');
$expect_true(str_contains($html, 'USt 19%'), 'USt-Zeile');
$expect_true(str_contains($html, 'Bar') || str_contains($html, 'cash'), 'Zahlungsart');
$expect_true(str_contains($html, 'Platz für TSE-QR-Code'), 'QR-Platzhalter ohne Signatur');
$expect_true(CashReceiptPrintService::tsePrintBlock($receipt) === null, 'tseBlock null ohne QR');

$receiptSigned = $receipt;
$receiptSigned['qr_code_data'] = 'V0;MOCK-TSS;tx-1;1;11.90';
$receiptSigned['tse_serial'] = 'MOCK-TSS';
$receiptSigned['signature_counter'] = '1';
$receiptSigned['transaction_number'] = 9;
$receiptSigned['signature_algorithm'] = 'ecdsa-plain-SHA256';
$receiptSigned['time_start'] = strtotime('2026-11-07 10:00:00');
$receiptSigned['time_end'] = strtotime('2026-11-07 10:05:00');
$block = CashReceiptPrintService::tsePrintBlock($receiptSigned);
$expect_true(is_array($block) && ($block['qr_svg'] ?? '') !== '', 'QR-SVG bei Payload');
$htmlSigned = CashReceiptPrintService::renderHtml($receiptSigned, $lines, $payments, $register);
$expect_true(str_contains($htmlSigned, 'TSE-Seriennr.'), 'TSE-Seriennr. im Druck');
$expect_true(!str_contains($htmlSigned, 'Platz für TSE-QR-Code'), 'kein Platzhalter bei QR');

$name = CashReceiptPrintService::filename($receipt, 'pdf');
$expect_true(str_contains($name, 'Kassenbeleg') && str_ends_with($name, '.pdf'), 'Dateiname PDF');

$view = (string) file_get_contents(DG_ROOT . '/views/modules/registerkasse-bon.php');
$expect_true(str_contains($view, 'download=html'), 'UI Beleg anzeigen');
$expect_true(str_contains($view, 'download=pdf'), 'UI PDF');

if (CashReceiptPdfService::isAvailable()) {
    try {
        $pdf = CashReceiptPdfService::renderPdf($receipt, $lines, $payments, $register);
        $expect_true(str_starts_with($pdf, '%PDF'), 'PDF-Header');
        $expect_true(strlen($pdf) > 500, 'PDF nicht leer');
    } catch (Throwable $e) {
        echo 'FAIL PDF: ' . $e->getMessage() . "\n";
        $fail++;
    }
} else {
    echo "SKIP Dompdf nicht geladen — HTML-Stub geprüft.\n";
}

if ($fail > 0) {
    fwrite(STDERR, "cash-receipt-print-selftest FAILED ({$fail})\n");
    exit(1);
}
echo "cash-receipt-print-selftest OK (K7e)\n";
