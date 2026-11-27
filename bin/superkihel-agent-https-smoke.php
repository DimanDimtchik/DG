<?php

declare(strict_types=1);

/**
 * SKP1d — Formal-Smoke HTTPS-Agent (ohne Passwort).
 * Usage: php bin/superkihel-agent-https-smoke.php
 *
 * Prüft: UI → 401 · /mcp/ ohne Basic-Auth-Challenge · CRM-Button-URL.
 * Netzwerk nötig; bei Offline → Skip mit Hinweis (Exit 0 nur bei echten Passes).
 */

if (!defined('DG_ROOT')) {
    define('DG_ROOT', dirname(__DIR__));
}

$failed = 0;
$assert = static function (bool $ok, string $label, mixed $detail = null) use (&$failed): void {
    if (!$ok) {
        $failed++;
    }
    $suffix = $detail !== null
        ? ' → ' . json_encode($detail, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        : '';
    echo ($ok ? 'OK  ' : 'FAIL') . '  ' . $label . $suffix . "\n";
};

$view = (string) file_get_contents(DG_ROOT . '/views/modules/superkihel.php');
$assert(str_contains($view, 'https://agent.ganz-soft.de/'), 'CRM button HTTPS URL');
$assert(!preg_match('#href=["\']http://127\.0\.0\.1:3000/?["\']#', $view), 'no localhost:3000 primary button');

$fetchHeaders = static function (string $url): array {
    $ctx = stream_context_create([
        'http' => [
            'method' => 'HEAD',
            'timeout' => 12,
            'ignore_errors' => true,
            'header' => "User-Agent: DG-SKP1d-Smoke\r\n",
        ],
        'ssl' => [
            'verify_peer' => true,
            'verify_peer_name' => true,
        ],
    ]);
    $body = @file_get_contents($url, false, $ctx);
    $headers = $http_response_header ?? [];
    return ['ok' => $body !== false || $headers !== [], 'headers' => $headers];
};

$statusOf = static function (array $headers): ?int {
    foreach ($headers as $line) {
        if (preg_match('#^HTTP/\S+\s+(\d{3})#', $line, $m) === 1) {
            return (int) $m[1];
        }
    }
    return null;
};

$hasWwwAuth = static function (array $headers): bool {
    foreach ($headers as $line) {
        if (stripos($line, 'WWW-Authenticate:') === 0) {
            return true;
        }
    }
    return false;
};

$ui = $fetchHeaders('https://agent.ganz-soft.de/');
if (!$ui['ok'] || $ui['headers'] === []) {
    fwrite(STDERR, "SKIP/FAIL: HTTPS agent unreachable — network required for SKP1d smoke\n");
    exit(1);
}
$uiStatus = $statusOf($ui['headers']);
$assert($uiStatus === 401, 'UI without credentials → 401', $uiStatus);
$assert($hasWwwAuth($ui['headers']), 'UI sends WWW-Authenticate Basic');

$mcp = $fetchHeaders('https://agent.ganz-soft.de/mcp/');
$mcpStatus = $statusOf($mcp['headers']);
$assert($mcp['headers'] !== [], 'MCP endpoint reachable');
$assert(!$hasWwwAuth($mcp['headers']), 'MCP without WWW-Authenticate', $mcp['headers'][0] ?? null);
$assert($mcpStatus !== 401, 'MCP not 401 (auth off)', $mcpStatus);

if ($failed > 0) {
    fwrite(STDERR, "FAILED: {$failed}\n");
    exit(1);
}
echo "OK SKP1d HTTPS smoke\n";
exit(0);
