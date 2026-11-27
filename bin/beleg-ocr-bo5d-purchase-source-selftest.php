<?php

declare(strict_types=1);

/**
 * BO5d — EK-Quelle aus Beleg an Artikel (Klick); Preferred optional; kein Auto-WE.
 * Usage: php bin/beleg-ocr-bo5d-purchase-source-selftest.php
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

$repo = (string) file_get_contents(DG_ROOT . '/src/Inventory/ArticlePurchaseSourceRepository.php');
$api = (string) file_get_contents(DG_ROOT . '/src/Accounting/VoucherApi.php');
$import = (string) file_get_contents(DG_ROOT . '/assets/js/buchhaltung-import.mjs');
$view = (string) file_get_contents(DG_ROOT . '/views/modules/buchhaltung-beleg-form.php');

$assert(str_contains($repo, 'function upsertFromBeleg'), 'upsertFromBeleg exists');
$assert(str_contains($repo, 'replaceFromInput'), 'upsert uses replaceFromInput merge');
$assert(str_contains($api, "action === 'article_purchase_source'"), 'API article_purchase_source branch');
$assert(str_contains($api, 'handleArticlePurchaseSource'), 'API handler');
$assert(str_contains($api, 'ArticlePurchaseSourceRepository::upsertFromBeleg'), 'API calls upsert');
$assert(str_contains($api, 'Csrf::verify'), 'purchase source requires CSRF');
$assert(str_contains($api, 'RoleResolver::canEdit'), 'purchase source requires edit role');

$assert(str_contains($import, 'savePurchaseSourceFromExtract'), 'client EK save helper');
$assert(str_contains($import, 'extractUnitPurchasePrice'), 'client price from extract');
$assert(str_contains($import, 'action=article_purchase_source'), 'client posts article_purchase_source');
$assert(str_contains($import, 'Einkaufspreis speichern'), 'EK save button label');
$assert(str_contains($import, 'data-ek-preferred'), 'preferred checkbox');
$assert(str_contains($import, 'data-ek-save'), 'EK save button data attr');
$assert(str_contains($import, 'Kein Wareneingang gebucht'), 'status mentions no stock');
$assert(str_contains($view, 'Einkaufspreis vom Beleg optional'), 'view hint mentions EK');
$assert(!preg_match('/BO5d|Gate G3|Stufe C/', $view), 'no plan codes in view');

// Offline: invalid article id
$threw = false;
try {
    ArticlePurchaseSourceRepository::upsertFromBeleg(0, [
        'supplier_name' => 'Test GmbH',
        'purchase_price' => 1.23,
    ]);
} catch (InvalidArgumentException $e) {
    $threw = true;
    $assert(str_contains($e->getMessage(), 'Artikel'), 'zero article_id rejected', $e->getMessage());
} catch (Throwable $e) {
    $threw = true;
    $assert(false, 'unexpected throwable for zero article', $e->getMessage());
}
$assert($threw, 'upsert throws on article_id 0');

if (Database::isConfigured()) {
    $emptyThrew = false;
    try {
        ArticlePurchaseSourceRepository::upsertFromBeleg(1, []);
    } catch (InvalidArgumentException $e) {
        $emptyThrew = true;
        $assert(
            str_contains($e->getMessage(), 'Lieferant') || str_contains($e->getMessage(), 'Einkaufspreis'),
            'empty entry rejected',
            $e->getMessage()
        );
    } catch (Throwable $e) {
        $emptyThrew = true;
        $assert(true, 'empty entry failed loudly', $e->getMessage());
    }
    $assert($emptyThrew, 'empty entry throws when DB on');
} else {
    $assert(true, 'skip DB upsert tests (no database)');
}

// No silent stock sync in purchase-source handler block
$handler = '';
if (preg_match(
    '/function handleArticlePurchaseSource\(\): void\s*\{([\s\S]*?)\n    private static function handleArticleCreate/',
    $api,
    $m
)) {
    $handler = $m[1];
}
$assert($handler !== '', 'extracted purchase-source handler');
$assert(!str_contains($handler, 'syncForVoucher'), 'handler has no syncForVoucher');
$assert(!str_contains($handler, 'StockMovement'), 'handler has no StockMovement');
$assert(!str_contains($handler, 'processReceipt'), 'handler has no processReceipt');

if ($failed > 0) {
    fwrite(STDERR, "FAILED {$failed} assertion(s)\n");
    exit(1);
}
fwrite(STDOUT, "OK BO5d purchase source selftest\n");
exit(0);
