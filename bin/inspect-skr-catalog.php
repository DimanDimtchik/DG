<?php
declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap.php';

foreach (['skr03-full.json' => 'skr03', 'skr04-full.json' => 'skr04'] as $file => $label) {
    $path = DG_ROOT . '/assets/data/' . $file;
    $raw = json_decode((string) file_get_contents($path), true);
    if (!is_array($raw)) {
        echo "{$label}: invalid json\n";
        continue;
    }
    $leaf = 0;
    $samples = [];
    foreach ($raw as $row) {
        if (!is_array($row) || empty($row['leaf'])) {
            continue;
        }
        $code = (string) ($row['code'] ?? '');
        if ($code === '' || $code === '-1' || !ctype_digit($code)) {
            continue;
        }
        ++$leaf;
        if (count($samples) < 5) {
            $samples[] = $code . ' ' . ($row['name'] ?? '');
        }
    }
    echo "{$label}: {$leaf} leaf accounts\n";
    foreach ($samples as $s) {
        echo "  {$s}\n";
    }
}

$parsed = ChartAccountCatalog::accountsForSkr('skr03');
echo 'Catalog parser skr03: ' . count($parsed) . "\n";
$f = ChartAccountCatalog::accountsForSkr('skr03');
foreach (ChartAccountCatalog::accountsForSkr('skr03') as $a) {
    if (str_contains(mb_strtolower($a['name']), 'fahrzeug') || str_contains(mb_strtolower($a['name']), 'pkw')) {
        echo $a['account_number'] . ' ' . $a['name'] . "\n";
    }
}
