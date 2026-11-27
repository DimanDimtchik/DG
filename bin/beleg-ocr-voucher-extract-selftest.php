<?php

declare(strict_types=1);

/**
 * BO3c — VoucherExtractService Soft-Fail + Schema ohne Live-Ollama.
 * Usage: php bin/beleg-ocr-voucher-extract-selftest.php
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

$apiSrc = (string) file_get_contents(DG_ROOT . '/src/Accounting/VoucherApi.php');
$assert(str_contains($apiSrc, "action === 'voucher_extract'"), 'VoucherApi has voucher_extract branch');
$assert(str_contains($apiSrc, 'handleVoucherExtract'), 'VoucherApi has handleVoucherExtract');
$assert(str_contains($apiSrc, 'VoucherExtractService::extract'), 'VoucherApi calls VoucherExtractService');
$assert(!str_contains($apiSrc, 'KichelApi'), 'voucher_extract not routed via KichelApi');

$ollamaSrc = (string) file_get_contents(DG_ROOT . '/src/Kichel/KichelOllamaClient.php');
$assert(str_contains($ollamaSrc, 'public static function generate'), 'KichelOllamaClient::generate exists');
$assert(str_contains($ollamaSrc, 'public static function configuredModel'), 'KichelOllamaClient::configuredModel exists');

$raw = <<<'TXT'
Muster GmbH
Rechnungsnr. RE-2024-100
Rechnungsdatum: 15.03.2024
2,00 Stück Widget Alpha 10,00 20,00
Gesamtbetrag EUR 23,80
TXT;

try {
    $empty = false;
    VoucherExtractService::extract(['raw_text' => '   ']);
} catch (InvalidArgumentException $e) {
    $empty = str_contains($e->getMessage(), 'Rohtext fehlt');
}
$assert($empty, 'empty raw_text throws');

$result = VoucherExtractService::extract([
    'raw_text' => $raw,
    'source' => 'pdf_text',
    'file_name' => 'muster.pdf',
]);
$extract = $result['extract'];
$meta = $result['meta'];

$assert(($extract['schema_version'] ?? 0) === 1, 'schema_version 1');
$assert(is_array($extract['lines'] ?? null), 'lines is array');
$assert(is_array($extract['warnings'] ?? null), 'warnings is array');
$assert(isset($extract['amount_check']['ok']), 'amount_check present');
$assert(($meta['soft_fail'] ?? false) === true || ($meta['ollama_used'] ?? false) === true, 'soft_fail or ollama_used');
if (!KichelOllamaClient::isEnabled()) {
    $assert(($meta['soft_fail'] ?? false) === true, 'soft_fail when ollama off');
    $assert(($meta['ollama_used'] ?? true) === false, 'ollama_used false when off');
    $assert(in_array($extract['source'], ['pdf_text', 'ocr', 'einvoice_xml'], true), 'source not kichel on soft fail');
}

$hint = [
    'schema_version' => 1,
    'source' => 'pdf_text',
    'supplier_name' => 'Hint Lieferant AG',
    'invoice_number' => 'H-9',
    'invoice_date' => '2024-01-10',
    'currency' => 'EUR',
    'gross_total' => '119.00',
    'net_total' => '100.00',
    'vat_total' => '19.00',
    'lines' => [
        [
            'line_no' => 1,
            'catalog_kind' => 'product',
            'title' => 'Hint Artikel',
            'quantity' => '1',
            'unit' => 'Stück',
            'unit_price_net' => '100.00',
            'line_net' => '100.00',
            'line_gross' => '119.00',
            'tax_percent' => 19,
            'confidence' => 0.9,
            'match_article_id' => null,
        ],
    ],
    'warnings' => [],
];
$withHint = VoucherExtractService::extract([
    'raw_text' => "nur rohtext ohne klare zeilen\n",
    'source' => 'ocr',
    'hint_extract' => json_encode($hint, JSON_THROW_ON_ERROR),
]);
$assert(($withHint['extract']['supplier_name'] ?? '') === 'Hint Lieferant AG', 'hint supplier kept on soft path');
$assert(count($withHint['extract']['lines']) === 1, 'hint lines kept');
$assert(($withHint['extract']['lines'][0]['title'] ?? '') === 'Hint Artikel', 'hint line title');

$parsed = VoucherExtractService::parseJsonObjectFromModel("Hier:\n```json\n{\"supplier_name\":\"X\",\"lines\":[]}\n```\n");
$assert(is_array($parsed) && ($parsed['supplier_name'] ?? '') === 'X', 'parseJsonObjectFromModel strips fence');

$badAmounts = VoucherExtractService::withAmountCheck([
    'net_total' => '50.00',
    'lines' => [
        ['line_net' => '10.00'],
        ['line_net' => '20.00'],
    ],
    'warnings' => [],
]);
$assert(($badAmounts['amount_check']['ok'] ?? true) === false, 'amount_check flags mismatch');
$assert($badAmounts['warnings'] !== [], 'amount mismatch adds warning');

$norm = VoucherExtractService::normalizeExtract([
    'invoice_date' => '15.03.2024',
    'lines' => [['title' => 'A', 'line_net' => '1.00']],
], 'pdf_text', true);
$assert(($norm['invoice_date'] ?? '') === '2024-03-15', 'german invoice_date → ISO');

$autoload = (string) file_get_contents(DG_ROOT . '/src/autoload.php');
$assert(str_contains($autoload, 'VoucherExtractService'), 'autoload registers VoucherExtractService');

if ($failed > 0) {
    fwrite(STDERR, "FAILED {$failed} assertion(s)\n");
    exit(1);
}
fwrite(STDOUT, "OK BO3c voucher_extract selftest\n");
exit(0);
