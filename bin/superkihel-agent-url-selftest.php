<?php

declare(strict_types=1);

/**
 * SKP1e — Super-Kichel „Agent öffnen“ zeigt auf HTTPS-Agent.
 * Usage: php bin/superkihel-agent-url-selftest.php
 */

if (!defined('DG_ROOT')) {
    define('DG_ROOT', dirname(__DIR__));
}

$view = (string) file_get_contents(DG_ROOT . '/views/modules/superkihel.php');
$fail = static function (string $msg): never {
    fwrite(STDERR, "FAIL: {$msg}\n");
    exit(1);
};

if (!str_contains($view, 'https://agent.ganz-soft.de/')) {
    $fail('missing HTTPS agent URL');
}
if (!str_contains($view, 'data-superkihel-agent')) {
    $fail('missing data-superkihel-agent marker');
}
if (preg_match('#href=["\']http://127\.0\.0\.1:3000/?["\']#', $view) === 1) {
    $fail('legacy localhost:3000 button still present');
}
if (str_contains($view, 'SKP1e') || str_contains($view, 'SKP0')) {
    $fail('plan codes must not appear in end-user view');
}

fwrite(STDOUT, "OK SKP1e agent URL selftest\n");
exit(0);
