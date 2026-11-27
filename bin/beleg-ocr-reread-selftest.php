<?php

declare(strict_types=1);

/**
 * BO1b/BO1c — „Erneut auslesen“ + Mehrseiten-OCR Scan-PDF.
 * Usage: php bin/beleg-ocr-reread-selftest.php
 */

if (!defined('DG_ROOT')) {
    define('DG_ROOT', dirname(__DIR__));
}

$view = (string) file_get_contents(DG_ROOT . '/views/modules/buchhaltung-beleg-form.php');
$js = (string) file_get_contents(DG_ROOT . '/assets/js/buchhaltung-import.mjs');
$fail = static function (string $msg): never {
    fwrite(STDERR, "FAIL: {$msg}\n");
    exit(1);
};

if (!str_contains($view, 'id="dg-voucher-extract-reread"')) {
    $fail('missing reread button id in beleg form');
}
if (!str_contains($view, 'id="dg-voucher-extract-reread-wrap"')) {
    $fail('missing reread wrap in beleg form');
}
if (!str_contains($view, 'Erneut auslesen')) {
    $fail('missing reread label in beleg form');
}
if (str_contains($view, 'BO1b') || str_contains($view, 'BO0')) {
    $fail('plan codes must not appear in end-user view');
}
if (!preg_match('/class="dg-button"[^>]*id="dg-voucher-extract-reread"|id="dg-voucher-extract-reread"[^>]*class="dg-button"/', $view)) {
    $fail('reread control must use .dg-button');
}

if (!str_contains($js, 'rereadStoredAttachment')) {
    $fail('missing rereadStoredAttachment in buchhaltung-import.mjs');
}
if (!str_contains($js, 'data-voucher-reread')) {
    $fail('missing per-file data-voucher-reread marker');
}
if (!str_contains($js, 'updateRereadToolbar')) {
    $fail('missing updateRereadToolbar');
}
if (!str_contains($js, 'latestAnalyzableStoredFile')) {
    $fail('missing latestAnalyzableStoredFile');
}

if (!str_contains($js, 'OCR_PDF_MAX_PAGES')) {
    $fail('missing OCR_PDF_MAX_PAGES (BO1c)');
}
if (!str_contains($js, 'ocrPdfPages')) {
    $fail('missing ocrPdfPages (BO1c)');
}
if (!preg_match('/OCR_PDF_MAX_PAGES\s*=\s*(\d+)/', $js, $m) || (int) $m[1] < 2) {
    $fail('OCR_PDF_MAX_PAGES must be >= 2');
}

fwrite(STDOUT, "OK BO1b/BO1c beleg OCR reread + multipage selftest\n");
exit(0);
