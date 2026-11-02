<?php
declare(strict_types=1);

define('DG_ROOT', dirname(__DIR__));
require_once DG_ROOT . '/src/autoload.php';

function assertList(bool $ok, string $msg): void
{
    if (!$ok) {
        fwrite(STDERR, "FAIL: $msg\n");
        exit(1);
    }
    echo "OK: $msg\n";
}

assertList(WebsiteListMarkers::normalize('ul', 'symbol:heart') === 'symbol:heart', 'heart marker');
assertList(WebsiteListMarkers::normalize('ol', 'symbol:heart') === 'decimal', 'ol rejects symbol');
assertList(WebsiteListMarkers::normalize('ul', 'bogus') === 'disc', 'ul fallback');

$sym = WebsiteListMarkers::presentation('ul', 'symbol:diamond', '');
assertList(str_contains($sym['class'], 'ws-list--m-diamond'), 'diamond class');
assertList($sym['style'] === '', 'symbol no inline style');

$img = WebsiteListMarkers::presentation('ul', 'image', '/app/media?id=20261101161244_ec383d');
assertList(str_contains($img['class'], 'm-image'), 'image marker class');
assertList(str_contains($img['style'], 'url('), 'image css var');

$emptyImg = WebsiteListMarkers::presentation('ul', 'image', '');
assertList(str_contains($emptyImg['style'], 'disc'), 'empty image falls back disc');

echo "website-list-markers-selftest: all OK\n";
