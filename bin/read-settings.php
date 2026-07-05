<?php
declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap.php';

if (!Database::isConfigured()) {
    fwrite(STDERR, "DB not configured\n");
    exit(1);
}

foreach (Database::pdo()->query('SELECT setting_key, value_json FROM dg_settings') as $row) {
    $data = json_decode((string) $row['value_json'], true);
    if (!is_array($data)) {
        continue;
    }
    if ($row['setting_key'] === 'mail' && isset($data['smtp_password'])) {
        $data['smtp_password'] = $data['smtp_password'] !== '' ? '***' : '';
    }
    echo $row['setting_key'] . ': ' . json_encode($data, JSON_UNESCAPED_UNICODE) . PHP_EOL;
}
