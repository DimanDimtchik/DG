<?php
declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap.php';

header('Content-Type: text/plain; charset=utf-8');

foreach (['erloes', 'erlös', 'einnahme'] as $q) {
    echo "=== {$q} (income) ===\n";
    foreach (ChartAccountRepository::search($q, 'skr03', 25, 'income') as $r) {
        echo $r['account_number'] . ' | ' . $r['name'] . "\n";
    }
    echo "\n";
}

echo "=== erloes income tax 7 ===\n";
foreach (ChartAccountRepository::search('beratung', 'skr03', 25, 'income', 7) as $r) {
    echo $r['account_number'] . ' | ' . $r['name'] . "\n";
}
echo "\n=== erloes income tax 19 ===\n";
foreach (ChartAccountRepository::search('beratung', 'skr03', 25, 'income', 19) as $r) {
    echo $r['account_number'] . ' | ' . $r['name'] . "\n";
}
