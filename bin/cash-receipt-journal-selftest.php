<?php
declare(strict_types=1);

/**
 * K16a/b — Bon-Journal + CSV-Zeitraumexport (ohne Pflicht-DB).
 * php bin/cash-receipt-journal-selftest.php
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

$expect_true(method_exists('CashReceiptRepository', 'listJournal'), 'listJournal');
$expect_true(method_exists('CashReceiptRepository', 'listJournalRange'), 'listJournalRange');
$expect_true(class_exists('CashReceiptJournalCsvExport'), 'CsvExport');
$expect_true(is_readable(dirname(__DIR__) . '/views/modules/registerkasse-journal.php'), 'Journal-View');

$sample = CashReceiptJournalCsvExport::buildCsv([
    [
        'receipt_number_display' => 'T-1',
        'receipt_type' => 'sale',
        'register_code' => 'K1',
        'register_name' => 'Laden',
        'location_code' => 'F1',
        'location_name' => 'Filiale',
        'business_date' => '2026-11-07',
        'finished_at' => '2026-11-07 12:00:00',
        'total_gross' => 11.9,
        'total_net' => 10.0,
        'payment_summary_json' => json_encode([['method' => 'cash', 'amount' => 11.9]]),
        'tse_outage' => 0,
        'tse_tx_id' => '',
        'storno_of_receipt_id' => null,
        'notes' => '',
    ],
]);
$expect_true(str_starts_with($sample, "\xEF\xBB\xBF"), 'UTF-8 BOM');
$expect_true(str_contains($sample, 'Bon;Typ;Kassen-ID'), 'CSV-Kopf');
$expect_true(str_contains($sample, 'T-1'), 'CSV-Zeile');

$bad = false;
try {
    CashReceiptJournalCsvExport::sanitizeRequest(['date_from' => 'x', 'date_to' => 'y']);
} catch (InvalidArgumentException) {
    $bad = true;
}
$expect_true($bad, 'ungültiger Zeitraum abgewiesen');

$ok = CashReceiptJournalCsvExport::sanitizeRequest([
    'date_from' => '2026-11-01',
    'date_to' => '2026-11-07',
    'register_id' => 3,
]);
$expect_true($ok['date_from'] === '2026-11-01' && $ok['register_id'] === 3, 'sanitize ok');

if (!Database::isConfigured()) {
    echo "SKIP DB — Guard-Tests ok.\n";
    if ($fail > 0) {
        fwrite(STDERR, "cash-receipt-journal-selftest FAILED ({$fail})\n");
        exit(1);
    }
    echo "cash-receipt-journal-selftest OK (ohne DB)\n";
    exit(0);
}

MigrationRunner::runPending();
if (!CashReceiptRepository::tableExists()) {
    echo "FAIL Registerkasse-Tabellen fehlen\n";
    exit(1);
}

$rows = CashReceiptRepository::listJournal(null, null, 5);
$expect_true(is_array($rows), 'listJournal liefert Liste');
foreach ($rows as $row) {
    $expect_true(($row['status'] ?? '') === 'finalized', 'nur finalized');
}

if ($fail > 0) {
    fwrite(STDERR, "cash-receipt-journal-selftest FAILED ({$fail})\n");
    exit(1);
}
echo "cash-receipt-journal-selftest OK\n";
