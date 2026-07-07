<?php
declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap.php';

$invoice = $argv[1] ?? '';
if ($invoice === '') {
    fwrite(STDERR, "Usage: php bin/debug-voucher-invoice.php <invoice_number>\n");
    exit(1);
}

MigrationRunner::runPending();
$pdo = Database::pdo();
$stmt = $pdo->prepare('SELECT * FROM dg_vouchers WHERE invoice_number = :invoice LIMIT 1');
$stmt->execute(['invoice' => $invoice]);
$voucher = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$voucher) {
    echo "NOT_FOUND\n";
    exit(0);
}

echo 'VOUCHER id=' . $voucher['id']
    . ' supplier=' . ($voucher['supplier_name'] ?? '')
    . ' gross=' . $voucher['gross_amount']
    . ' net=' . $voucher['net_amount']
    . ' tax=' . $voucher['tax_amount']
    . ' rate=' . $voucher['tax_rate']
    . ' account=' . $voucher['account_number']
    . ' rc=' . ($voucher['reverse_charge_type'] ?? '')
    . PHP_EOL;

$lines = VoucherRepository::linesForVoucher((int) $voucher['id'], false);
foreach ($lines as $line) {
    echo 'LINE kind=' . ($line['line_kind'] ?? '')
        . ' acct=' . $line['account_number']
        . ' (' . $line['account_name'] . ')'
        . ' gross=' . $line['gross_amount']
        . ' net=' . $line['net_amount']
        . ' tax=' . $line['tax_amount']
        . ' rate=' . $line['tax_rate']
        . PHP_EOL;
}

if (($argv[2] ?? '') === '--recent') {
    echo PHP_EOL . 'RECENT:' . PHP_EOL;
    $recent = $pdo->query(
        'SELECT id, voucher_date, invoice_number, supplier_name, gross_amount, account_number, created_at
         FROM dg_vouchers ORDER BY id DESC LIMIT 15'
    );
    while ($row = $recent->fetch(PDO::FETCH_ASSOC)) {
        echo implode(' | ', array_map(static fn ($v): string => (string) $v, $row)) . PHP_EOL;
    }
}
