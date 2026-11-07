<?php
declare(strict_types=1);

/**
 * K7f — Selftest: Draft → Position → Zahlung → Mock-TSE → fest.
 * php bin/cash-receipt-flow-selftest.php
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

$expect_true(method_exists('CashReceiptService', 'startDraft'), 'startDraft');
$expect_true(method_exists('CashReceiptService', 'addLine'), 'addLine');
$expect_true(method_exists('CashReceiptService', 'addPayment'), 'addPayment');
$expect_true(method_exists('CashReceiptService', 'finalizeWithTse'), 'finalizeWithTse');
$expect_true(class_exists('MockTseProvider'), 'MockTseProvider');

$view = (string) file_get_contents(DG_ROOT . '/views/modules/registerkasse-bon.php');
$expect_true(str_contains($view, 'cash_receipt_finalize'), 'UI Festschreiben');

$src = (string) file_get_contents(DG_ROOT . '/src/CashRegister/CashReceiptService.php');
$expect_true(str_contains($src, 'finishTransaction'), 'Finish ruft Provider');

if (!Database::isConfigured()) {
    // Offline: nur Mock-Provider-Kette + Print
    $mock = new MockTseProvider();
    $txId = TseSignService::uuidV4();
    $start = $mock->startTransaction($txId, ['amount_gross' => 11.9]);
    $upd = $mock->updateTransaction($txId, (int) $start['revision'], ['amount_gross' => 11.9]);
    $fin = $mock->finishTransaction($txId, (int) $upd['revision'], ['amount_gross' => 11.9]);
    $expect_true(($fin['state'] ?? '') === 'FINISHED', 'Mock FINISHED (ohne DB)');
    $expect_true(trim((string) ($fin['qr_code_data'] ?? '')) !== '', 'Mock QR (ohne DB)');
    $html = CashReceiptPrintService::renderHtml(
        [
            'receipt_number_display' => 'OFFLINE-1',
            'status' => 'finalized',
            'receipt_type' => 'sale',
            'started_at' => date('Y-m-d H:i:s'),
            'finished_at' => date('Y-m-d H:i:s'),
            'total_gross' => 11.9,
            'total_net' => 10.0,
            'vat_summary_json' => null,
            'qr_code_data' => $fin['qr_code_data'],
            'tse_serial' => $fin['tss_serial_number'] ?? '',
            'signature_counter' => $fin['signature_counter'] ?? '',
            'signature_algorithm' => $fin['signature_algorithm'] ?? '',
            'transaction_number' => $fin['tx_number'] ?? null,
            'time_start' => $fin['time_start'] ?? null,
            'time_end' => $fin['time_end'] ?? null,
        ],
        [['line_no' => 1, 'title' => 'Test', 'quantity' => 1, 'unit' => 'Stück',
            'unit_price_gross' => 11.9, 'gross_amount' => 11.9, 'tax_rate' => 19, 'article_number' => '']],
        [['payment_method' => 'cash', 'amount' => 11.9, 'external_ref' => '']],
        ['code' => 'K1', 'name' => 'Mock']
    );
    $expect_true(!str_contains($html, 'Platz für TSE-QR-Code'), 'Druck mit QR (ohne DB)');
    echo "SKIP DB — Mock-Kette + Druck geprüft.\n";
    if ($fail > 0) {
        fwrite(STDERR, "cash-receipt-flow-selftest FAILED ({$fail})\n");
        exit(1);
    }
    echo "cash-receipt-flow-selftest OK (ohne DB · K7f)\n";
    exit(0);
}

MigrationRunner::runPending();
if (
    !CashLocationRepository::tableExists()
    || !CashRegisterRepository::tableExists()
    || !CashSessionRepository::tableExists()
    || !CashReceiptRepository::tableExists()
    || !CashPaymentRepository::tableExists()
) {
    echo "FAIL Registerkasse-Tabellen fehlen\n";
    exit(1);
}

$sfx = substr(bin2hex(random_bytes(3)), 0, 6);
$pdo = Database::pdo();
$locId = 0;
$regId = 0;
$receiptId = 0;
$cleanup = static function () use ($pdo, &$receiptId, &$regId, &$locId): void {
    if ($receiptId > 0) {
        $pdo->prepare('DELETE FROM dg_cash_payments WHERE receipt_id = :id')->execute(['id' => $receiptId]);
        $pdo->prepare('DELETE FROM dg_cash_receipt_lines WHERE receipt_id = :id')->execute(['id' => $receiptId]);
        $pdo->prepare('DELETE FROM dg_cash_receipts WHERE id = :id')->execute(['id' => $receiptId]);
    }
    if ($regId > 0) {
        $pdo->prepare('DELETE FROM dg_cash_sessions WHERE register_id = :rid')->execute(['rid' => $regId]);
        $pdo->prepare('DELETE FROM dg_cash_registers WHERE id = :id')->execute(['id' => $regId]);
    }
    if ($locId > 0) {
        $pdo->prepare('DELETE FROM dg_cash_locations WHERE id = :id')->execute(['id' => $locId]);
    }
};

try {
    $locId = CashLocationRepository::insert([
        'code' => 'ST-K7F-' . $sfx,
        'name' => 'Selftest Filiale K7f',
        'is_active' => 1,
    ]);
    $regId = CashRegisterRepository::insert([
        'location_id' => $locId,
        'code' => 'KR-K7F-' . $sfx,
        'name' => 'Selftest Kasse K7f',
        'next_receipt_number' => 1,
        'receipt_number_prefix' => 'F-',
        'is_active' => 1,
    ]);

    CashSessionService::open($regId, 17, '0');

    // 1) Draft
    $receiptId = CashReceiptService::startDraft($regId, 17);
    $r = CashReceiptRepository::findById($receiptId);
    $expect_true(($r['status'] ?? '') === 'draft', '1 Draft');

    // 2) Position
    CashReceiptService::addLine($receiptId, [
        'title' => 'Kaffee Mock',
        'quantity' => 1,
        'unit_price_gross' => '11,90',
        'tax_rate' => 19,
    ]);
    $r = CashReceiptRepository::findById($receiptId);
    $expect_true(round((float) ($r['total_gross'] ?? 0), 2) === 11.9, '2 Brutto 11,90');
    $expect_true(count(CashReceiptRepository::listLines($receiptId)) === 1, '2 eine Position');

    // 3) Zahlung
    CashReceiptService::addPayment($receiptId, [
        'payment_method' => 'cash',
        'amount' => '11,90',
    ]);
    $r = CashReceiptRepository::findById($receiptId);
    $expect_true(($r['status'] ?? '') === 'paying', '3 Status paying');
    $expect_true(CashReceiptService::isFullyPaid($receiptId), '3 voll bezahlt');

    // 4) Mock-TSE → fest
    $fin = CashReceiptService::finalizeWithTse($receiptId, new MockTseProvider());
    $expect_true(($fin['state'] ?? '') === 'FINISHED', '4 Mock FINISHED');
    $expect_true(trim((string) ($fin['qr_code_data'] ?? '')) !== '', '4 QR im Ergebnis');

    $r = CashReceiptRepository::findById($receiptId);
    $expect_true(($r['status'] ?? '') === 'finalized', '4 Status finalized');
    $expect_true(trim((string) ($r['tse_tx_id'] ?? '')) !== '', '4 tse_tx_id');
    $expect_true(trim((string) ($r['qr_code_data'] ?? '')) !== '', '4 qr_code_data am Bon');
    $expect_true(str_starts_with((string) ($r['signature_value'] ?? ''), 'MOCK-'), '4 Mock-Signatur');
    $expect_true(($r['tse_serial'] ?? '') === MockTseProvider::TSS_SERIAL, '4 TSE-Serial');

    // 5) Druck zeigt QR, nicht Platzhalter
    $html = CashReceiptPrintService::renderHtml(
        $r,
        CashReceiptRepository::listLines($receiptId),
        CashPaymentRepository::listByReceipt($receiptId),
        CashRegisterRepository::findById($regId)
    );
    $expect_true(!str_contains($html, 'Platz für TSE-QR-Code'), '5 Druck ohne Platzhalter');
    $expect_true(str_contains($html, 'TSE-Seriennr.') || str_contains($html, 'Signaturzähler'), '5 TSE-Block');

    // 6) Immutable: keine weitere Zahlung
    $blocked = false;
    try {
        CashReceiptService::addPayment($receiptId, ['payment_method' => 'cash', 'amount' => 1]);
    } catch (RuntimeException $e) {
        $blocked = true;
    }
    $expect_true($blocked, '6 nach Finalize keine Zahlung');

    // Doppel-Finish blockiert
    $double = false;
    try {
        CashReceiptService::finalizeWithTse($receiptId, new MockTseProvider());
    } catch (RuntimeException $e) {
        $double = true;
    }
    $expect_true($double, '6 Doppel-Finish blockiert');
} catch (Throwable $e) {
    echo 'FAIL Ausnahme: ' . $e->getMessage() . "\n";
    $fail++;
} finally {
    $cleanup();
    echo "OK  Selftest-Daten entfernt\n";
}

if ($fail > 0) {
    fwrite(STDERR, "cash-receipt-flow-selftest FAILED ({$fail})\n");
    exit(1);
}
echo "cash-receipt-flow-selftest OK (K7f)\n";
