<?php
declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap.php';

header('Content-Type: text/plain; charset=utf-8');

try {
    $list = VoucherRepository::list(['year' => 2026, 'page' => 1]);
    echo 'total=' . $list['total'] . ' items=' . count($list['items']) . PHP_EOL;
    foreach ($list['items'] as $item) {
        echo 'id=' . ($item['id'] ?? '')
            . ' inv=' . ($item['invoice_number'] ?? '')
            . ' date=' . ($item['voucher_date'] ?? '')
            . ' gross=' . ($item['gross_amount'] ?? '')
            . PHP_EOL;
    }
} catch (Throwable $e) {
    echo 'ERROR: ' . $e->getMessage() . PHP_EOL;
    echo $e->getFile() . ':' . $e->getLine() . PHP_EOL;
}
