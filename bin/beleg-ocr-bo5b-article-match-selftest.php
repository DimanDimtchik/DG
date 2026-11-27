<?php

declare(strict_types=1);

/**
 * BO5b — Katalog-Match-Vorschläge (API/UI verdrahtet, kein Auto-Save).
 * Usage: php bin/beleg-ocr-bo5b-article-match-selftest.php
 */

if (!defined('DG_ROOT')) {
    define('DG_ROOT', dirname(__DIR__));
}
require_once DG_ROOT . '/src/autoload.php';

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

$api = (string) file_get_contents(DG_ROOT . '/src/Accounting/VoucherApi.php');
$service = (string) file_get_contents(DG_ROOT . '/src/Accounting/VoucherArticleMatchService.php');
$import = (string) file_get_contents(DG_ROOT . '/assets/js/buchhaltung-import.mjs');
$view = (string) file_get_contents(DG_ROOT . '/views/modules/buchhaltung-beleg-form.php');
$autoload = (string) file_get_contents(DG_ROOT . '/src/autoload.php');
$css = (string) file_get_contents(DG_ROOT . '/assets/css/dg.css');
$belege = (string) file_get_contents(DG_ROOT . '/assets/js/buchhaltung-belege.js');

$assert(str_contains($autoload, 'VoucherArticleMatchService'), 'autoload registers match service');
$assert(str_contains($api, "action === 'article_match'"), 'VoucherApi article_match branch');
$assert(str_contains($api, 'VoucherArticleMatchService::candidatesForLines'), 'API uses candidatesForLines');
$assert(str_contains($service, 'findIdByArticleNumber'), 'match by article number');
$assert(str_contains($service, 'findIdByTitle'), 'match by title');
$assert(str_contains($service, 'searchArticles'), 'match falls back to search');
$assert(!str_contains($service, 'CalendarArticleRepository::save'), 'match service does not save articles');
$assert(!str_contains($service, 'StockMovement'), 'match service does not touch stock');

$assert(str_contains($view, 'id="dg-voucher-extract-matches"'), 'matches panel in view');
$assert(str_contains($view, 'Katalog-Vorschläge'), 'matches heading');
$assert(!preg_match('/BO5b|Gate G3|Stufe C/', $view), 'no plan codes in view');

$assert(str_contains($import, 'loadArticleMatches'), 'client loads matches');
$assert(str_contains($import, 'confirmArticleMatch'), 'client confirm handler');
$assert(str_contains($import, 'action=article_match'), 'client calls article_match');
$assert(str_contains($import, 'Zuordnen'), 'confirm button label');
$assert(str_contains($import, 'match_article_id'), 'stores match_article_id');
$assert(str_contains($belege, 'match_article_id'), 'apply writes article id when matched');

$assert(str_contains($css, '.dg-voucher-extract__matches'), 'CSS for matches panel');
$assert(str_contains($import, 'dg-button'), 'uses dg-button for actions');

// Ohne DB: leere Kandidaten, kein Crash
$empty = VoucherArticleMatchService::candidatesForLine([
    'title' => 'Kabeltrommel 25m',
    'sku' => 'ART-9',
    'catalog_kind' => 'product',
], 5);
$assert(is_array($empty), 'candidatesForLine returns array offline');

$batch = VoucherArticleMatchService::candidatesForLines([
    ['line_no' => 1, 'title' => 'Kabeltrommel 25m', 'sku' => 'ART-9', 'catalog_kind' => 'product'],
    ['line_no' => 2, 'title' => 'Montage vor Ort', 'catalog_kind' => 'service'],
], 5);
$assert(count($batch) === 2, 'candidatesForLines keeps two titled lines', count($batch));
$assert(($batch[0]['title'] ?? '') === 'Kabeltrommel 25m', 'batch line title');

if ($failed > 0) {
    fwrite(STDERR, "FAILED {$failed} assertion(s)\n");
    exit(1);
}
fwrite(STDOUT, "OK BO5b article match selftest\n");
exit(0);
