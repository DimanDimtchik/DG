<?php
declare(strict_types=1);

/**
 * K8a — Selftest Zahlungsart Bar (cash-only → Finalize → FiBu-Kassenbuch-Sync).
 * php bin/cash-payment-cash-selftest.php
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

$expect_true(CashPaymentRepository::methodLabels()['cash'] === 'Bar', 'Label Bar');
$expect_true(in_array('cash', CashRegisterFiBuPostingService::bookableMethods(), true), 'Bar buchbar');
$expect_true(CashRegisterFiBuPostingService::METHODS_CASH === ['cash'], 'METHODS_CASH');

$view = (string) file_get_contents(DG_ROOT . '/views/modules/registerkasse-bon.php');
$expect_true(str_contains($view, 'payment_method" value="cash"') || str_contains($view, "payment_method\" value=\"cash\""), 'UI Rest bar');
$expect_true(str_contains($view, 'Rest bar'), 'UI-Text Rest bar');
$expect_true(str_contains($view, "code === 'cash' ? ' selected'"), 'Bar vorausgewählt');

if (!Database::isConfigured()) {
    echo "SKIP DB — Label/UI geprüft.\n";
    if ($fail > 0) {
        fwrite(STDERR, "cash-payment-cash-selftest FAILED ({$fail})\n");
        exit(1);
    }
    echo "cash-payment-cash-selftest OK (ohne DB)\n";
    exit(0);
}

MigrationRunner::runPending();
foreach (
    [
        'CashLocationRepository',
        'CashRegisterRepository',
        'CashSessionRepository',
        'CashReceiptRepository',
        'CashPaymentRepository',
    ] as $cls
) {
    if (!class_exists($cls) || !$cls::tableExists()) {
        echo "FAIL fehlt: {$cls}\n";
        exit(1);
    }
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
        if (CashJournalRepository::tableExists()) {
            try {
                $pdo->prepare('DELETE FROM dg_cash_journal WHERE cash_receipt_id = :id')->execute(['id' => $receiptId]);
            } catch (Throwable) {
                // Spalte ggf. anders
            }
        }
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
        'code' => 'ST-K8A-' . $sfx,
        'name' => 'Selftest Filiale K8a',
        'is_active' => 1,
    ]);
    $regId = CashRegisterRepository::insert([
        'location_id' => $locId,
        'code' => 'KR-K8A-' . $sfx,
        'name' => 'Selftest Kasse K8a',
        'next_receipt_number' => 1,
        'receipt_number_prefix' => 'B-',
        'is_active' => 1,
    ]);

    CashSessionService::open($regId, 8, '50,00');
    $receiptId = CashReceiptService::startDraft($regId, 8);
    CashReceiptService::addLine($receiptId, [
        'title' => 'Barverkauf',
        'quantity' => 1,
        'unit_price_gross' => '12,50',
        'tax_rate' => 19,
    ]);

    $payId = CashReceiptService::addPayment($receiptId, [
        'payment_method' => 'cash',
        'amount' => '12,50',
    ]);
    $expect_true($payId > 0, 'Bar-Zahlung angelegt');
    $expect_true(CashReceiptService::paymentTotal($receiptId) === 12.5, 'Summe Bar = Brutto');

    $pays = CashPaymentRepository::listByReceipt($receiptId);
    $expect_true(count($pays) === 1, 'eine Zahlungszeile');
    $expect_true(($pays[0]['payment_method'] ?? '') === 'cash', 'method=cash');

    CashReceiptService::finalizeWithoutTse($receiptId);
    $receipt = CashReceiptRepository::findById($receiptId);
    $expect_true(($receipt['status'] ?? '') === 'finalized', 'finalized');

    $summary = json_decode((string) ($receipt['payment_summary_json'] ?? '[]'), true);
    $expect_true(is_array($summary) && count($summary) === 1, 'Summary 1 Eintrag');
    $expect_true(($summary[0]['method'] ?? '') === 'cash', 'Summary Bar');
    $expect_true(round((float) ($summary[0]['amount'] ?? 0), 2) === 12.5, 'Summary 12,50');
} catch (Throwable $e) {
    echo 'FAIL Ausnahme: ' . $e->getMessage() . "\n";
    $fail++;
} finally {
    $cleanup();
    echo "OK  Selftest-Daten entfernt\n";
}

if ($fail > 0) {
    fwrite(STDERR, "cash-payment-cash-selftest FAILED ({$fail})\n");
    exit(1);
}
echo "cash-payment-cash-selftest OK (K8a)\n";
