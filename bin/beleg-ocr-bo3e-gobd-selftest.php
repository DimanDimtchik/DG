<?php

declare(strict_types=1);

/**
 * BO3e — Fixture-Text → Extrakt; GoBD: kein stilles Buchen/Lager; Soft-Fail ok.
 * Usage: php bin/beleg-ocr-bo3e-gobd-selftest.php
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

$fixturePath = DG_ROOT . '/docs/testdata/beleg-ocr/invoice-ocr-sample.txt';
$assert(is_readable($fixturePath), 'fixture readable', $fixturePath);
$raw = (string) file_get_contents($fixturePath);
$assert($raw !== '', 'fixture non-empty');

$result = VoucherExtractService::extract([
    'raw_text' => $raw,
    'source' => 'ocr',
    'file_name' => 'invoice-ocr-sample.txt',
    'voucher_id' => 999001,
]);
$extract = $result['extract'] ?? [];
$meta = $result['meta'] ?? [];

$assert(($extract['schema_version'] ?? 0) === 1, 'schema_version 1');
$assert(($extract['supplier_name'] ?? '') !== '', 'supplier from fixture', $extract['supplier_name'] ?? null);
$assert(($extract['invoice_number'] ?? '') === 'RE-BO3E-42', 'invoice_number', $extract['invoice_number'] ?? null);
$assert(($extract['invoice_date'] ?? '') === '2024-03-15', 'invoice_date ISO', $extract['invoice_date'] ?? null);
$assert(($extract['gross_total'] ?? '') === '77.35', 'gross_total', $extract['gross_total'] ?? null);
$assert(is_array($extract['lines'] ?? null) && count($extract['lines']) >= 2, '>=2 lines from fixture', count($extract['lines'] ?? []));
$kinds = array_column($extract['lines'], 'catalog_kind');
$assert(in_array('product', $kinds, true), 'product line present', $kinds);
$assert(in_array('service', $kinds, true), 'service line present', $kinds);
$assert(isset($extract['amount_check']['ok']), 'amount_check present');
$assert(($meta['ollama_used'] ?? true) === false || ($meta['soft_fail'] ?? false) === true || ($meta['ollama_used'] ?? false) === true, 'meta path coherent');

// Soft-Fail ohne Ollama ist erwarteter Smoke-Pfad
if (!KichelOllamaClient::isEnabled()) {
    $assert(($meta['soft_fail'] ?? false) === true, 'soft_fail when ollama off');
    $assert(($meta['ollama_used'] ?? true) === false, 'no ollama_used when off');
}

$serviceSrc = (string) file_get_contents(DG_ROOT . '/src/Accounting/VoucherExtractService.php');
$apiSrc = (string) file_get_contents(DG_ROOT . '/src/Accounting/VoucherApi.php');
$clientSrc = (string) file_get_contents(DG_ROOT . '/assets/js/buchhaltung-import.mjs');

$forbiddenService = [
    'JournalService',
    'LedgerService',
    'StockService',
    'StockMovement',
    'VoucherRepository',
    '::book',
    'INSERT INTO',
    'UPDATE ',
    'Db::',
];
foreach ($forbiddenService as $needle) {
    $assert(!str_contains($serviceSrc, $needle), "extract service has no {$needle}");
}

// API-Zweig: nur extract + json_encode, kein Speichern im Handler
$handlerPos = strpos($apiSrc, 'function handleVoucherExtract');
$uploadPos = strpos($apiSrc, 'function handleFileUpload', $handlerPos !== false ? $handlerPos : 0);
$assert($handlerPos !== false && $uploadPos !== false && $uploadPos > $handlerPos, 'handleVoucherExtract before file_upload');
$handlerSlice = substr($apiSrc, $handlerPos, $uploadPos - $handlerPos);
foreach (['VoucherFileStorage', 'Journal', 'Stock', 'Db::', 'INSERT', 'UPDATE'] as $needle) {
    $assert(!str_contains($handlerSlice, $needle), "API extract handler has no {$needle}");
}
$assert(str_contains($handlerSlice, 'VoucherExtractService::extract'), 'API calls extract only');
$assert(str_contains($handlerSlice, "json_encode([\n                'success' => true"), 'API returns JSON success');

$assert(str_contains($serviceSrc, 'Keine Buchungs- oder Kontierungsvorschläge'), 'structure prompt bans booking');
$assert(str_contains($serviceSrc, 'KichelGobdTone::isUnsafe'), 'extract rejects unsafe model tone');
$assert(str_contains($serviceSrc, 'Kein stilles Speichern/Buchen/Lager'), 'service header states no silent book/stock');

// GoBD: unsichere Modellantwort → Soft-Fail (Simulation ohne Live-Ollama)
$unsafe = 'Das kannst du einfach auf Konto 8400 buchen.';
$assert(KichelGobdTone::isUnsafe($unsafe), 'GobdTone detects booking advice');

// Client: Assistenz-Aufruf bucht nicht; Apply nur per Button
$assert(str_contains($clientSrc, 'enrichWithAssistenz'), 'client has assistenz enrich');
$assert(str_contains($clientSrc, "action=voucher_extract"), 'client calls voucher_extract');
$enrichStart = strpos($clientSrc, 'async function enrichWithAssistenz');
$enrichEnd = strpos($clientSrc, 'async function finalizeSuggestion');
$assert($enrichStart !== false && $enrichEnd !== false, 'enrich + finalize markers');
$enrichSlice = substr($clientSrc, $enrichStart, $enrichEnd - $enrichStart);
$assert(!str_contains($enrichSlice, 'applySuggestion'), 'enrich does not auto-apply');
$assert(str_contains($clientSrc, "addEventListener('click', applySuggestion)"), 'apply only on button click');

// voucher_id darf Meta sein, aber Service speichert nicht
$assert(
    !preg_match('/voucher_id[^\n]{0,80}(save|update|insert)/i', $serviceSrc),
    'voucher_id not used for persistence in service'
);

if ($failed > 0) {
    fwrite(STDERR, "FAILED {$failed} assertion(s)\n");
    exit(1);
}
fwrite(STDOUT, "OK BO3e fixture+GoBD voucher_extract selftest\n");
exit(0);
