<?php
declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap.php';

header('Content-Type: text/plain; charset=utf-8');

try {
    echo 'peek income: ' . VoucherRepository::peekInvoiceNumber('income') . PHP_EOL;
    echo 'uses expense: ' . (VoucherRepository::usesAutoInvoiceNumber('expense') ? 'yes' : 'no') . PHP_EOL;
} catch (Throwable $e) {
    echo 'ERROR: ' . $e->getMessage() . PHP_EOL;
}
