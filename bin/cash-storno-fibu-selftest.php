<?php
declare(strict_types=1);

/**
 * Selftest K15e: Storno-FiBu-Pfad (Methoden + Journal-Seiten-Tausch offline).
 * php bin/cash-storno-fibu-selftest.php
 */
require dirname(__DIR__) . '/bootstrap.php';

$fail = 0;
$expect = static function (bool $cond, string $msg) use (&$fail): void {
    if ($cond) {
        echo "OK  {$msg}\n";
        return;
    }
    echo "FAIL {$msg}\n";
    $fail++;
};

$expect(method_exists('CashRegisterFiBuPostingService', 'postStornoForFinalizedReceipt'), 'postStornoForFinalizedReceipt');
$expect(method_exists('CashJournalRepository', 'syncForCashStornoReceipt'), 'syncForCashStornoReceipt');

$receipt = [
    'id' => 1,
    'status' => 'finalized',
    'receipt_type' => 'storno',
    'storno_of_receipt_id' => 9,
    'total_gross' => -11.9,
    'receipt_number' => 2,
    'receipt_number_display' => 'S-2',
    'finished_at' => '2026-11-07 12:00:00',
];
$lines = [[
    'gross_amount' => -11.9,
    'quantity' => -1,
    'tax_rate' => 19,
    'title' => 'Test',
]];
$payments = [[
    'payment_method' => 'cash',
    'amount' => -11.9,
]];
$absReceipt = $receipt;
$absReceipt['total_gross'] = 11.9;
$absLines = [['gross_amount' => 11.9, 'quantity' => 1, 'tax_rate' => 19, 'title' => 'Test']];
$absPayments = [['payment_method' => 'cash', 'amount' => 11.9]];
$sale = CashRegisterFiBuPostingService::buildJournalLines(
    $absReceipt,
    $absLines,
    $absPayments,
    'SKR03',
    'cr:1'
);
$expect($sale !== [], 'Verkaufszeilen gebaut');
$flipped = [];
foreach ($sale as $jl) {
    $side = strtolower((string) ($jl['side'] ?? ''));
    $flipped[] = $side === 'debit' ? 'credit' : 'debit';
}
$expect(in_array('debit', $flipped, true) && in_array('credit', $flipped, true), 'Storno-Seiten gemischt');

if ($fail > 0) {
    fwrite(STDERR, "cash-storno-fibu-selftest: {$fail} Fehler\n");
    exit(1);
}
echo "cash-storno-fibu-selftest OK\n";
exit(0);
