<?php
declare(strict_types=1);

/**
 * K20b — Selftest gemischte Zahlung (Bar+EC+Karte+Gutschein, kein Typ „mixed“).
 * php bin/cash-mixed-payment-selftest.php
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

$expect_true(method_exists('CashReceiptService', 'addPayment'), 'addPayment');
$expect_true(method_exists('CashReceiptService', 'finalizeWithoutTse'), 'finalizeWithoutTse');
$expect_true(method_exists('CashReceiptService', 'paymentTotal'), 'paymentTotal');

$labels = CashPaymentRepository::methodLabels();
foreach (['cash', 'ec', 'card', 'voucher'] as $m) {
    $expect_true(isset($labels[$m]), 'Label ' . $m);
}
$expect_true(!isset($labels['mixed']), 'kein Label mixed');

if (!Database::isConfigured()) {
    echo "SKIP DB — Guard-Tests ohne Instanz-DB.\n";
    if ($fail > 0) {
        fwrite(STDERR, "cash-mixed-payment-selftest FAILED ({$fail})\n");
        exit(1);
    }
    echo "cash-mixed-payment-selftest OK (ohne DB)\n";
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
        'code' => 'ST-K20B-' . $sfx,
        'name' => 'Selftest Filiale K20b',
        'is_active' => 1,
    ]);
    $regId = CashRegisterRepository::insert([
        'location_id' => $locId,
        'code' => 'KR-K20B-' . $sfx,
        'name' => 'Selftest Kasse K20b',
        'next_receipt_number' => 1,
        'receipt_number_prefix' => 'M-',
        'is_active' => 1,
    ]);

    CashSessionService::open($regId, 11, '0');
    $receiptId = CashReceiptService::startDraft($regId, 11);
    CashReceiptService::addLine($receiptId, [
        'title' => 'Warenkorb gemischt',
        'quantity' => 1,
        'unit_price_gross' => '40,00',
        'tax_rate' => 19,
    ]);

    $receipt = CashReceiptRepository::findById($receiptId);
    $expect_true(round((float) ($receipt['total_gross'] ?? 0), 2) === 40.0, 'Brutto 40');

    $mixedRejected = false;
    try {
        CashReceiptService::addPayment($receiptId, [
            'payment_method' => 'mixed',
            'amount' => 40,
        ]);
    } catch (RuntimeException $e) {
        $mixedRejected = str_contains($e->getMessage(), 'Ungültige Zahlungsart');
    }
    $expect_true($mixedRejected, 'payment_method=mixed abgewiesen');

    CashReceiptService::addPayment($receiptId, ['payment_method' => 'cash', 'amount' => '10,00']);
    CashReceiptService::addPayment($receiptId, ['payment_method' => 'ec', 'amount' => '12,00']);
    CashReceiptService::addPayment($receiptId, ['payment_method' => 'card', 'amount' => '8,00']);

    $expect_true(CashReceiptService::paymentTotal($receiptId) === 30.0, 'Teilzahlung 30');
    $receipt = CashReceiptRepository::findById($receiptId);
    $expect_true(($receipt['status'] ?? '') === 'paying', 'Status paying');

    $underpayBlocked = false;
    try {
        CashReceiptService::finalizeWithoutTse($receiptId);
    } catch (RuntimeException $e) {
        $underpayBlocked = str_contains($e->getMessage(), 'vollständig bezahlt');
    }
    $expect_true($underpayBlocked, 'Rest offen blockiert Finish');

    CashReceiptService::addPayment($receiptId, ['payment_method' => 'voucher', 'amount' => '10,00']);
    $expect_true(CashReceiptService::paymentTotal($receiptId) === 40.0, 'Summe = Brutto 40');

    $payments = CashPaymentRepository::listByReceipt($receiptId);
    $expect_true(count($payments) === 4, 'genau 4 Zahlungszeilen');
    $methods = array_map(
        static fn (array $p): string => (string) ($p['payment_method'] ?? ''),
        $payments
    );
    sort($methods);
    $expect_true($methods === ['card', 'cash', 'ec', 'voucher'], 'alle vier Arten, kein mixed');

    CashReceiptService::finalizeWithoutTse($receiptId);
    $receipt = CashReceiptRepository::findById($receiptId);
    $expect_true(($receipt['status'] ?? '') === 'finalized', 'finalized nach Vollzahlung');

    $summaryRaw = $receipt['payment_summary_json'] ?? null;
    $summary = is_string($summaryRaw) ? json_decode($summaryRaw, true) : $summaryRaw;
    $expect_true(is_array($summary) && count($summary) === 4, 'payment_summary_json 4 Einträge');
    $sumByMethod = [];
    foreach ($summary as $row) {
        if (!is_array($row)) {
            continue;
        }
        $sumByMethod[(string) ($row['method'] ?? '')] = round((float) ($row['amount'] ?? 0), 2);
    }
    $expect_true(($sumByMethod['cash'] ?? 0) === 10.0, 'Summary cash 10');
    $expect_true(($sumByMethod['ec'] ?? 0) === 12.0, 'Summary ec 12');
    $expect_true(($sumByMethod['card'] ?? 0) === 8.0, 'Summary card 8');
    $expect_true(($sumByMethod['voucher'] ?? 0) === 10.0, 'Summary voucher 10');

    $afterFinalPay = false;
    try {
        CashReceiptService::addPayment($receiptId, ['payment_method' => 'cash', 'amount' => 1]);
    } catch (RuntimeException $e) {
        $afterFinalPay = true;
    }
    $expect_true($afterFinalPay, 'nach Finalize keine weitere Zahlung');
} catch (Throwable $e) {
    echo 'FAIL Ausnahme: ' . $e->getMessage() . "\n";
    $fail++;
} finally {
    $cleanup();
    echo "OK  Selftest-Daten entfernt\n";
}

if ($fail > 0) {
    fwrite(STDERR, "cash-mixed-payment-selftest FAILED ({$fail})\n");
    exit(1);
}
echo "cash-mixed-payment-selftest OK (K20b)\n";
