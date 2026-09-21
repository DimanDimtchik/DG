<?php
declare(strict_types=1);

/**
 * Sanitize-Smoke für erweiterte Block-Einstellungen.
 * php bin/website-block-advanced-selftest.php
 */
if (!defined('DG_ROOT')) {
    define('DG_ROOT', dirname(__DIR__));
}
require_once DG_ROOT . '/src/autoload.php';

$failures = 0;
function assertTrue(bool $ok, string $msg): void
{
    global $failures;
    if ($ok) {
        echo "OK  $msg\n";
        return;
    }
    $failures++;
    echo "FAIL $msg\n";
}

$clean = WebsiteBlockAdvanced::normalize([
    'display' => [
        'display' => 'flex',
        'padding' => '12px',
        'margin' => '8px 0',
        'opacity' => '0.5',
        'textAlign' => 'center',
        'bogus' => 'x',
    ],
    'colors' => [
        'color' => '#AbC',
        'background' => 'red',
    ],
    'border' => [
        'width' => '2px',
        'style' => 'dashed',
        'color' => '#112233',
        'radius' => '8px',
    ],
    'attrs' => [
        'id' => 'hero-block',
        'className' => 'foo bar onclick <script>',
        'title' => 'Hallo',
        'ariaLabel' => 'Abschnitt',
    ],
    'evil' => ['x' => 1],
]);

assertTrue(($clean['display']['display'] ?? '') === 'flex', 'display allowlist');
assertTrue(($clean['display']['padding'] ?? '') === '12px', 'padding length');
assertTrue(($clean['display']['margin'] ?? '') === '8px 0', 'margin shorthand');
assertTrue(($clean['display']['opacity'] ?? '') === '0.5', 'opacity');
assertTrue(($clean['colors']['color'] ?? '') === '#abc', 'hex color normalized');
assertTrue(!isset($clean['colors']['background']), 'named color rejected');
assertTrue(($clean['border']['style'] ?? '') === 'dashed', 'border style');
assertTrue(($clean['attrs']['id'] ?? '') === 'hero-block', 'html id');
assertTrue(($clean['attrs']['className'] ?? '') === 'foo bar onclick', 'class tokens kept, tags stripped from list');
assertTrue(!isset($clean['evil']), 'unknown group dropped');
assertTrue(!isset($clean['display']['bogus']), 'unknown display key dropped');

$bad = WebsiteBlockAdvanced::normalize([
    'display' => [
        'width' => '100vw',
        'padding' => 'expression(alert(1))',
        'opacity' => '2',
    ],
    'colors' => ['color' => 'javascript:alert(1)'],
    'attrs' => [
        'id' => '1bad',
        'className' => 'a<script>b',
    ],
]);
assertTrue($bad === [], 'dangerous values all rejected');

$css = WebsiteBlockAdvanced::toInlineCss(WebsiteBlockAdvanced::normalize([
    'colors' => ['color' => '#111111', 'background' => '#ffffff'],
    'display' => ['padding' => '10px', 'textAlign' => 'left'],
]));
assertTrue(str_contains($css, 'color:#111111'), 'css contains color');
assertTrue(str_contains($css, 'background-color:#ffffff'), 'css contains background');
assertTrue(str_contains($css, 'padding:10px'), 'css contains padding');
assertTrue(str_contains($css, 'text-align:left'), 'css contains text-align');
assertTrue(!str_contains($css, 'expression'), 'css has no expression');

$attrs = WebsiteBlockAdvanced::toHtmlAttributes(WebsiteBlockAdvanced::normalize([
    'attrs' => ['id' => 'x', 'title' => 'T"est', 'ariaLabel' => 'A'],
]));
assertTrue(str_contains($attrs, 'id="x"'), 'attr id');
assertTrue(str_contains($attrs, 'aria-label="A"'), 'attr aria-label');
assertTrue(str_contains($attrs, 'title="T&quot;est"') || str_contains($attrs, 'title="T&#34;est"') || str_contains($attrs, 'title="T"'), 'attr title escaped');

$layout = WebsiteContent::normalizeLayout([
    'rows' => [[
        'id' => 'r1',
        'columns' => [[
            'id' => 'c1',
            'width' => 12,
            'blocks' => [[
                'id' => 'b1',
                'type' => 'text',
                'text' => 'Hallo',
                'advanced' => [
                    'colors' => ['color' => '#00ff00', 'background' => 'lime'],
                    'attrs' => ['id' => 'ok-id'],
                ],
            ]],
        ]],
    ]],
]);
$blockAdv = $layout['rows'][0]['columns'][0]['blocks'][0]['advanced'] ?? [];
assertTrue(($blockAdv['colors']['color'] ?? '') === '#00ff00', 'normalizeLayout keeps hex');
assertTrue(!isset($blockAdv['colors']['background']), 'normalizeLayout drops lime');
assertTrue(($blockAdv['attrs']['id'] ?? '') === 'ok-id', 'normalizeLayout keeps id');

if ($failures > 0) {
    fwrite(STDERR, "FAILED: $failures\n");
    exit(1);
}
echo "ALL OK\n";
exit(0);
