<?php

declare(strict_types=1);

/**
 * Optional DB config. Phase 1 runs without DB.
 * On server: copy database.local.php (not in git) with host/name/user/password.
 *
 * @return array{configured: bool, host?: string, name?: string, user?: string, password?: string, charset?: string}
 */
$local = SHOP_ROOT . '/config/database.local.php';
if (is_file($local)) {
    /** @var array<string, mixed> $cfg */
    $cfg = require $local;
    return [
        'configured' => true,
        'host' => (string) ($cfg['host'] ?? 'localhost'),
        'name' => (string) ($cfg['name'] ?? ''),
        'user' => (string) ($cfg['user'] ?? ''),
        'password' => (string) ($cfg['password'] ?? ''),
        'charset' => (string) ($cfg['charset'] ?? 'utf8mb4'),
    ];
}

return ['configured' => false];
