<?php
declare(strict_types=1);

/**
 * KR7 — Widget und Super-Kichel nutzen dieselbe Hilfe-Pipeline (/api/kichel).
 * Usage: php bin/kichel-pipeline-selftest.php
 */
if (!defined('DG_ROOT')) {
    define('DG_ROOT', dirname(__DIR__));
}
require_once DG_ROOT . '/src/autoload.php';

$failed = 0;

function kr7_assert(bool $ok, string $label, mixed $detail = null): void
{
    global $failed;
    if (!$ok) {
        $failed++;
    }
    $suffix = $detail !== null
        ? ' → ' . json_encode($detail, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        : '';
    echo ($ok ? 'OK  ' : 'FAIL') . '  ' . $label . $suffix . "\n";
}

$widget = (string) file_get_contents(DG_ROOT . '/views/partials/kichel-widget.php');
$kichelJs = (string) file_get_contents(DG_ROOT . '/assets/js/kichel.js');
$superJs = (string) file_get_contents(DG_ROOT . '/assets/js/superkihel.js');
$superView = (string) file_get_contents(DG_ROOT . '/views/modules/superkihel.php');
$api = (string) file_get_contents(DG_ROOT . '/src/Kichel/KichelApi.php');
$index = (string) file_get_contents(DG_ROOT . '/index.php');

kr7_assert(
    str_contains($widget, "apiUrl: '/api/kichel'") || str_contains($widget, 'apiUrl: "/api/kichel"'),
    'widget defines apiUrl /api/kichel'
);

kr7_assert(
    str_contains($kichelJs, 'window.dgKichel') && str_contains($kichelJs, 'cfg.apiUrl'),
    'kichel.js uses window.dgKichel.apiUrl'
);

kr7_assert(
    str_contains($superJs, 'window.dgKichel') && str_contains($superJs, 'cfg.apiUrl'),
    'superkihel.js uses window.dgKichel.apiUrl'
);

kr7_assert(
    !preg_match('#fetch\s*\(\s*[\'"]/(?!api/kichel)#', $kichelJs)
    && str_contains($kichelJs, 'fetch(cfg.apiUrl'),
    'kichel.js fetch only via cfg.apiUrl'
);

kr7_assert(
    str_contains($superJs, 'fetch(cfg.apiUrl')
    && !str_contains($superJs, '/api/generate')
    && !str_contains($superJs, 'ollama'),
    'superkihel.js no direct Ollama / alternate help API'
);

kr7_assert(
    !str_contains($kichelJs, '/api/generate') && !str_contains($kichelJs, 'ollama'),
    'kichel.js no direct Ollama'
);

kr7_assert(
    str_contains($index, "case '/api/kichel'") && str_contains($index, 'KichelApi::handle()'),
    'index.php routes /api/kichel → KichelApi'
);

kr7_assert(
    str_contains($api, 'KichelAssistant::answer'),
    'KichelApi calls KichelAssistant::answer only'
);

kr7_assert(
    !preg_match('#\b(fetch|action)\s*[=:(].{0,40}/api/#i', $superView)
    && str_contains($superView, 'superkihel.js'),
    'superkihel view has no own help API call'
);

// OpenHands-Button ist SKP (Programmieren), kein zweites Hilfe-Prompt-System
kr7_assert(
    str_contains($superView, 'Programmieren') || str_contains($superView, 'Agent'),
    'superkihel may expose Agent UI (SKP) separately from help chat'
);

$adminRole = (string) App::config('roles.admin', 'administrator');
$user = new User(1, 'admin', 'Administrator', '', [$adminRole], true);
$one = KichelAssistant::answer($user, 'Wo trage ich die USt-ID ein?');
kr7_assert(
    isset($one['answer'], $one['sources']) && is_array($one['sources']),
    'single assistant response shape used by both UIs',
    ['has_sources' => !empty($one['sources'])]
);

echo $failed === 0 ? "ALL OK\n" : "FAILED {$failed}\n";
exit($failed === 0 ? 0 : 1);
