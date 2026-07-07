<?php
declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap.php';

header('Content-Type: text/plain; charset=utf-8');

foreach (['IMP-0001', 'K109'] as $number) {
    foreach (VoucherIncomePositions::searchArticles($number, 5) as $article) {
        if (($article['article_number'] ?? '') !== $number) {
            continue;
        }
        echo $number . ': ' . json_encode($article, JSON_UNESCAPED_UNICODE) . PHP_EOL;
        break;
    }
}
