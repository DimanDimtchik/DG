<?php
declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap.php';

header('Content-Type: text/plain; charset=utf-8');

$items = VoucherIncomePositions::searchArticles('', 10);
echo 'count=' . count($items) . PHP_EOL;
foreach ($items as $item) {
    echo ($item['article_number'] ?? '') . ' | ' . ($item['title'] ?? '') . ' | ' . ($item['price_label'] ?? '') . PHP_EOL;
}
