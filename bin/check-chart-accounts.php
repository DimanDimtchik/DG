<?php
declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap.php';

$skr = ChartOfAccountsSettings::activeSkrType();
ChartAccountRepository::ensureSeeded($skr);

echo "SKR: {$skr}\n";
echo 'Konten: ' . ChartAccountRepository::countForSkr($skr) . ' (Katalog ' . ChartAccountCatalog::catalogCount($skr) . ")\n";

foreach (['3200', '0320', '4530'] as $n) {
    $a = ChartAccountRepository::findByNumber($n, $skr);
    echo $n . ': ' . ($a ? $a['name'] : 'MISSING') . "\n";
}

echo "Suche fahrzeug:\n";
foreach (ChartAccountRepository::search('fahrzeug', $skr, 8) as $r) {
    echo '  ' . $r['account_number'] . ' ' . $r['name'] . "\n";
}
