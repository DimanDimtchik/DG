<?php
declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap.php';

header('Content-Type: text/plain; charset=utf-8');

try {
    $pdo = Database::pdo();
    echo "DB ok\n";
    foreach (['reverse_charge_type', 'ustva_snapshot'] as $col) {
        $r = $pdo->query("SHOW COLUMNS FROM dg_vouchers LIKE " . $pdo->quote($col))->fetch();
        echo "dg_vouchers.$col: " . ($r ? 'yes' : 'no') . "\n";
    }
    foreach (['line_kind', 'ustva_kz', 'posting_side'] as $col) {
        $r = $pdo->query("SHOW COLUMNS FROM dg_voucher_lines LIKE " . $pdo->quote($col))->fetch();
        echo "dg_voucher_lines.$col: " . ($r ? 'yes' : 'no') . "\n";
    }
    $count = MigrationRunner::runPending();
    echo "Migrations applied: {$count}\n";
    echo "Done.\n";
} catch (Throwable $e) {
    http_response_code(500);
    echo 'ERROR: ' . $e->getMessage() . "\n";
    echo $e->getFile() . ':' . $e->getLine() . "\n";
}
