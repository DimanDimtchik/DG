<?php
declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap.php';

$pdo = Database::pdo();
$r = $pdo->query('SELECT id, invoice_number, voucher_type, payment_status FROM dg_vouchers ORDER BY id');
while ($row = $r->fetch(PDO::FETCH_ASSOC)) {
    echo implode(' | ', $row) . PHP_EOL;
}

$list = VoucherRepository::list(['year' => 2026, 'type' => '', 'page' => 1]);
echo PHP_EOL . 'list with type empty: total=' . $list['total'] . PHP_EOL;
$list2 = VoucherRepository::list(['year' => 2026, 'page' => 1]);
echo 'list without type key: total=' . $list2['total'] . PHP_EOL;
