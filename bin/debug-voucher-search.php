<?php
declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap.php';

header('Content-Type: text/plain; charset=utf-8');

$q = $argv[1] ?? 're123';

try {
    $list = VoucherRepository::list(['year' => 2026, 'search' => $q, 'page' => 1]);
    echo 'OK total=' . $list['total'] . ' items=' . count($list['items']) . PHP_EOL;
} catch (Throwable $e) {
    echo 'ERROR: ' . $e->getMessage() . PHP_EOL;
    echo $e->getFile() . ':' . $e->getLine() . PHP_EOL;
    echo $e->getTraceAsString() . PHP_EOL;
}
