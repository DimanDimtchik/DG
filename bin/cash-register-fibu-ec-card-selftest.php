<?php
declare(strict_types=1);

/**
 * K15b–d — Buchung Bar/EC/Karte/Gutschein aus Registerkassen-Bon (ohne Pflicht-DB).
 * php bin/cash-register-fibu-ec-card-selftest.php
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

$expect_true(class_exists('CashRegisterFiBuPostingService'), 'Klasse PostingService');
$expect_true(CashRegisterFiBuPostingService::SOURCE === 'cash_register', 'SOURCE');
$expect_true(
    CashRegisterFiBuPostingService::bookableMethods() === ['cash', 'ec', 'card', 'voucher'],
    'Bar/EC/Karte/Gutschein'
);
$expect_true(method_exists('LedgerAccounts', 'revenueAccount'), 'revenueAccount');
$expect_true(method_exists('CashJournalRepository', 'syncForCashReceipt'), 'syncForCashReceipt');
$expect_true(LedgerAccounts::revenueAccount('skr03', 19) === '8400', 'SKR03 19%');
$expect_true(LedgerAccounts::revenueAccount('skr03', 7) === '8300', 'SKR03 7%');
$expect_true(LedgerAccounts::revenueAccount('skr04', 19) === '4400', 'SKR04 19%');

$receipt = ['total_gross' => 119.0, 'receipt_number_display' => 'T-1'];
$lines = [
    ['gross_amount' => 119.0, 'tax_rate' => 19],
];
$paymentsCashOnly = [
    ['payment_method' => 'cash', 'amount' => 119.0],
];
$cashBuilt = CashRegisterFiBuPostingService::buildJournalLines($receipt, $lines, $paymentsCashOnly, 'skr03', 'cr:1');
$hasCash = false;
$cashDebit = 0.0;
$cashCredit = 0.0;
foreach ($cashBuilt as $row) {
    $amt = round((float) ($row['amount'] ?? 0), 2);
    if (($row['side'] ?? '') === 'debit') {
        $cashDebit = round($cashDebit + $amt, 2);
        if (($row['account_number'] ?? '') === '1000') {
            $hasCash = true;
        }
    } else {
        $cashCredit = round($cashCredit + $amt, 2);
    }
}
$expect_true($hasCash, 'Bar Soll Kasse 1000');
$expect_true(abs($cashDebit - $cashCredit) < 0.009, 'Bar Soll = Haben');
$expect_true(abs($cashDebit - 119.0) < 0.009, 'Bar Brutto 119');

$paymentsEc = [
    ['payment_method' => 'ec', 'amount' => 119.0],
];
$built = CashRegisterFiBuPostingService::buildJournalLines($receipt, $lines, $paymentsEc, 'skr03', 'cr:1');
$expect_true($built !== [], 'EC erzeugt Zeilen');

$debit = 0.0;
$credit = 0.0;
$hasBank = false;
$hasRevenue = false;
$hasTax = false;
foreach ($built as $row) {
    $amt = round((float) ($row['amount'] ?? 0), 2);
    if (($row['side'] ?? '') === 'debit') {
        $debit = round($debit + $amt, 2);
        if (($row['account_number'] ?? '') === '1200') {
            $hasBank = true;
        }
    } else {
        $credit = round($credit + $amt, 2);
        if (($row['account_number'] ?? '') === '8400') {
            $hasRevenue = true;
        }
        if (($row['account_number'] ?? '') === '1776') {
            $hasTax = true;
        }
    }
}
$expect_true($hasBank, 'Soll Bank 1200');
$expect_true($hasRevenue, 'Haben Erlöse 8400');
$expect_true($hasTax, 'Haben USt 1776');
$expect_true(abs($debit - $credit) < 0.009, 'Soll = Haben');
$expect_true(abs($debit - 119.0) < 0.009, 'Brutto 119');

$mixed = CashRegisterFiBuPostingService::buildJournalLines(
    $receipt,
    $lines,
    [
        ['payment_method' => 'cash', 'amount' => 19.0],
        ['payment_method' => 'card', 'amount' => 100.0],
    ],
    'skr03',
    'cr:2'
);
$mixedDebit = 0.0;
$mixedCash = false;
$mixedCard = false;
foreach ($mixed as $row) {
    if (($row['side'] ?? '') === 'debit') {
        $mixedDebit = round($mixedDebit + (float) ($row['amount'] ?? 0), 2);
        $acc = (string) ($row['account_number'] ?? '');
        if ($acc === '1000') {
            $mixedCash = true;
        }
        if ($acc === '1200') {
            $mixedCard = true;
        }
    }
}
$expect_true($mixedCash && $mixedCard, 'Gemischt: Bar + Karte');
$expect_true(abs($mixedDebit - 119.0) < 0.009, 'Gemischt: voller Brutto 119');

$voucherBuilt = CashRegisterFiBuPostingService::buildJournalLines(
    $receipt,
    $lines,
    [['payment_method' => 'voucher', 'amount' => 119.0]],
    'skr03',
    'cr:3'
);
$hasLiability = false;
$voucherDebit = 0.0;
$voucherCredit = 0.0;
foreach ($voucherBuilt as $row) {
    $amt = round((float) ($row['amount'] ?? 0), 2);
    if (($row['side'] ?? '') === 'debit') {
        $voucherDebit = round($voucherDebit + $amt, 2);
        if (($row['account_number'] ?? '') === '1796') {
            $hasLiability = true;
        }
    } else {
        $voucherCredit = round($voucherCredit + $amt, 2);
    }
}
$expect_true($hasLiability, 'Gutschein Soll Verbindlichkeit 1796');
$expect_true(abs($voucherDebit - $voucherCredit) < 0.009, 'Gutschein Soll = Haben');
$expect_true(abs($voucherDebit - 119.0) < 0.009, 'Gutschein Brutto 119');

$expect_true(CashRegisterFiBuPostingService::documentMarker(42) === 'cr:42', 'Marker');

if (!Database::isConfigured()) {
    echo "SKIP DB — Build-Tests ok.\n";
    if ($fail > 0) {
        fwrite(STDERR, "cash-register-fibu-ec-card-selftest FAILED ({$fail})\n");
        exit(1);
    }
    echo "cash-register-fibu-ec-card-selftest OK (ohne DB)\n";
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

$codeLoc = 'ST-K15C-' . substr(bin2hex(random_bytes(3)), 0, 6);
$codeReg = 'KR-K15C-' . substr(bin2hex(random_bytes(3)), 0, 6);
$locId = CashLocationRepository::insert([
    'code' => $codeLoc,
    'name' => 'Selftest Filiale K15c',
    'is_active' => 1,
]);
$regId = CashRegisterRepository::insert([
    'location_id' => $locId,
    'code' => $codeReg,
    'name' => 'Selftest Kasse K15c',
    'register_type' => 'store',
    'next_receipt_number' => 1,
    'receipt_number_prefix' => 'E-',
    'is_active' => 1,
]);
CashSessionService::open($regId, 1, '0,00');
$rid = CashReceiptService::startDraft($regId, 1);
CashReceiptService::addLine($rid, [
    'title' => 'Artikel EC',
    'quantity' => 1,
    'unit_price_gross' => 11.90,
    'tax_rate' => 19,
]);
CashReceiptService::addPayment($rid, [
    'payment_method' => 'ec',
    'amount' => 11.90,
]);
CashReceiptService::finalizeWithoutTse($rid);

$batchId = CashRegisterFiBuPostingService::postForFinalizedReceipt($rid);
$expect_true($batchId !== null && $batchId > 0, 'Batch nach Finalize');
$again = CashRegisterFiBuPostingService::postForFinalizedReceipt($rid);
$expect_true($again === $batchId, 'Idempotent gleicher Batch');

if ($fail > 0) {
    fwrite(STDERR, "cash-register-fibu-ec-card-selftest FAILED ({$fail})\n");
    exit(1);
}
echo "cash-register-fibu-ec-card-selftest OK\n";
