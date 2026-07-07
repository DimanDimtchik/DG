<?php
declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap.php';

$pdo = Database::pdo();
echo "ALL:\n";
$r = $pdo->query('SELECT id, voucher_date, YEAR(voucher_date) AS y, invoice_number FROM dg_vouchers ORDER BY id');
while ($row = $r->fetch(PDO::FETCH_ASSOC)) {
    echo implode(' | ', $row) . PHP_EOL;
}
echo "\nCOUNT 2026:\n";
echo (int) $pdo->query("SELECT COUNT(*) FROM dg_vouchers WHERE YEAR(voucher_date) = 2026")->fetchColumn() . PHP_EOL;
