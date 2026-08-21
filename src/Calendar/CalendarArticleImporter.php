<?php
declare(strict_types=1);

/** CSV/Excel/XML/JSON/PDF-Import für Kalender-Leistungen — herstellerneutral. */
final class CalendarArticleImporter
{
    /**
     * Methode template headers.
     * @return array<string, mixed>
     */
    public static function templateHeaders(): array
    {
        return [
            'Artikelnummer',
            'GTIN/EAN',
            'Bezeichnung',
            'Beschreibung',
            'Notiz',
            'Einheit',
            'Steuerart',
            'VK (Brutto)',
            'Arbeitszeit',
        ];
    }

    /**
     * Methode template csv.
     * @return string
     */
    public static function templateCsv(): string
    {
        $content = chr(0xEF) . chr(0xBB) . chr(0xBF);
        $content .= self::csvLine(self::templateHeaders());

        return $content;
    }

    /**
     * Methode template json.
     * @return string
     */
    public static function templateJson(): string
    {
        $example = [];
        foreach (self::templateHeaders() as $header) {
            $example[$header] = match ($header) {
                'Bezeichnung' => 'Beispiel-Leistung',
                'Einheit' => 'Stück',
                'Steuerart' => 'USt19',
                'VK (Brutto)' => '19,90',
                'Arbeitszeit' => '30',
                default => '',
            };
        }

        return json_encode([$example], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }

    /**
     * Methode import uploaded file.
     * @param array $file
     * @param int $defaultAreaId
     * @return array{imported: int, updated: int, errors: list<string>, message: string}
     * @throws RuntimeException
     * @throws InvalidArgumentException
     */
    public static function importUploadedFile(array $file, int $defaultAreaId = 0): array
    {
        if (!Database::isConfigured()) {
            throw new RuntimeException('Datenbank nicht konfiguriert.');
        }

        $tmpName = (string) ($file['tmp_name'] ?? '');
        $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($error !== UPLOAD_ERR_OK || $tmpName === '' || !is_uploaded_file($tmpName)) {
            throw new InvalidArgumentException('Keine gültige Importdatei hochgeladen.');
        }

        $ext = strtolower(pathinfo((string) ($file['name'] ?? ''), PATHINFO_EXTENSION));
        if (!CalendarArticleImportReader::isSupportedExtension($ext)) {
            throw new InvalidArgumentException(
                'Nicht unterstütztes Format. Erlaubt: '
                . implode(', ', CalendarArticleImportReader::supportedExtensions()) . '.'
            );
        }

        $rows = CalendarArticleImportReader::readFile($tmpName, $ext);
        if (count($rows) < 2) {
            throw new InvalidArgumentException('Die Datei enthält keine Datenzeilen.');
        }

        $header = array_map(self::normalizeHeader(...), $rows[0]);
        $map = self::mapImportColumns($header);

        if ($map['title'] === null) {
            $detected = array_values(array_filter($header));
            $hint = $detected !== []
                ? 'Erkannte Spalten: ' . implode(' | ', $detected)
                : 'Keine Spaltenüberschriften erkannt.';
            throw new InvalidArgumentException('Pflichtspalte „Bezeichnung“ (oder Name/Titel) fehlt. ' . $hint);
        }

        $priceIsNet = self::headerUsesNetPrice($header);

        $imported = 0;
        $updated = 0;
        $errors = [];

        for ($i = 1, $count = count($rows); $i < $count; $i++) {
            $line = $rows[$i];
            if (self::isEmptyRow($line)) {
                continue;
            }

            $rawRow = [];
            foreach ($map as $field => $colIndex) {
                if ($colIndex !== null && isset($line[$colIndex])) {
                    $rawRow[$field] = $line[$colIndex];
                }
            }

            try {
                $data = self::sanitizeImportRow($rawRow, $priceIsNet, $defaultAreaId);
                $existingId = self::resolveExistingImportId($rawRow, $data);
                CalendarArticleRepository::save(array_merge($data, [
                    'import' => true,
                    'article_id' => $existingId ?? 0,
                ]));
                if ($existingId) {
                    $updated++;
                } else {
                    $imported++;
                }
            } catch (Throwable $e) {
                $errors[] = 'Zeile ' . ($i + 1) . ': ' . $e->getMessage();
            }
        }

        $message = sprintf('Import abgeschlossen: %d neu, %d aktualisiert.', $imported, $updated);
        if ($errors !== []) {
            $message .= ' Fehler: ' . implode(' | ', array_slice($errors, 0, 5));
            if (count($errors) > 5) {
                $message .= sprintf(' (+%d weitere)', count($errors) - 5);
            }
        }

        return [
            'imported' => $imported,
            'updated' => $updated,
            'errors' => $errors,
            'message' => $message,
        ];
    }

    /**
     * Methode csv line.
     * @param array $fields
     * @return string
     */
    private static function csvLine(array $fields): string
    {
        $parts = [];
        foreach ($fields as $field) {
            $parts[] = '"' . str_replace('"', '""', (string) $field) . '"';
        }

        return implode(';', $parts) . "\r\n";
    }

    /**
     * Führt aus: normalize header.
     * @param string $value
     * @return string
     */
    private static function normalizeHeader(string $value): string
    {
        $value = CalendarArticleImportReader::cleanCell($value);
        $value = strtolower($value);
        $value = str_replace([' ', '-', '/', '(', ')', '.', '€', '%'], ['_', '_', '_', '', '', '', '', ''], $value);

        return $value;
    }

    /**
     * Methode header uses net price.
     * @param array $header
     * @return bool
     */
    private static function headerUsesNetPrice(array $header): bool
    {
        foreach ($header as $col) {
            if (str_contains($col, 'netto')) {
                return true;
            }
        }

        return false;
    }

    /**
     * Methode map import columns.
     * @param array $header
     * @return array<string, mixed>
     */
    private static function mapImportColumns(array $header): array
    {
        $aliases = [
            'article_number' => [
                'artikelnummer', 'artikel_nr', 'artikelnr', 'sku', 'article_number', 'nummer', 'art_nr', 'product_code',
            ],
            'gtin' => ['gtin', 'ean', 'gtin_ean', 'barcode', 'gtin_ean_code'],
            'title' => ['bezeichnung', 'titel', 'name', 'title', 'leistung', 'produkt', 'product_name'],
            'description' => ['beschreibung', 'description', 'langtext'],
            'note' => ['notiz', 'note', 'interne_notiz', 'memo'],
            'unit' => ['einheit', 'unit', 'mengeneinheit', 'me'],
            'tax_type' => ['steuerart', 'steuer', 'tax', 'tax_type', 'mwst', 'ust', 'vat'],
            'price_gross' => [
                'verkaufspreis', 'verkaufspreis_brutto', 'vk_brutto', 'vk', 'preis', 'preis_brutto',
                'price', 'price_gross', 'bruttopreis', 'vk_brutto_eur',
            ],
            'price_net' => [
                'verkaufspreis_netto', 'vk_netto', 'preis_netto', 'price_net', 'nettopreis', 'vk_netto_eur',
            ],
            'work_duration' => ['arbeitszeit', 'dauer', 'work_duration', 'work_time', 'duration', 'minuten'],
            'catalog_kind' => ['art', 'typ', 'type', 'katalog', 'catalog_kind', 'artikelart', 'kategorie'],
        ];

    /** @var array<string, int|null> $map */
        $map = array_fill_keys(array_keys($aliases), null);

        foreach ($header as $index => $col) {
            foreach ($aliases as $field => $names) {
                if (in_array($col, $names, true) && $map[$field] === null) {
                    $map[$field] = $index;
                }
            }
        }

        foreach ($header as $index => $col) {
            if ($map['article_number'] === null && str_contains($col, 'artikel') && str_contains($col, 'nummer')) {
                $map['article_number'] = $index;
            }
            if ($map['title'] === null && (str_contains($col, 'bezeichnung') || $col === 'name' || $col === 'title')) {
                $map['title'] = $index;
            }
            if ($map['price_gross'] === null && str_contains($col, 'brutto')) {
                $map['price_gross'] = $index;
            }
            if ($map['price_net'] === null && str_contains($col, 'netto')) {
                $map['price_net'] = $index;
            }
            if ($map['gtin'] === null && str_contains($col, 'gtin')) {
                $map['gtin'] = $index;
            }
        }

        if ($map['price_gross'] !== null && $map['price_net'] !== null && self::headerUsesNetPrice($header)) {
            $map['price_gross'] = null;
        }

        return $map;
    }

    /**
     * Prüft: is empty row.
     * @param array $line
     * @return bool
     */
    private static function isEmptyRow(array $line): bool
    {
        foreach ($line as $cell) {
            if (trim((string) $cell) !== '') {
                return false;
            }
        }

        return true;
    }

    /**
     * Führt aus: sanitize import row.
     * @param array $raw
     * @param bool $priceIsNet
     * @param int $defaultAreaId
     * @return array<string, mixed>
     * @throws InvalidArgumentException
     */
    private static function sanitizeImportRow(array $raw, bool $priceIsNet, int $defaultAreaId): array
    {
        $title = CalendarArticleValidator::normalizeImportTitle((string) ($raw['title'] ?? ''));
        if ($title === '') {
            throw new InvalidArgumentException('Bezeichnung fehlt.');
        }

        $catalogKind = CalendarArticleCatalog::normalizeKind((string) ($raw['catalog_kind'] ?? CalendarArticleCatalog::KIND_SERVICE));
        $sourceNumber = trim((string) ($raw['article_number'] ?? ''));
        if ($sourceNumber !== '') {
            $articleNumber = CalendarArticleValidator::validateArticleNumber($sourceNumber);
        } else {
            $existingId = CalendarArticleRepository::findIdByTitleForImport($title);
            $existingNumber = $existingId ? CalendarArticleRepository::articleNumberById($existingId) : null;
            if ($existingNumber !== null && $existingNumber !== '') {
                $articleNumber = $existingNumber;
            } else {
                $articleNumber = CalendarArticleRepository::allocateArticleNumber($catalogKind, true);
            }
        }

        $taxType = CalendarArticleValidator::normalizeTaxTypeForImport((string) ($raw['tax_type'] ?? ''));

        $priceGross = trim((string) ($raw['price_gross'] ?? ''));
        $priceNet = trim((string) ($raw['price_net'] ?? ''));
        if ($priceGross === '' && $priceNet !== '') {
            $priceGross = (string) CalendarArticleValidator::grossFromNet(
                CalendarArticleValidator::validateImportPrice($priceNet),
                $taxType
            );
        } elseif ($priceIsNet && $priceGross !== '') {
            $priceGross = (string) CalendarArticleValidator::grossFromNet(
                CalendarArticleValidator::validateImportPrice($priceGross),
                $taxType
            );
        }

        $workRaw = trim((string) ($raw['work_duration'] ?? ''));
        $workMinutes = self::defaultWorkMinutesForUnit((string) ($raw['unit'] ?? ''), $workRaw);

        return [
            'article_number' => $articleNumber,
            'catalog_kind' => $catalogKind,
            'gtin' => CalendarArticleValidator::validateGtin((string) ($raw['gtin'] ?? '')),
            'title' => $title,
            'description' => trim((string) ($raw['description'] ?? '')),
            'note' => trim((string) ($raw['note'] ?? '')),
            'unit' => CalendarArticleValidator::normalizeUnitForImport((string) ($raw['unit'] ?? '')),
            'tax_type' => $taxType,
            'price_gross' => CalendarArticleValidator::validateImportPrice($priceGross !== '' ? $priceGross : '0'),
            'work_minutes' => $workMinutes,
            'area_id' => max(0, $defaultAreaId),
            'sort_order' => 0,
            'is_active' => 1,
        ];
    }

    /**
     * Default Work Minutes For Unit.
     * @param string $unit
     * @param string $workRaw
     * @return int
     */
    private static function defaultWorkMinutesForUnit(string $unit, string $workRaw): int
    {
        if ($workRaw !== '') {
            if (is_numeric($workRaw)) {
                return max(1, min(1440, (int) $workRaw));
            }
            if (str_contains($workRaw, ':')) {
                [$hours, $minutes] = array_map('intval', explode(':', $workRaw, 2) + [0, 0]);

                return max(1, min(1440, $hours * 60 + $minutes));
            }
        }

        $unitLower = strtolower(CalendarArticleValidator::normalizeUnitForImport($unit));
        if ($unitLower === 'stunde' || str_contains($unitLower, 'stunde')) {
            return 60;
        }

        return 30;
    }

    /**
     * Führt aus: resolve existing import id.
     * @param array $rawRow
     * @param array $data
     * @return int|null
     */
    private static function resolveExistingImportId(array $rawRow, array $data): ?int
    {
        $sourceNumber = trim((string) ($rawRow['article_number'] ?? ''));
        if ($sourceNumber !== '') {
            return CalendarArticleRepository::findIdByArticleNumber($sourceNumber);
        }

        return CalendarArticleRepository::findIdByTitleForImport((string) ($data['title'] ?? ''));
    }
}
