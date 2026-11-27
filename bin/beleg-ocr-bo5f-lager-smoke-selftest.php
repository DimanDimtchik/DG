<?php

declare(strict_types=1);

/**
 * BO5f — Smoke Lager-Anbindung: Match + Neuanlage + EK + WE-Vorschlag verdrahtet.
 * Kein Live-DB-Zwang; UI-Smoke auf Dev nach Deploy.
 * Usage: php bin/beleg-ocr-bo5f-lager-smoke-selftest.php
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

$import = (string) file_get_contents(DG_ROOT . '/assets/js/buchhaltung-import.mjs');
$view = (string) file_get_contents(DG_ROOT . '/views/modules/buchhaltung-beleg-form.php');
$api = (string) file_get_contents(DG_ROOT . '/src/Accounting/VoucherApi.php');
$applyHelper = (string) file_get_contents(DG_ROOT . '/assets/js/vendor/dg-voucher-ocr-apply.mjs');

// --- Fixture: E-Rechnung mit Artikel + Leistung ---
$ciiPath = DG_ROOT . '/docs/testdata/beleg-ocr/cii-invoice.xml';
$assert(is_readable($ciiPath), 'CII fixture readable');
$cii = (string) file_get_contents($ciiPath);
$assert(str_contains($cii, 'SupplyChainTradeLineItem') || str_contains($cii, 'IncludedSupplyChainTradeLineItem'), 'CII has line items');

// OCR-Sample als zweiter Pfad (Regex-Extrakt ohne Ollama)
$ocrPath = DG_ROOT . '/docs/testdata/beleg-ocr/invoice-ocr-sample.txt';
$assert(is_readable($ocrPath), 'OCR fixture readable');
$ocrExtract = VoucherExtractService::extract([
    'raw_text' => (string) file_get_contents($ocrPath),
    'source' => 'ocr',
    'file_name' => 'invoice-ocr-sample.txt',
]);
$lines = $ocrExtract['extract']['lines'] ?? [];
$assert(count($lines) >= 2, 'OCR fixture >=2 lines', count($lines));
$kinds = array_column($lines, 'catalog_kind');
$assert(in_array('product', $kinds, true), 'product line in fixture', $kinds);
$assert(in_array('service', $kinds, true), 'service line in fixture', $kinds);

// --- Verdrahtung BO5b–e (1 Match + 1 Neuanlage + EK + WE) ---
$assert(str_contains($api, "action === 'article_match'"), 'API article_match');
$assert(str_contains($api, "action === 'article_create'"), 'API article_create');
$assert(str_contains($api, "action === 'article_purchase_source'"), 'API article_purchase_source');
$assert(str_contains($api, "action === 'stock_receipt_preview'"), 'API stock_receipt_preview');
$assert(str_contains($api, "action === 'stock_receipt'"), 'API stock_receipt');

$assert(str_contains($import, 'loadArticleMatches'), 'client match load');
$assert(str_contains($import, 'confirmArticleMatch'), 'client match confirm');
$assert(str_contains($import, 'Zuordnen'), 'match button visible');
$assert(str_contains($import, 'Als Artikel anlegen'), 'product create button');
$assert(str_contains($import, 'Als Leistung anlegen'), 'service create button');
$assert(str_contains($import, 'Einkaufspreis speichern'), 'EK save visible');
$assert(str_contains($import, 'data-ek-preferred'), 'EK preferred optional');
$assert(str_contains($import, 'Wareneingang buchen'), 'WE book visible');
$assert(str_contains($import, 'refreshReceiptProposal'), 'WE preview refresh');

$assert(str_contains($view, 'dg-voucher-extract-matches'), 'match panel in view');
$assert(str_contains($view, 'dg-voucher-extract-receipt'), 'WE panel in view');
$assert(str_contains($view, 'Einkaufspreis vom Beleg optional'), 'EK hint in view');
$assert(str_contains($view, 'Keine automatische Lagerbuchung'), 'no-auto WE hint');
$assert(!preg_match('/BO5[a-f]|Gate G3|Stufe C/', $view), 'no plan codes in view');

// Apply trägt match_article_id weiter (Erlös-Items)
$assert(str_contains($applyHelper, 'match_article_id'), 'apply helper keeps match_article_id');
$assert(str_contains($import, 'match_article_id'), 'import stores match_article_id');

// --- Smoke-Simulation: 1 Match + 1 ohne Match (Neuanlage-Kandidat) ---
$simLines = [
    [
        'line_no' => 1,
        'catalog_kind' => 'product',
        'title' => 'Schraube M6',
        'quantity' => '10',
        'unit' => 'Stück',
        'unit_price_net' => '0.50',
        'match_article_id' => 4242, // simuliert bestätigt
        'match_label' => 'A-4242 — Schraube M6',
    ],
    [
        'line_no' => 2,
        'catalog_kind' => 'service',
        'title' => 'Montage vor Ort',
        'quantity' => '1',
        'unit' => 'Stunde',
        'match_article_id' => null, // → Neuanlage-UI
    ],
];
$assert((int) $simLines[0]['match_article_id'] > 0, 'sim: product matched');
$assert($simLines[1]['match_article_id'] === null, 'sim: service unmatched → create path');

// EK-Preis aus Match-Zeile ableitbar (Client-Logik gespiegelt)
$unitNet = (float) $simLines[0]['unit_price_net'];
$assert($unitNet > 0, 'sim: EK unit price from extract', $unitNet);

// WE-Vorschlag offline: Match ohne DB → Artikel nicht gefunden → skipped (kein Auto-Book)
$proposal = StockReceiptIssueService::proposalFromExtractLines($simLines, 0);
$assert(($proposal['bookable'] ?? ['x']) === [], 'WE offline: no silent bookable without DB article', $proposal);
$assert(count($proposal['skipped'] ?? []) >= 1, 'WE offline: matched line skipped or explained', $proposal['skipped'] ?? null);
$assert(($proposal['already_booked'] ?? true) === false, 'WE offline: not already booked');

// Buchung ohne gespeicherten Beleg blockiert
$bookBlocked = false;
try {
    StockReceiptIssueService::bookExtractProposal($simLines, 0, null);
} catch (InvalidArgumentException $e) {
    $bookBlocked = str_contains($e->getMessage(), 'speichern');
}
$assert($bookBlocked, 'WE book blocked without voucher_id');

// GoBD: keine StockMovementService::syncForVoucher-Aufrufe in BO5-Handlern
foreach (['handleArticleCreate', 'handleArticlePurchaseSource', 'handleStockReceiptPreview', 'handleStockReceipt'] as $fn) {
    if (!preg_match('/function ' . preg_quote($fn, '/') . '\(\): void\s*\{([\s\S]*?)\n    (?:\/\*\*|private static function )/', $api, $m)) {
        $assert(false, "extract {$fn}");
        continue;
    }
    $slice = $m[1];
    $assert(
        !str_contains($slice, 'StockMovementService::syncForVoucher')
        && !preg_match('/(?<!Kein )syncForVoucher\s*\(/', $slice),
        "{$fn} no syncForVoucher call"
    );
}
$assert(str_contains($api, 'bookExtractProposal'), 'stock_receipt path uses bookExtractProposal');

// Untergeordnete Selftests (BO5b–e) müssen grün bleiben
$sub = [
    'beleg-ocr-bo5b-article-match-selftest.php',
    'beleg-ocr-bo5c-article-create-selftest.php',
    'beleg-ocr-bo5d-purchase-source-selftest.php',
    'beleg-ocr-bo5e-stock-receipt-selftest.php',
];
foreach ($sub as $script) {
    $path = DG_ROOT . '/bin/' . $script;
    $assert(is_readable($path), "subtest exists {$script}");
    $cmd = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($path) . ' 2>&1';
    $out = [];
    $code = 0;
    exec($cmd, $out, $code);
    $assert($code === 0, "subtest {$script} exit 0", $code !== 0 ? implode("\n", array_slice($out, -5)) : null);
}

if ($failed > 0) {
    fwrite(STDERR, "FAILED {$failed} assertion(s)\n");
    exit(1);
}
fwrite(STDOUT, "OK BO5f lager smoke selftest\n");
exit(0);
