<?php
declare(strict_types=1);

/**
 * BO3c — Beleg-Rohtext → BO2-Extrakt (optional Ollama strukturieren).
 * Kein stilles Speichern/Buchen/Lager. Soft-Fail wenn Modell aus/fehlerhaft.
 */
final class VoucherExtractService
{
    public const MAX_RAW_CHARS = 100000;

    /**
     * @param array{
     *   raw_text?: string,
     *   source?: string,
     *   voucher_id?: int,
     *   hint_extract?: string|array<string, mixed>|null,
     *   file_name?: string
     * } $input
     * @return array{extract: array<string, mixed>, meta: array<string, mixed>}
     */
    public static function extract(array $input): array
    {
        $raw = trim((string) ($input['raw_text'] ?? ''));
        if ($raw === '') {
            throw new InvalidArgumentException('Rohtext fehlt.');
        }
        if (mb_strlen($raw) > self::MAX_RAW_CHARS) {
            throw new InvalidArgumentException('Rohtext zu lang (max. ' . self::MAX_RAW_CHARS . ' Zeichen).');
        }

        $source = self::normalizeSource((string) ($input['source'] ?? 'pdf_text'));
        $fileName = trim((string) ($input['file_name'] ?? ''));
        $hints = self::normalizeExtract(
            self::decodeHintExtract($input['hint_extract'] ?? null),
            $source,
            true
        );
        if ($hints['lines'] === [] && $hints['supplier_name'] === '' && $hints['invoice_number'] === '') {
            $hints = self::normalizeExtract(self::fallbackExtractFromRaw($raw, $source), $source, true);
        }

        $meta = [
            'ollama_used' => false,
            'soft_fail' => false,
            'soft_fail_reason' => '',
            'model' => '',
        ];

        if (!KichelOllamaClient::isEnabled()) {
            $meta['soft_fail'] = true;
            $meta['soft_fail_reason'] = 'Ollama ist ausgeschaltet — Text-Hinweise behalten.';
            $extract = $hints;
            $extract['source'] = $source === 'ocr' ? 'ocr' : ($source === 'einvoice_xml' ? 'einvoice_xml' : 'pdf_text');
            if ($extract['warnings'] === []) {
                $extract['warnings'][] = $meta['soft_fail_reason'];
            }

            return ['extract' => self::withAmountCheck($extract), 'meta' => $meta];
        }

        $model = KichelOllamaClient::configuredModel();
        $meta['model'] = $model;
        $prompt = self::buildStructurePrompt($raw, $hints, $fileName);
        $rawAnswer = KichelOllamaClient::generate($prompt);
        if ($rawAnswer === null || $rawAnswer === '') {
            $meta['soft_fail'] = true;
            $meta['soft_fail_reason'] = 'Assistenz lieferte keine Antwort — Text-Hinweise behalten.';
            $extract = $hints;
            $extract['warnings'][] = $meta['soft_fail_reason'];

            return ['extract' => self::withAmountCheck($extract), 'meta' => $meta];
        }

        if (KichelGobdTone::isUnsafe($rawAnswer)) {
            $meta['soft_fail'] = true;
            $meta['soft_fail_reason'] = 'Antwort verworfen (GoBD-Ton) — Text-Hinweise behalten.';
            $extract = $hints;
            $extract['warnings'][] = $meta['soft_fail_reason'];

            return ['extract' => self::withAmountCheck($extract), 'meta' => $meta];
        }

        $parsed = self::parseJsonObjectFromModel($rawAnswer);
        if ($parsed === null) {
            $meta['soft_fail'] = true;
            $meta['soft_fail_reason'] = 'Kein gültiges JSON in der Assistenz-Antwort — Text-Hinweise behalten.';
            $extract = $hints;
            $extract['warnings'][] = $meta['soft_fail_reason'];

            return ['extract' => self::withAmountCheck($extract), 'meta' => $meta];
        }

        $fromModel = self::normalizeExtract($parsed, 'kichel', false);
        $merged = self::mergePreferringHintsOnConflict($fromModel, $hints);
        $merged['source'] = 'kichel';
        $meta['ollama_used'] = true;

        return ['extract' => self::withAmountCheck($merged), 'meta' => $meta];
    }

    /**
     * @param mixed $hint
     * @return array<string, mixed>
     */
    public static function decodeHintExtract(mixed $hint): array
    {
        if (is_array($hint)) {
            return $hint;
        }
        if (!is_string($hint) || trim($hint) === '') {
            return [];
        }
        $decoded = json_decode($hint, true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @param array<string, mixed> $raw
     * @return array<string, mixed>
     */
    public static function normalizeExtract(array $raw, string $source, bool $fromHints): array
    {
        $linesIn = $raw['lines'] ?? [];
        if (!is_array($linesIn)) {
            $linesIn = [];
        }
        $lines = [];
        $lineNo = 0;
        foreach ($linesIn as $row) {
            if (!is_array($row)) {
                continue;
            }
            $title = trim((string) ($row['title'] ?? $row['description'] ?? ''));
            if ($title === '') {
                continue;
            }
            $lineNo++;
            $kind = strtolower(trim((string) ($row['catalog_kind'] ?? 'service')));
            if ($kind !== 'product' && $kind !== 'service') {
                $kind = 'service';
            }
            $tax = (int) round((float) str_replace(',', '.', (string) ($row['tax_percent'] ?? 19)));
            if (!in_array($tax, [0, 7, 19], true)) {
                $tax = 19;
            }
            $lines[] = [
                'line_no' => $lineNo,
                'catalog_kind' => $kind,
                'sku' => trim((string) ($row['sku'] ?? '')),
                'title' => $title,
                'quantity' => self::qtyString($row['quantity'] ?? '1'),
                'unit' => trim((string) ($row['unit'] ?? 'Stück')) ?: 'Stück',
                'unit_price_net' => self::moneyString($row['unit_price_net'] ?? ''),
                'line_net' => self::moneyString($row['line_net'] ?? $row['amount'] ?? ''),
                'line_gross' => self::moneyString($row['line_gross'] ?? ''),
                'tax_percent' => $tax,
                'confidence' => isset($row['confidence']) ? (float) $row['confidence'] : ($fromHints ? 0.45 : 0.7),
                'match_article_id' => null,
            ];
        }

        $warnings = [];
        if (isset($raw['warnings']) && is_array($raw['warnings'])) {
            foreach ($raw['warnings'] as $w) {
                $w = trim((string) $w);
                if ($w !== '') {
                    $warnings[] = $w;
                }
            }
        }

        return [
            'schema_version' => 1,
            'source' => $source,
            'supplier_name' => trim((string) ($raw['supplier_name'] ?? $raw['supplier'] ?? '')),
            'invoice_number' => trim((string) ($raw['invoice_number'] ?? $raw['invoiceNumber'] ?? '')),
            'invoice_date' => self::isoDate((string) ($raw['invoice_date'] ?? $raw['invoiceDate'] ?? '')),
            'currency' => trim((string) ($raw['currency'] ?? 'EUR')) ?: 'EUR',
            'gross_total' => self::moneyString($raw['gross_total'] ?? $raw['grossAmount'] ?? ''),
            'net_total' => self::moneyString($raw['net_total'] ?? $raw['netAmount'] ?? ''),
            'vat_total' => self::moneyString($raw['vat_total'] ?? $raw['vatAmount'] ?? ''),
            'tax_rates' => is_array($raw['tax_rates'] ?? null) ? $raw['tax_rates'] : [],
            'iban' => preg_replace('/\s+/', '', strtoupper(trim((string) ($raw['iban'] ?? '')))) ?: '',
            'bic' => trim((string) ($raw['bic'] ?? '')),
            'vat_id' => trim((string) ($raw['vat_id'] ?? $raw['vatId'] ?? '')),
            'tax_number' => trim((string) ($raw['tax_number'] ?? $raw['taxNumber'] ?? '')),
            'lines' => $lines,
            'warnings' => $warnings,
            'amount_check' => ['ok' => true, 'detail' => ''],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function fallbackExtractFromRaw(string $raw, string $source): array
    {
        $lines = preg_split('/\R/u', $raw) ?: [];
        $outLines = [];
        $lineNo = 0;
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            if (preg_match('/^(\d+[,.]\d+)\s*Stück\s+(.+?)\s+(\d+[,.]\d{2})\s+(\d+[,.]\d{2})/iu', $line, $m)) {
                $lineNo++;
                $qty = self::moneyString($m[1]);
                $title = trim($m[2]);
                $unitPrice = self::moneyString($m[3]);
                $lineNet = self::moneyString($m[4]);
                $outLines[] = [
                    'line_no' => $lineNo,
                    'catalog_kind' => preg_match('/dienst|montage|beratung|stunde/iu', $title) ? 'service' : 'product',
                    'sku' => '',
                    'title' => $title,
                    'quantity' => $qty !== '' ? (string) (float) str_replace(',', '.', $qty) : '1',
                    'unit' => 'Stück',
                    'unit_price_net' => $unitPrice,
                    'line_net' => $lineNet,
                    'line_gross' => '',
                    'tax_percent' => 19,
                    'confidence' => 0.5,
                    'match_article_id' => null,
                ];
            }
        }

        $invoiceNumber = '';
        if (preg_match('~(?:rechnungs?-?\s*nr\.?|rechnungsnummer)[-:\s]+([A-Z0-9][A-Z0-9./-]{2,})~iu', $raw, $m)) {
            $invoiceNumber = $m[1];
        }
        $invoiceDate = '';
        if (preg_match('~(?:rechnungsdatum|belegdatum)[-:\s]+(\d{1,2}[./-]\d{1,2}[./-]\d{2,4})~iu', $raw, $m)) {
            $invoiceDate = self::isoDate($m[1]);
        }
        $gross = '';
        if (preg_match('~(?:gesamtbetrag|rechnungsbetrag|zu\s*zahlen)[-:\s]*(?:eur|€)?\s*(\d{1,3}(?:\.\d{3})*,\d{2}|\d+[,.]\d{2})~iu', $raw, $m)) {
            $gross = self::moneyString($m[1]);
        }
        $supplier = '';
        foreach ($lines as $line) {
            $t = trim($line);
            if ($t !== '' && !preg_match('/rechnung|datum|iban|gesamt|netto|mwst|stück/iu', $t) && mb_strlen($t) >= 3 && mb_strlen($t) <= 80) {
                $supplier = $t;
                break;
            }
        }

        return [
            'schema_version' => 1,
            'source' => $source === 'ocr' ? 'ocr' : 'pdf_text',
            'supplier_name' => $supplier,
            'invoice_number' => $invoiceNumber,
            'invoice_date' => $invoiceDate,
            'currency' => 'EUR',
            'gross_total' => $gross,
            'net_total' => '',
            'vat_total' => '',
            'tax_rates' => [],
            'iban' => '',
            'bic' => '',
            'vat_id' => '',
            'tax_number' => '',
            'lines' => $outLines,
            'warnings' => $outLines === [] ? ['Keine Positionszeilen per Text erkannt.'] : [],
            'amount_check' => ['ok' => true, 'detail' => ''],
        ];
    }

    /**
     * @param array<string, mixed> $extract
     * @return array<string, mixed>
     */
    public static function withAmountCheck(array $extract): array
    {
        $sum = 0.0;
        $has = false;
        foreach ($extract['lines'] as $line) {
            $n = self::parseMoney((string) ($line['line_net'] ?? ''));
            if ($n !== null) {
                $sum += $n;
                $has = true;
            }
        }
        $head = self::parseMoney((string) ($extract['net_total'] ?? ''));
        if (!$has || $head === null) {
            $extract['amount_check'] = ['ok' => true, 'detail' => ''];

            return $extract;
        }
        $delta = abs($sum - $head);
        if ($delta > 0.05) {
            $detail = sprintf('Zeilensumme Netto %.2f weicht vom Kopf %.2f ab.', $sum, $head);
            $extract['amount_check'] = ['ok' => false, 'detail' => $detail];
            $extract['warnings'][] = $detail;
        } else {
            $extract['amount_check'] = ['ok' => true, 'detail' => ''];
        }

        return $extract;
    }

    /**
     * @param array<string, mixed> $model
     * @param array<string, mixed> $hints
     * @return array<string, mixed>
     */
    private static function mergePreferringHintsOnConflict(array $model, array $hints): array
    {
        $out = $model;
        foreach (['supplier_name', 'invoice_number', 'invoice_date', 'iban', 'bic', 'vat_id', 'tax_number'] as $key) {
            $m = trim((string) ($model[$key] ?? ''));
            $h = trim((string) ($hints[$key] ?? ''));
            if ($h !== '' && $m !== '' && strcasecmp($h, $m) !== 0) {
                $out['warnings'][] = "Konflikt {$key}: Assistenz „{$m}“, Hinweis „{$h}“ — Hinweis behalten.";
                $out[$key] = $h;
            } elseif ($m === '' && $h !== '') {
                $out[$key] = $h;
            }
        }
        foreach (['gross_total', 'net_total', 'vat_total'] as $key) {
            $m = self::parseMoney((string) ($model[$key] ?? ''));
            $h = self::parseMoney((string) ($hints[$key] ?? ''));
            if ($m !== null && $h !== null && abs($m - $h) > 0.05) {
                $out['warnings'][] = "Konflikt {$key}: Assistenz vs. Hinweis — Hinweisbetrag behalten.";
                $out[$key] = self::moneyString($h);
            } elseif (($model[$key] ?? '') === '' && ($hints[$key] ?? '') !== '') {
                $out[$key] = $hints[$key];
            }
        }
        if ($model['lines'] === [] && $hints['lines'] !== []) {
            $out['lines'] = $hints['lines'];
            $out['warnings'][] = 'Assistenz ohne Positionen — Text-Hinweise übernommen.';
        }

        return $out;
    }

    /**
     * @param array<string, mixed> $hints
     */
    private static function buildStructurePrompt(string $raw, array $hints, string $fileName): string
    {
        $hintJson = json_encode([
            'supplier_name' => $hints['supplier_name'],
            'invoice_number' => $hints['invoice_number'],
            'invoice_date' => $hints['invoice_date'],
            'gross_total' => $hints['gross_total'],
            'net_total' => $hints['net_total'],
            'vat_total' => $hints['vat_total'],
            'lines' => $hints['lines'],
        ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        $name = $fileName !== '' ? $fileName : 'beleg';

        return <<<PROMPT
Du strukturierst Lieferantenrechnungen für ein CRM. Antworte NUR mit einem JSON-Objekt (kein Markdown, kein Text).

Schema (schema_version immer 1):
{
  "schema_version": 1,
  "source": "kichel",
  "supplier_name": "",
  "invoice_number": "",
  "invoice_date": "YYYY-MM-DD",
  "currency": "EUR",
  "gross_total": "0.00",
  "net_total": "0.00",
  "vat_total": "0.00",
  "iban": "",
  "bic": "",
  "vat_id": "",
  "tax_number": "",
  "lines": [
    {
      "line_no": 1,
      "catalog_kind": "product|service",
      "sku": "",
      "title": "",
      "quantity": "1",
      "unit": "Stück",
      "unit_price_net": "",
      "line_net": "",
      "line_gross": "",
      "tax_percent": 19,
      "confidence": 0.7
    }
  ],
  "warnings": []
}

VERBINDLICH:
- Nur Informationen aus dem Rohtext / den Hinweisen; nichts erfinden.
- Beträge nicht neu berechnen, wenn im Text klar; bei Unsicherheit warnings setzen.
- catalog_kind: product = Ware/Material, service = Dienstleistung.
- Keine Buchungs- oder Kontierungsvorschläge.
- Keine Gesetze oder Rechtsberatung.

Dateiname: {$name}

Hinweise (optional, dürfen korrigiert werden wenn Rohtext klar widerspricht):
{$hintJson}

Rohtext:
{$raw}
PROMPT;
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function parseJsonObjectFromModel(string $text): ?array
    {
        $trim = trim($text);
        if (preg_match('/```(?:json)?\s*([\s\S]*?)```/i', $trim, $m)) {
            $trim = trim($m[1]);
        }
        $start = strpos($trim, '{');
        $end = strrpos($trim, '}');
        if ($start === false || $end === false || $end <= $start) {
            return null;
        }
        $slice = substr($trim, $start, $end - $start + 1);
        $decoded = json_decode($slice, true);

        return is_array($decoded) ? $decoded : null;
    }

    private static function normalizeSource(string $source): string
    {
        $source = strtolower(trim($source));
        if (in_array($source, ['ocr', 'pdf_text', 'einvoice_xml', 'mixed'], true)) {
            return $source;
        }

        return 'pdf_text';
    }

    private static function moneyString(mixed $value): string
    {
        if ($value === '' || $value === null) {
            return '';
        }
        if (is_int($value) || is_float($value)) {
            return number_format((float) $value, 2, '.', '');
        }
        $n = self::parseMoney((string) $value);

        return $n === null ? '' : number_format($n, 2, '.', '');
    }

    private static function parseMoney(string $value): ?float
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }
        $value = str_replace(['€', 'EUR', ' '], '', $value);
        if (preg_match('/^\d{1,3}(\.\d{3})+,\d{2}$/', $value)) {
            $value = str_replace('.', '', $value);
            $value = str_replace(',', '.', $value);
        } else {
            $value = str_replace(',', '.', $value);
        }
        if (!is_numeric($value)) {
            return null;
        }

        return round((float) $value, 2);
    }

    private static function qtyString(mixed $value): string
    {
        if ($value === '' || $value === null) {
            return '1';
        }
        $n = self::parseMoney((string) $value);
        if ($n === null || $n == 0.0) {
            return '1';
        }
        if (abs($n - round($n)) < 0.00001) {
            return (string) (int) round($n);
        }

        return rtrim(rtrim(number_format($n, 3, '.', ''), '0'), '.') ?: '1';
    }

    private static function isoDate(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $value, $m)) {
            return $m[1] . '-' . $m[2] . '-' . $m[3];
        }
        if (preg_match('/(\d{1,2})[.\/-](\d{1,2})[.\/-](\d{2,4})/', $value, $m)) {
            $y = strlen($m[3]) === 2 ? '20' . $m[3] : $m[3];

            return sprintf('%s-%02d-%02d', $y, (int) $m[2], (int) $m[1]);
        }
        if (preg_match('/^(\d{8})$/', $value, $m)) {
            $d = $m[1];

            return substr($d, 0, 4) . '-' . substr($d, 4, 2) . '-' . substr($d, 6, 2);
        }

        return '';
    }
}
