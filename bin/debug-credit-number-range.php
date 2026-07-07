<?php
declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap.php';

echo 'credit peek: ' . VoucherRepository::peekInvoiceNumber('credit') . PHP_EOL;
echo 'labels: ' . json_encode(VoucherRepository::autoInvoiceNumberLabels(), JSON_UNESCAPED_UNICODE) . PHP_EOL;
echo 'types: ' . json_encode(NumberRangeSettings::typeGroups()['Belege'], JSON_UNESCAPED_UNICODE) . PHP_EOL;
