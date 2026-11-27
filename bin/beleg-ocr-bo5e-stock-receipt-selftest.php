<?php

declare(strict_types=1);

/**
 * BO5e — Wareneingang-Vorschlag + Klick-Buchung; kein Auto-syncForVoucher.
 * Usage: php bin/beleg-ocr-bo5e-stock-receipt-selftest.php
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

$service = (string) file_get_contents(DG_ROOT . '/src/Inventory/StockReceiptIssueService.php');
$api = (string) file_get_contents(DG_ROOT . '/src/Accounting/VoucherApi.php');
$import = (string) file_get_contents(DG_ROOT . '/assets/js/buchhaltung-import.mjs');
$view = (string) file_get_contents(DG_ROOT . '/views/modules/buchhaltung-beleg-form.php');

$assert(str_contains($service, 'function proposalFromExtractLines'), 'proposalFromExtractLines exists');
$assert(str_contains($service, 'function bookExtractProposal'), 'bookExtractProposal exists');
$assert(str_contains($service, 'function hasReceiptMovementsForVoucher'), 'already-booked guard');
$assert(str_contains($service, 'processReceipt'), 'books via processReceipt');
$assert(str_contains($service, 'Beleg zuerst speichern'), 'requires saved voucher');

$assert(str_contains($api, "action === 'stock_receipt_preview'"), 'API preview branch');
$assert(str_contains($api, "action === 'stock_receipt'"), 'API book branch');
$assert(str_contains($api, 'handleStockReceiptPreview'), 'preview handler');
$assert(str_contains($api, 'handleStockReceipt'), 'book handler');
$assert(str_contains($api, 'StockReceiptIssueService::bookExtractProposal'), 'API calls book');
$assert(str_contains($api, 'StockReceiptIssueService::proposalFromExtractLines'), 'API calls proposal');

// Book handler must require CSRF / edit and must NOT call syncForVoucher
$handler = '';
if (preg_match(
    '/function handleStockReceipt\(\): void\s*\{([\s\S]*?)\n    private static function handleArticlePurchaseSource/',
    $api,
    $m
)) {
    $handler = $m[1];
}
$assert($handler !== '', 'extracted stock_receipt handler');
$assert(str_contains($handler, 'Csrf::verify'), 'book requires CSRF');
$assert(str_contains($handler, 'RoleResolver::canEdit'), 'book requires edit role');
$assert(!str_contains($handler, 'syncForVoucher'), 'handler has no syncForVoucher');
$assert(!str_contains($handler, 'StockMovementService'), 'handler has no StockMovementService');

$assert(str_contains($import, 'refreshReceiptProposal'), 'client preview helper');
$assert(str_contains($import, 'bookReceiptFromExtract'), 'client book helper');
$assert(str_contains($import, 'action=stock_receipt_preview'), 'client calls preview');
$assert(str_contains($import, 'action=stock_receipt'), 'client posts stock_receipt');
$assert(str_contains($import, 'Wareneingang buchen'), 'book button label');
$assert(str_contains($import, 'Beleg zuerst speichern'), 'client requires saved voucher');

$assert(str_contains($view, 'dg-voucher-extract-receipt'), 'receipt panel in view');
$assert(str_contains($view, 'Wareneingang-Vorschlag'), 'receipt heading');
$assert(str_contains($view, 'Keine automatische Lagerbuchung'), 'no-auto hint');
$assert(!preg_match('/BO5e|Gate G3|Stufe C/', $view), 'no plan codes in view');

// Offline proposal: unmatched line → skipped
$proposal = StockReceiptIssueService::proposalFromExtractLines([
    ['line_no' => 1, 'title' => 'Schraube M6', 'quantity' => '2', 'match_article_id' => null],
    ['line_no' => 2, 'title' => 'Beratung', 'quantity' => '1', 'match_article_id' => 0],
], 0);
$assert(($proposal['bookable'] ?? null) === [], 'offline unmatched → no bookable', $proposal);
$assert(count($proposal['skipped'] ?? []) >= 1, 'offline unmatched → skipped', $proposal['skipped'] ?? null);
$assert(($proposal['already_booked'] ?? true) === false, 'offline already_booked false');

// book without voucher id
$threw = false;
try {
    StockReceiptIssueService::bookExtractProposal(
        [['title' => 'x', 'match_article_id' => 1, 'quantity' => 1]],
        0,
        null
    );
} catch (InvalidArgumentException $e) {
    $threw = true;
    $assert(str_contains($e->getMessage(), 'speichern'), 'book rejects voucher_id 0', $e->getMessage());
} catch (Throwable $e) {
    $threw = true;
    $assert(false, 'unexpected throwable', $e->getMessage());
}
$assert($threw, 'book throws without voucher');

if ($failed > 0) {
    fwrite(STDERR, "FAILED {$failed} assertion(s)\n");
    exit(1);
}
fwrite(STDOUT, "OK BO5e stock receipt selftest\n");
exit(0);
