#!/usr/bin/env php
<?php
declare(strict_types=1);

if ($argc < 2) {
    fwrite(STDERR, "Usage: php bin/shop-admin-password-hash.php \"IhrPasswort\"\n");
    exit(1);
}

echo password_hash($argv[1], PASSWORD_DEFAULT), PHP_EOL;
