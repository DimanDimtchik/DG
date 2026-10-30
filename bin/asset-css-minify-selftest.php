<?php
declare(strict_types=1);

/**
 * Guard: CRM lädt dg.app.min.css (nicht Offline-Stub), Minify existiert und ist kleiner.
 */
if (!defined('DG_ROOT')) {
    define('DG_ROOT', dirname(__DIR__));
}
require_once DG_ROOT . '/src/autoload.php';

function assertCss(bool $ok, string $msg): void
{
    if (!$ok) {
        fwrite(STDERR, "FEHLER: {$msg}\n");
        exit(1);
    }
    echo "OK: {$msg}\n";
}

$src = DG_ROOT . '/assets/css/dg.css';
$min = DG_ROOT . '/assets/css/dg.app.min.css';
$stub = DG_ROOT . '/assets/css/dg.min.css';

assertCss(is_readable($src), 'dg.css vorhanden');
assertCss(is_readable($min), 'dg.app.min.css vorhanden');
assertCss(is_readable($stub), 'dg.min.css Offline-Stub vorhanden');

$srcSize = filesize($src);
$minSize = filesize($min);
$stubSize = filesize($stub);
assertCss($srcSize > 50000, 'dg.css groß (App)');
assertCss($minSize > 10000 && $minSize < $srcSize, 'dg.app.min.css kleiner als Quelle');
assertCss($stubSize < 5000, 'dg.min.css bleibt Offline-Stub (klein)');

$minBody = (string) file_get_contents($min);
assertCss(str_contains($minBody, '--dg-space-4') || str_contains($minBody, '--dg-space-4:'), 'Min enthält Token');
assertCss(str_contains($minBody, 'dg-button--primary'), 'Min enthält Button-Primary');
assertCss(!str_contains($minBody, 'body.dg-offline'), 'App-Min ist nicht Offline-Stub');

$stubBody = (string) file_get_contents($stub);
assertCss(str_contains($stubBody, 'dg-offline'), 'Stub bleibt Offline');

putenv('DG_CSS_SOURCE');
$url = Asset::crmStylesheetUrl();
assertCss(str_contains($url, 'dg.app.min.css'), 'crmStylesheetUrl → app.min (mtime ok)');

putenv('DG_CSS_SOURCE=1');
$urlSrc = Asset::crmStylesheetUrl();
assertCss(str_contains($urlSrc, 'dg.css') && !str_contains($urlSrc, 'dg.app.min.css'), 'DG_CSS_SOURCE=1 → dg.css');
putenv('DG_CSS_SOURCE');

$head = (string) file_get_contents(DG_ROOT . '/views/partials/head.php');
assertCss(str_contains($head, 'crmStylesheetUrl()'), 'head nutzt crmStylesheetUrl');
assertCss(!str_contains($head, "Asset::url('/assets/css/dg.css')"), 'head nicht hardcodiert auf dg.css');

echo "Asset-CSS-Minify Selftest bestanden.\n";
