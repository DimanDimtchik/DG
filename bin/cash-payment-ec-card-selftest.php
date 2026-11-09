<?php
declare(strict_types=1);

/**
 * K8b — Selftest EC/Karte (Betrag+Art, ohne Terminal; FiBu-Bankkonto).
 * php bin/cash-payment-ec-card-selftest.php
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

$labels = CashPaymentRepository::methodLabels();
$hints = CashPaymentRepository::methodHints();
$expect_true(($labels['ec'] ?? '') === 'EC', 'Label EC');
$expect_true(($labels['card'] ?? '') === 'Karte', 'Label Karte');
$expect_true(isset($hints['ec'], $hints['card']), 'Hints EC/Karte');
$expect_true(str_contains($hints['ec'], 'Terminal') || str_contains($hints['ec'], 'girocard'), 'Hint EC');
$expect_true(
    CashRegisterFiBuPostingService::METHODS_EC_CARD === ['ec', 'card'],
    'METHODS_EC_CARD'
);

$view = (string) file_get_contents(DG_ROOT . '/views/modules/registerkasse-bon.php');
$expect_true(str_contains($view, 'payment_method" value="ec"') || str_contains($view, 'value="ec"'), 'UI Rest EC');
$expect_true(str_contains($view, 'payment_method" value="card"') || str_contains($view, 'value="card"'), 'UI Rest Karte');
$expect_true(str_contains($view, 'ohne Terminal') || str_contains($view, 'Terminal-Anbindung'), 'Hinweis ohne Terminal');

$receipt = ['total_gross' => 50.0, 'receipt_number_display' => 'E-1'];
$lines = [['gross_amount' => 50.0, 'tax_rate' => 19]];
foreach (['ec' => '1200', 'card' => '1200'] as $method => $expectAccount) {
    $built = CashRegisterFiBuPostingService::buildJournalLines(
        $receipt,
        $lines,
        [['payment_method' => $method, 'amount' => 50.0]],
        'skr03',
        'cr:test'
    );
    $hasBank = false;
    foreach ($built as $row) {
        if (($row['side'] ?? '') === 'debit' && ($row['account_number'] ?? '') === $expectAccount) {
            $hasBank = true;
        }
    }
    $expect_true($hasBank, $method . ' → Soll Bank ' . $expectAccount);
}

if (!Database::isConfigured()) {
    echo "SKIP DB — Label/UI/FiBu-Mapping geprüft.\n";
    if ($fail > 0) {
        fwrite(STDERR, "cash-payment-ec-card-selftest FAILED ({$fail})\n");
        exit(1);
    }
    echo "cash-payment-ec-card-selftest OK (ohne DB)\n";
    exit(0);
}

MigrationRunner::runPending();
if (!CashReceiptRepository::tableExists() || !CashPaymentRepository::tableExists()) {
    echo "FAIL Tabellen fehlen\n";
    exit(1);
}

$sfx = substr(bin2hex(random_bytes(3)), 0, 6);
$pdo = Database::pdo();
$locId = 0;
$regId = 0;
$ids = [];
$cleanup = static function () use ($pdo, &$ids, &$regId, &$locId): void {
    foreach ($ids as $id) {
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
        'code' => 'ST-K8B-' . $sfx,
        'name' => 'Selftest Filiale K8b',
        'is_active' => 1,
    ]);
    $regId = CashRegisterRepository::insert([
        'location_id' => $locId,
        'code' => 'KR-K8B-' . $sfx,
        'name' => 'Selftest Kasse K8b',
        'next_receipt_number' => 1,
        'is_active' => 1,
    ]);
    CashSessionService::open($regId, 5, '0');

    // EC full
    $idEc = CashReceiptService::startDraft($regId, 5);
    $ids[] = $idEc;
    CashReceiptService::addLine($idEc, [
        'title' => 'EC-Kauf',
        'quantity' => 1,
        'unit_price_gross' => '20,00',
        'tax_rate' => 19,
    ]);
    CashReceiptService::addPayment($idEc, [
        'payment_method' => 'ec',
        'amount' => '20,00',
        'external_ref' => 'TRACE-EC-1',
    ]);
    $pays = CashPaymentRepository::listByReceipt($idEc);
    $expect_true(($pays[0]['payment_method'] ?? '') === 'ec', 'EC method');
    $expect_true(($pays[0]['external_ref'] ?? '') === 'TRACE-EC-1', 'EC Referenz');
    CashReceiptService::finalizeWithoutTse($idEc);

    // Karte full
    $idCard = CashReceiptService::startDraft($regId, 5);
    $ids[] = $idCard;
    CashReceiptService::addLine($idCard, [
        'title' => 'Kartenkauf',
        'quantity' => 1,
        'unit_price_gross' => '15,00',
        'tax_rate' => 7,
    ]);
    CashReceiptService::addPayment($idCard, [
        'payment_method' => 'card',
        'amount' => '15,00',
        'external_ref' => 'TRACE-CARD-1',
    ]);
    $pays = CashPaymentRepository::listByReceipt($idCard);
    $expect_true(($pays[0]['payment_method'] ?? '') === 'card', 'Karte method');
    CashReceiptService::finalizeWithoutTse($idCard);
    $expect_true((CashReceiptRepository::findById($idCard)['status'] ?? '') === 'finalized', 'Karte finalized');
} catch (Throwable $e) {
    echo 'FAIL Ausnahme: ' . $e->getMessage() . "\n";
    $fail++;
} finally {
    $cleanup();
    echo "OK  Selftest-Daten entfernt\n";
}

if ($fail > 0) {
    fwrite(STDERR, "cash-payment-ec-card-selftest FAILED ({$fail})\n");
    exit(1);
}
echo "cash-payment-ec-card-selftest OK (K8b)\n";
