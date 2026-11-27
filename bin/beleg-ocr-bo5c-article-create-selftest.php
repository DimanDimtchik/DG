<?php

declare(strict_types=1);

/**
 * BO5c — Neuanlage product/service nur nach Klick; kein Auto-WE.
 * Usage: php bin/beleg-ocr-bo5c-article-create-selftest.php
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

$service = (string) file_get_contents(DG_ROOT . '/src/Accounting/VoucherArticleMatchService.php');
$api = (string) file_get_contents(DG_ROOT . '/src/Accounting/VoucherApi.php');
$import = (string) file_get_contents(DG_ROOT . '/assets/js/buchhaltung-import.mjs');
$view = (string) file_get_contents(DG_ROOT . '/views/modules/buchhaltung-beleg-form.php');

$assert(str_contains($service, 'function createFromExtractLine'), 'createFromExtractLine exists');
$assert(str_contains($service, "'track_stock' => 0"), 'create disables track_stock (no WE)');
$assert(str_contains($service, 'CalendarArticleRepository::save'), 'create uses repository save');
$assert(str_contains($api, "action === 'article_create'"), 'API article_create branch');
$assert(str_contains($api, 'handleArticleCreate'), 'API handler');
$assert(str_contains($api, 'VoucherArticleMatchService::createFromExtractLine'), 'API calls create');
$assert(str_contains($api, 'Csrf::verify'), 'create requires CSRF');
$assert(str_contains($api, 'RoleResolver::canEdit'), 'create requires edit role');

$assert(str_contains($import, 'createArticleFromExtract'), 'client create helper');
$assert(str_contains($import, 'Als Artikel anlegen'), 'product create button');
$assert(str_contains($import, 'Als Leistung anlegen'), 'service create button');
$assert(str_contains($import, 'action=article_create'), 'client posts article_create');
$assert(str_contains($import, 'kein Wareneingang'), 'status mentions no stock movement');
$assert(str_contains($view, 'Neuanlage ohne Lagerbuchung') || str_contains($view, 'ohne Lagerbuchung'), 'view hint no stock');
$assert(!preg_match('/BO5c|Gate G3|Stufe C/', $view), 'no plan codes in view');

// createFromExtractLine without DB must throw clearly
$threw = false;
try {
    VoucherArticleMatchService::createFromExtractLine(['title' => 'x', 'catalog_kind' => 'product']);
} catch (Throwable $e) {
    $threw = true;
    $assert(
        str_contains($e->getMessage(), 'Datenbank') || str_contains($e->getMessage(), 'konfiguriert'),
        'offline create fails without DB',
        $e->getMessage()
    );
}
$assert($threw, 'create throws without database');

$emptyTitle = false;
try {
    // Force past DB check by only validating title — if DB missing, still title check may not run first
    // Reflect: method checks DB first. Call with empty title after ensuring message path via InvalidArgument when DB on.
} catch (Throwable) {
}
// Title validation: unit-test the empty title path via reflection only if DB configured
if (Database::isConfigured()) {
    try {
        VoucherArticleMatchService::createFromExtractLine(['title' => '  ', 'catalog_kind' => 'service']);
    } catch (InvalidArgumentException $e) {
        $emptyTitle = str_contains($e->getMessage(), 'Bezeichnung');
    }
    $assert($emptyTitle, 'empty title rejected when DB on');
} else {
    $assert(true, 'skip empty-title DB test (no database)');
}

if ($failed > 0) {
    fwrite(STDERR, "FAILED {$failed} assertion(s)\n");
    exit(1);
}
fwrite(STDOUT, "OK BO5c article create selftest\n");
exit(0);
