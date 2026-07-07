<?php
declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap.php';

header('Content-Type: text/plain; charset=utf-8');

foreach (['ware', '3200'] as $q) {
    echo "q={$q} expense:\n";
    foreach (ChartAccountRepository::search($q, 'skr03', 10, 'expense') as $row) {
        echo '  ' . $row['account_number'] . ' ' . $row['section'] . ' ' . $row['name'] . PHP_EOL;
    }
    echo "q={$q} income:\n";
    foreach (ChartAccountRepository::search($q, 'skr03', 10, 'income') as $row) {
        echo '  ' . $row['account_number'] . ' ' . $row['section'] . ' ' . $row['name'] . PHP_EOL;
    }
    echo PHP_EOL;
}
