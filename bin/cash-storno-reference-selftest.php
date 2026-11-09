<?php
declare(strict_types=1);

/**
 * K20c — Selftest Storno-Referenz (storno_of_receipt_id, kein Edit am Original).
 * Ergänzt K13c um Referenz-Randfälle (Draft, Storno-von-Storno, eigene Bonnummer, CSV).
 * php bin/cash-storno-reference-selftest.php
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

$expect_true(method_exists('CashReceiptService', 'stornoFromFinalized'), 'stornoFromFinalized');
$expect_true(method_exists('CashReceiptRepository', 'findStornoOf'), 'findStornoOf');

if (!Database::isConfigured()) {
    echo "SKIP DB — K20c braucht Instanz-DB.\n";
    if ($fail > 0) {
        fwrite(STDERR, "cash-storno-reference-selftest FAILED ({$fail})\n");
        exit(1);
    }
    echo "cash-storno-reference-selftest OK (ohne DB)\n";
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
        echo "FAIL Tabelle/Klasse fehlt: {$cls}\n";
        exit(1);
    }
}

$sfx = substr(bin2hex(random_bytes(3)), 0, 6);
$pdo = Database::pdo();
$locId = 0;
$regId = 0;
$ids = [];
$cleanup = static function () use ($pdo, &$ids, &$regId, &$locId): void {
    foreach ($ids as $id) {
        if ($id <= 0) {
            continue;
        }
        $pdo->prepare('DELETE FROM dg_cash_payments WHERE receipt_id = :id')->execute(['id' => $id]);
        $pdo->prepare('DELETE FROM dg_cash_receipt_lines WHERE receipt_id = :id')->execute(['id' => $id]);
        $pdo->prepare('DELETE FROM dg_cash_receipts WHERE id = :id')->execute(['id' => $id]);
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
        'code' => 'ST-K20C-' . $sfx,
        'name' => 'Selftest Filiale K20c',
        'is_active' => 1,
    ]);
    $regId = CashRegisterRepository::insert([
        'location_id' => $locId,
        'code' => 'KR-K20C-' . $sfx,
        'name' => 'Selftest Kasse K20c',
        'next_receipt_number' => 1,
        'receipt_number_prefix' => 'R-',
        'is_active' => 1,
    ]);
    $op = 33;
    CashSessionService::open($regId, $op, '0');

    // Draft darf nicht storniert werden
    $draftId = CashReceiptService::startDraft($regId, $op);
    $ids[] = $draftId;
    CashReceiptService::addLine($draftId, [
        'title' => 'Entwurf',
        'quantity' => 1,
        'unit_price_gross' => '3,00',
        'tax_rate' => 19,
    ]);
    $draftBlocked = false;
    try {
        CashReceiptService::stornoFromFinalized($draftId, $op);
    } catch (RuntimeException $e) {
        $draftBlocked = str_contains($e->getMessage(), 'festgeschriebene');
    }
    $expect_true($draftBlocked, 'Draft-Storno abgewiesen');

    // Verkauf finalisieren
    $saleId = CashReceiptService::startDraft($regId, $op);
    $ids[] = $saleId;
    CashReceiptService::addLine($saleId, [
        'title' => 'Artikel',
        'quantity' => 1,
        'unit_price_gross' => '15,00',
        'tax_rate' => 19,
    ]);
    CashReceiptService::addPayment($saleId, [
        'payment_method' => 'cash',
        'amount' => '10,00',
    ]);
    CashReceiptService::addPayment($saleId, [
        'payment_method' => 'ec',
        'amount' => '5,00',
    ]);
    CashReceiptService::finalizeWithoutTse($saleId);

    $sale = CashReceiptRepository::findById($saleId);
    $expect_true((int) ($sale['storno_of_receipt_id'] ?? 0) === 0, 'Verkauf ohne storno_of');
    $expect_true(($sale['receipt_type'] ?? '') === 'sale', 'Typ sale');
    $saleNumber = (int) ($sale['receipt_number'] ?? 0);

    $stornoId = CashReceiptService::stornoFromFinalized($saleId, $op, 'K20c-Ref');
    $ids[] = $stornoId;
    $storno = CashReceiptRepository::findById($stornoId);

    $expect_true((int) ($storno['storno_of_receipt_id'] ?? 0) === $saleId, 'storno_of_receipt_id = Original');
    $expect_true(($storno['receipt_type'] ?? '') === 'storno', 'Typ storno');
    $expect_true((int) ($storno['receipt_number'] ?? 0) !== $saleNumber, 'Storno eigene Bonnummer');
    $expect_true((int) ($storno['receipt_number'] ?? 0) > $saleNumber, 'Storno-Nummer höher als Verkauf');
    $expect_true((int) ($storno['register_id'] ?? 0) === $regId, 'gleiche Kasse');

    $viaFind = CashReceiptRepository::findStornoOf($saleId);
    $expect_true($viaFind !== null && (int) ($viaFind['id'] ?? 0) === $stornoId, 'findStornoOf ↔ Referenz');

    // Original unverändert (Referenz nur am Storno)
    $saleAfter = CashReceiptRepository::findById($saleId);
    $expect_true((int) ($saleAfter['storno_of_receipt_id'] ?? 0) === 0, 'Original bleibt ohne storno_of');
    $expect_true(($saleAfter['status'] ?? '') === 'finalized', 'Original weiter finalized');
    $expect_true(round((float) ($saleAfter['total_gross'] ?? 0), 2) === 15.0, 'Original-Brutto unverändert');

    // Storno eines Stornos verboten
    $stornoOfStorno = false;
    try {
        CashReceiptService::stornoFromFinalized($stornoId, $op);
    } catch (RuntimeException $e) {
        $stornoOfStorno = str_contains($e->getMessage(), 'Verkaufsbons');
    }
    $expect_true($stornoOfStorno, 'Storno-von-Storno abgewiesen');

    // Journal-Range trägt Referenz
    $date = date('Y-m-d');
    $rows = CashReceiptRepository::listJournalRange($regId, $date, $date, 50);
    $foundSale = false;
    $foundStorno = false;
    foreach ($rows as $row) {
        if ((int) ($row['id'] ?? 0) === $saleId) {
            $foundSale = true;
            $expect_true((int) ($row['storno_of_receipt_id'] ?? 0) === 0, 'Journal Verkauf storno_of leer');
        }
        if ((int) ($row['id'] ?? 0) === $stornoId) {
            $foundStorno = true;
            $expect_true((int) ($row['storno_of_receipt_id'] ?? 0) === $saleId, 'Journal Storno-Referenz');
            $expect_true(($row['receipt_type'] ?? '') === 'storno', 'Journal Typ storno');
        }
    }
    $expect_true($foundSale && $foundStorno, 'beide Bons im Journal');

    // CSV-Spalte „Storno-von“
    $stornoRows = array_values(array_filter(
        $rows,
        static fn (array $r): bool => (int) ($r['id'] ?? 0) === $stornoId
    ));
    $csv = CashReceiptJournalCsvExport::buildCsv($stornoRows);
    $expect_true(str_contains($csv, 'Storno-von'), 'CSV-Kopf Storno-von');
    $expect_true(preg_match('/;' . preg_quote((string) $saleId, '/') . ';/', $csv) === 1
        || preg_match('/;' . preg_quote((string) $saleId, '/') . '\r?$/m', $csv) === 1, 'CSV Storno-von = Original-ID');
} catch (Throwable $e) {
    echo 'FAIL Ausnahme: ' . $e->getMessage() . "\n";
    $fail++;
} finally {
    $cleanup();
    echo "OK  Selftest-Daten entfernt\n";
}

if ($fail > 0) {
    fwrite(STDERR, "cash-storno-reference-selftest FAILED ({$fail})\n");
    exit(1);
}
echo "cash-storno-reference-selftest OK (K20c)\n";
