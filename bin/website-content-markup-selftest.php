<?php
declare(strict_types=1);

define('DG_ROOT', dirname(__DIR__));
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

assertTrue(WebsiteContent::isSafeHref('https://a.de'), 'https ok');
assertTrue(WebsiteContent::isSafeHref('mailto:a@b.de'), 'mailto ok');
assertTrue(WebsiteContent::isSafeHref('/pfad'), 'rel ok');
assertTrue(!WebsiteContent::isSafeHref('//evil.de'), 'proto-rel blocked');
assertTrue(!WebsiteContent::isSafeHref('javascript:alert(1)'), 'js blocked');

$h = WebsiteContent::renderTextHtml('Hallo **Welt**');
assertTrue(str_contains($h, '<strong>Welt</strong>'), 'bold');
assertTrue(!str_contains($h, '**'), 'bold markers gone');

$h2 = WebsiteContent::renderTextHtml('Siehe [Start](/) und [Bad](javascript:x)');
assertTrue(str_contains($h2, 'href="/"'), 'safe link');
assertTrue(!str_contains($h2, 'javascript'), 'js link stripped to label');
assertTrue(str_contains($h2, 'Bad'), 'unsafe label kept as text');
assertTrue(!str_contains($h2, 'href="javascript'), 'no javascript href');

$h3 = WebsiteContent::renderTextHtml('<script>x</script> **ok**');
assertTrue(!str_contains($h3, '<script'), 'script stripped');
assertTrue(str_contains($h3, '<strong>ok</strong>'), 'bold after strip');

$layout = WebsiteContent::normalizeLayout([
    'rows' => [[
        'columns' => [[
            'blocks' => [[
                'type' => 'text',
                'text' => 'a',
                'quote' => 1,
            ]],
        ]],
    ]],
]);
assertTrue(($layout['rows'][0]['columns'][0]['blocks'][0]['quote'] ?? null) === true, 'quote bool');

assertTrue(WebsiteHeading::normalizeLevel('h4') === 'h4', 'heading h4');
assertTrue(WebsiteHeading::normalizeLevel('H7') === 'h2', 'heading fallback');
$hBlock = WebsiteHeading::normalizeBlock([
    'type' => 'heading',
    'level' => 'h3',
    'size_preset' => 'custom',
    'font_size' => '120',
    'font_size_unit' => '%',
]);
assertTrue(($hBlock['level'] ?? '') === 'h3', 'heading keep level');
assertTrue(str_contains(WebsiteHeading::fontSizeStyle($hBlock), '--ws-heading-base'), 'heading % size');
$hLarge = WebsiteHeading::fontSizeStyle(['size_preset' => 'large']);
assertTrue(str_contains($hLarge, '1.25'), 'heading large preset');

$layoutH = WebsiteContent::normalizeLayout([
    'rows' => [[
        'columns' => [[
            'blocks' => [[
                'type' => 'heading',
                'text' => 'Titel',
                'level' => 'h5',
                'size_preset' => 'small',
            ]],
        ]],
    ]],
]);
$nb = $layoutH['rows'][0]['columns'][0]['blocks'][0];
assertTrue(($nb['level'] ?? '') === 'h5', 'normalize heading level');
assertTrue(($nb['size_preset'] ?? '') === 'small', 'normalize size preset');

if ($failures > 0) {
    echo "FAILED: $failures\n";
    exit(1);
}
echo "ALL OK\n";
