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

// —— W1: RGBA, Seiten-Rahmen, Radius-Ecken ——
$rgba = WebsiteBlockAdvanced::normalize([
    'colors' => [
        'color' => 'rgba(10, 20, 30, 0.5)',
        'background' => '#11223344',
    ],
]);
assertTrue(($rgba['colors']['color'] ?? '') === 'rgba(10,20,30,0.5)', 'rgba normalized');
assertTrue(($rgba['colors']['background'] ?? '') === '#11223344', 'hex8 kept');

$rgbaBad = WebsiteBlockAdvanced::normalize([
    'colors' => [
        'color' => 'rgba(300,0,0,1)',
        'background' => 'rgba(0,0,0,2)',
    ],
]);
assertTrue($rgbaBad === [], 'invalid rgba rejected');

$sides = WebsiteBlockAdvanced::normalize([
    'border' => [
        'width' => '9px',
        'style' => 'solid',
        'color' => '#000000',
        'topWidth' => '2px',
        'topStyle' => 'dashed',
        'topColor' => 'rgba(0,0,0,0.25)',
        'leftWidth' => '4px',
        'leftStyle' => 'solid',
        'leftColor' => '#abc',
        'radius' => '10px',
        'radiusTL' => '2px',
        'radiusBR' => '12px',
    ],
]);
assertTrue(($sides['border']['topWidth'] ?? '') === '2px', 'top width');
assertTrue(($sides['border']['topStyle'] ?? '') === 'dashed', 'top style');
assertTrue(($sides['border']['topColor'] ?? '') === 'rgba(0,0,0,0.25)', 'top rgba color');
assertTrue(($sides['border']['leftColor'] ?? '') === '#abc', 'left hex');
assertTrue(($sides['border']['radiusTL'] ?? '') === '2px', 'radius TL');
assertTrue(($sides['border']['radiusBR'] ?? '') === '12px', 'radius BR');

$sideCss = WebsiteBlockAdvanced::toInlineCss($sides);
assertTrue(str_contains($sideCss, 'border-top:2px dashed rgba(0,0,0,0.25)'), 'css border-top');
assertTrue(str_contains($sideCss, 'border-left:4px solid #abc'), 'css border-left');
assertTrue(!str_contains($sideCss, 'border:9px'), 'uniform border skipped when sides set');
assertTrue(str_contains($sideCss, 'border-radius:2px 0 12px 0'), 'css corner radii');

$radiusOnly = WebsiteBlockAdvanced::toInlineCss(WebsiteBlockAdvanced::normalize([
    'border' => ['radius' => '8px 4px'],
]));
assertTrue(str_contains($radiusOnly, 'border-radius:8px 4px'), 'radius shorthand css');

// —— W2: Hintergrundbild ——
$bg = WebsiteBlockAdvanced::normalize([
    'colors' => [
        'background' => 'rgba(0,0,0,0.1)',
        'backgroundImage' => '/media/demo/bg.png',
        'backgroundSize' => 'cover',
        'backgroundPosition' => 'center top',
        'backgroundRepeat' => 'no-repeat',
    ],
]);
assertTrue(($bg['colors']['backgroundImage'] ?? '') === '/media/demo/bg.png', 'bg image path');
assertTrue(($bg['colors']['backgroundSize'] ?? '') === 'cover', 'bg size cover');
assertTrue(($bg['colors']['backgroundPosition'] ?? '') === 'center top', 'bg position');
assertTrue(($bg['colors']['backgroundRepeat'] ?? '') === 'no-repeat', 'bg repeat');

$bgCss = WebsiteBlockAdvanced::toInlineCss($bg);
assertTrue(str_contains($bgCss, 'background-image:url("/media/demo/bg.png")'), 'css bg image');
assertTrue(str_contains($bgCss, 'background-size:cover'), 'css bg size');
assertTrue(str_contains($bgCss, 'background-position:center top'), 'css bg position');
assertTrue(str_contains($bgCss, 'background-repeat:no-repeat'), 'css bg repeat');
assertTrue(str_contains($bgCss, 'background-color:rgba(0,0,0,0.1)'), 'css bg color with image');

$bgUrlWrap = WebsiteBlockAdvanced::normalize([
    'colors' => ['backgroundImage' => 'url("/media/x.jpg")'],
]);
assertTrue(($bgUrlWrap['colors']['backgroundImage'] ?? '') === '/media/x.jpg', 'url() wrapper stripped');

$bgBad = WebsiteBlockAdvanced::normalize([
    'colors' => [
        'backgroundImage' => 'javascript:alert(1)',
        'backgroundSize' => '100vw',
        'backgroundPosition' => 'left center bottom',
        'backgroundRepeat' => 'space',
    ],
]);
assertTrue($bgBad === [], 'dangerous bg values rejected');

$bgHttps = WebsiteBlockAdvanced::normalize([
    'colors' => ['backgroundImage' => 'https://cdn.example.com/a.png'],
]);
assertTrue(($bgHttps['colors']['backgroundImage'] ?? '') === 'https://cdn.example.com/a.png', 'https bg allowed');

// —— W5: Hover / Visited ——
$ix = WebsiteBlockAdvanced::normalize([
    'hover' => [
        'color' => '#0066cc',
        'background' => 'rgba(0,102,204,0.1)',
        'borderColor' => '#abc',
        'textDecoration' => 'underline',
        'opacity' => '0.9',
        'evil' => 'expression(1)',
    ],
    'visited' => [
        'color' => '#551a8b',
        'textDecoration' => 'none',
    ],
]);
assertTrue(($ix['hover']['color'] ?? '') === '#0066cc', 'hover color');
assertTrue(($ix['hover']['background'] ?? '') === 'rgba(0,102,204,0.1)', 'hover bg');
assertTrue(($ix['hover']['borderColor'] ?? '') === '#abc', 'hover borderColor');
assertTrue(($ix['hover']['textDecoration'] ?? '') === 'underline', 'hover decoration');
assertTrue(($ix['hover']['opacity'] ?? '') === '0.9', 'hover opacity');
assertTrue(!isset($ix['hover']['evil']), 'hover evil key dropped');
assertTrue(($ix['visited']['color'] ?? '') === '#551a8b', 'visited color');
assertTrue(WebsiteBlockAdvanced::hasInteractionStyles($ix), 'has interaction');

$ixClass = WebsiteBlockAdvanced::interactionClassName('btn-1', $ix);
assertTrue($ixClass === 'ws-adv-h-btn-1', 'interaction class from id');
$ixCss = WebsiteBlockAdvanced::toInteractionCss($ixClass, $ix);
assertTrue(str_contains($ixCss, '.ws-adv-h-btn-1 a:hover'), 'css hover selector');
assertTrue(str_contains($ixCss, '.ws-adv-h-btn-1 .ws-btn:hover'), 'css btn hover');
assertTrue(str_contains($ixCss, 'color:#0066cc'), 'css hover color');
assertTrue(str_contains($ixCss, 'text-decoration:underline'), 'css hover deco');
assertTrue(str_contains($ixCss, 'a:visited'), 'css visited selector');
assertTrue(str_contains($ixCss, 'color:#551a8b'), 'css visited color');
assertTrue(!str_contains($ixCss, 'expression'), 'no expression in css');
assertTrue(!str_contains($ixCss, '@import'), 'no import in css');

$ixBad = WebsiteBlockAdvanced::normalize([
    'hover' => [
        'color' => 'red',
        'textDecoration' => 'blink',
        'opacity' => '2',
        'background' => 'javascript:alert(1)',
    ],
]);
assertTrue($ixBad === [], 'dangerous hover values rejected');
assertTrue(WebsiteBlockAdvanced::interactionClassName('x', []) === '', 'empty interaction class');
assertTrue(WebsiteBlockAdvanced::toInteractionCss('not-allowed', $ix) === '', 'bad class name rejected');

if ($failures > 0) {
    fwrite(STDERR, "FAILED: $failures\n");
    exit(1);
}
echo "ALL OK\n";
exit(0);
