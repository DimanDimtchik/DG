<?php
declare(strict_types=1);

/** Buchbare Leistungen (Katalog, Dauer, Bereich → Mitarbeiter-Zuordnung). */
final class CalendarArticleRepository
{
    /** @var list<int> */
    public const WORK_MINUTE_PRESETS = [15, 30, 45, 60];

    /**
     * Methode all.
     * @param bool $activeOnly
     * @param string|null $catalogKind
     * @return array<string, mixed>
     */
    public static function all(bool $activeOnly = false, ?string $catalogKind = null): array
    {
        if (!Database::isConfigured()) {
            return [];
        }

        $sql = 'SELECT * FROM dg_calendar_articles WHERE 1=1';
        $params = [];
        if ($activeOnly) {
            $sql .= ' AND is_active = 1';
        }
        if ($catalogKind !== null && $catalogKind !== '' && $catalogKind !== 'all') {
            $sql .= ' AND catalog_kind = :catalog_kind';
            $params['catalog_kind'] = CalendarArticleCatalog::normalizeKind($catalogKind);
        }
        $sql .= ' ORDER BY sort_order ASC, title ASC';

        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        foreach ($rows as &$row) {
            self::enrichRow($row);
        }
        unset($row);

        return $rows;
    }

    /**
     * Findet einen Datensatz anhand der ID.
     * @param int $id
     * @return array|null
     */
    public static function findById(int $id): ?array
    {
        if ($id < 1 || !Database::isConfigured()) {
            return null;
        }

        $stmt = Database::pdo()->prepare('SELECT * FROM dg_calendar_articles WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }
        self::enrichRow($row);

        return $row;
    }

    /**
     * Liefert work minutes.
     * @param int $articleId
     * @return int
     */
    public static function getWorkMinutes(int $articleId): int
    {
        $article = self::findById($articleId);

        return $article ? max(0, (int) ($article['work_minutes'] ?? 0)) : 0;
    }

    /**
     * Liefert area id.
     * @param int $articleId
     * @return int
     */
    public static function getAreaId(int $articleId): int
    {
        $article = self::findById($articleId);

        return $article ? max(0, (int) ($article['area_id'] ?? 0)) : 0;
    }

    /**
     * Methode title.
     * @param int $articleId
     * @return string
     */
    public static function title(int $articleId): string
    {
        $article = self::findById($articleId);

        return $article ? (string) ($article['title'] ?? '') : '';
    }

    /**
     * Methode price gross.
     * @param int $articleId
     * @return float
     */
    public static function priceGross(int $articleId): float
    {
        $article = self::findById($articleId);

        return $article ? (float) ($article['price_gross'] ?? 0) : 0.0;
    }

    /**
     * Methode save.
     * @param array $input
     * @return void
     * @throws RuntimeException
     * @throws InvalidArgumentException
     */
    public static function save(array $input): void
    {
        if (!Database::isConfigured()) {
            throw new RuntimeException('Datenbank nicht konfiguriert.');
        }

        $id = max(0, (int) ($input['article_id'] ?? 0));
        $catalogKind = CalendarArticleCatalog::normalizeKind((string) ($input['catalog_kind'] ?? CalendarArticleCatalog::KIND_SERVICE));
        $articleNumber = trim((string) ($input['article_number'] ?? ''));
        if ($articleNumber === '') {
            $articleNumber = self::suggestArticleNumber($catalogKind);
        }
        $articleNumber = CalendarArticleValidator::validateArticleNumber($articleNumber);
        $gtin = CalendarArticleValidator::validateGtin((string) ($input['gtin'] ?? ''));
        $title = trim((string) ($input['title'] ?? ''));
        $description = trim((string) ($input['description'] ?? ''));
        $note = trim((string) ($input['note'] ?? ''));
        $unit = !empty($input['import'])
            ? CalendarArticleValidator::normalizeUnitForImport((string) ($input['unit'] ?? ''))
            : CalendarArticleValidator::validateUnit((string) ($input['unit'] ?? 'Stück'));
        $taxType = !empty($input['import'])
            ? (string) ($input['tax_type'] ?? 'ust19')
            : CalendarArticleValidator::validateTaxType((string) ($input['tax_type'] ?? 'ust19'));
        $priceGross = !empty($input['import'])
            ? CalendarArticleValidator::validateImportPrice($input['price_gross'] ?? 0)
            : CalendarArticleValidator::validatePriceGross($input['price_gross'] ?? 0);
        $workMinutes = self::resolveWorkMinutesFromInput($input);
        $areaId = max(0, (int) ($input['area_id'] ?? 0));
        $sortOrder = max(0, (int) ($input['sort_order'] ?? 0));
        $isActive = !empty($input['is_active']) ? 1 : 0;
        $trackStock = $catalogKind === CalendarArticleCatalog::KIND_PRODUCT && !empty($input['track_stock']) ? 1 : 0;
        $minStock = 0.0;
        if ($trackStock === 1) {
            $minStock = round((float) str_replace(',', '.', (string) ($input['min_stock'] ?? 0)), 3);
            if ($minStock < 0) {
                throw new InvalidArgumentException('Mindestbestand darf nicht negativ sein.');
            }
        }
        $initialStock = 0.0;
        if ($trackStock === 1 && $id < 1) {
            $initialStock = round((float) str_replace(',', '.', (string) ($input['initial_stock'] ?? 0)), 3);
            if ($initialStock < 0) {
                throw new InvalidArgumentException('Anfangsbestand darf nicht negativ sein.');
            }
        }
        $stockPosition = ['ort' => '', 'halle' => '', 'regal' => '', 'platz' => ''];
        if ($trackStock === 1) {
            $stockPosition = StockPositionCode::fromInput($input);
            StockPositionCode::assertCompleteIfAny($stockPosition);
        }

        if ($title === '') {
            throw new InvalidArgumentException('Bezeichnung der Leistung ist erforderlich.');
        }
        if ($workMinutes < 1) {
            throw new InvalidArgumentException('Gültige Arbeitszeit erforderlich.');
        }
        if (CalendarStaffRepository::hasActiveEmployees() && $areaId < 1 && empty($input['import'])) {
            throw new InvalidArgumentException('Bitte einen Bereich zuordnen (Mitarbeiter-Zuordnung über Bereich).');
        }

        self::assertUniqueArticleNumber($articleNumber, $id);

        $fields = [
            'article_number' => $articleNumber,
            'catalog_kind' => $catalogKind,
            'gtin' => $gtin,
            'title' => $title,
            'description' => $description,
            'note' => $note,
            'unit' => $unit,
            'tax_type' => $taxType,
            'price_gross' => $priceGross,
            'work_minutes' => $workMinutes,
            'area_id' => $areaId,
            'sort_order' => $sortOrder,
            'is_active' => $isActive,
            'track_stock' => $trackStock,
            'min_stock' => $minStock,
            'stock_ort' => $stockPosition['ort'],
            'stock_halle' => $stockPosition['halle'],
            'stock_regal' => $stockPosition['regal'],
            'stock_platz' => $stockPosition['platz'],
        ];

        $pdo = Database::pdo();
        if ($id > 0) {
            $fields['id'] = $id;
            $stmt = $pdo->prepare(
                'UPDATE dg_calendar_articles
                 SET article_number = :article_number, catalog_kind = :catalog_kind, gtin = :gtin, title = :title, description = :description,
                     note = :note, unit = :unit, tax_type = :tax_type, price_gross = :price_gross,
                     work_minutes = :work_minutes, area_id = :area_id, sort_order = :sort_order, is_active = :is_active,
                     track_stock = :track_stock, min_stock = :min_stock,
                     stock_ort = :stock_ort, stock_halle = :stock_halle, stock_regal = :stock_regal, stock_platz = :stock_platz
                 WHERE id = :id'
            );
            $stmt->execute($fields);

            return;
        }

        $fields['stock_qty'] = 0;
        $stmt = $pdo->prepare(
            'INSERT INTO dg_calendar_articles
             (article_number, catalog_kind, gtin, title, description, note, unit, tax_type, price_gross, work_minutes, area_id, sort_order, is_active, track_stock, stock_qty, min_stock, stock_ort, stock_halle, stock_regal, stock_platz)
             VALUES
             (:article_number, :catalog_kind, :gtin, :title, :description, :note, :unit, :tax_type, :price_gross, :work_minutes, :area_id, :sort_order, :is_active, :track_stock, :stock_qty, :min_stock, :stock_ort, :stock_halle, :stock_regal, :stock_platz)'
        );
        $stmt->execute($fields);
        $newId = (int) $pdo->lastInsertId();
        if ($newId > 0 && $trackStock === 1 && $initialStock > 0) {
            StockMovementService::recordOpeningBalance($newId, $initialStock, null);
        }
    }

    /**
     * Liefert id by article number.
     * @param string $articleNumber
     * @return int|null
     */
    public static function findIdByArticleNumber(string $articleNumber): ?int
    {
        $articleNumber = trim($articleNumber);
        if ($articleNumber === '' || !Database::isConfigured()) {
            return null;
        }

        $stmt = Database::pdo()->prepare('SELECT id FROM dg_calendar_articles WHERE article_number = :article_number LIMIT 1');
        $stmt->execute(['article_number' => $articleNumber]);

        $id = $stmt->fetchColumn();

        return $id !== false ? (int) $id : null;
    }

    /**
     * Liefert id by title.
     * @param string $title
     * @return int|null
     */
    public static function findIdByTitle(string $title): ?int
    {
        $title = trim($title);
        if ($title === '' || !Database::isConfigured()) {
            return null;
        }

        $stmt = Database::pdo()->prepare('SELECT id FROM dg_calendar_articles WHERE title = :title LIMIT 1');
        $stmt->execute(['title' => $title]);

        $id = $stmt->fetchColumn();

        return $id !== false ? (int) $id : null;
    }

    /**
     * Liefert id by title for import.
     * @param string $title
     * @return int|null
     */
    public static function findIdByTitleForImport(string $title): ?int
    {
        if (!Database::isConfigured()) {
            return null;
        }

        $candidates = array_unique(array_filter([
            trim($title),
            CalendarArticleValidator::normalizeImportTitle($title),
        ]));
        if ($candidates === []) {
            return null;
        }

        foreach ($candidates as $candidate) {
            $stmt = Database::pdo()->prepare(
                'SELECT id FROM dg_calendar_articles WHERE title = :title ORDER BY id ASC LIMIT 1'
            );
            $stmt->execute(['title' => $candidate]);
            $id = $stmt->fetchColumn();
            if ($id !== false) {
                return (int) $id;
            }
        }

        $normalized = CalendarArticleValidator::normalizeImportTitle($title);
        $stmt = Database::pdo()->prepare(
            'SELECT id FROM dg_calendar_articles WHERE LEFT(TRIM(title), 255) = :title ORDER BY id ASC LIMIT 1'
        );
        $stmt->execute(['title' => $normalized]);
        $id = $stmt->fetchColumn();

        return $id !== false ? (int) $id : null;
    }

    /**
     * Methode max import article sequence.
     * @return int
     */
    public static function maxImportArticleSequence(): int
    {
        if (!Database::isConfigured()) {
            return 0;
        }

        $stmt = Database::pdo()->query(
            "SELECT article_number FROM dg_calendar_articles WHERE article_number LIKE 'IMP-%'"
        );
        $max = 0;
        while ($number = $stmt->fetchColumn()) {
            if (preg_match('/^IMP-(\d+)$/', (string) $number, $match)) {
                $max = max($max, (int) $match[1]);
            }
        }

        return $max;
    }

    /**
     * Methode article number by id.
     * @param int $id
     * @return string|null
     */
    public static function articleNumberById(int $id): ?string
    {
        if ($id < 1 || !Database::isConfigured()) {
            return null;
        }

        $stmt = Database::pdo()->prepare('SELECT article_number FROM dg_calendar_articles WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);

        $number = $stmt->fetchColumn();

        return $number !== false ? (string) $number : null;
    }

    /**
     * Führt aus: delete.
     * @param int $id
     * @return void
     * @throws InvalidArgumentException
     * @throws RuntimeException
     */
    public static function delete(int $id): void
    {
        if ($id < 1) {
            throw new InvalidArgumentException('ID erforderlich.');
        }
        if (!Database::isConfigured()) {
            throw new RuntimeException('Datenbank nicht konfiguriert.');
        }

        Database::pdo()->prepare('DELETE FROM dg_calendar_articles WHERE id = :id')->execute(['id' => $id]);
    }

    /**
     * Methode format duration.
     * @param int $minutes
     * @return string
     */
    public static function formatDuration(int $minutes): string
    {
        if ($minutes < 1) {
            return '—';
        }
        if ($minutes % 60 === 0) {
            $hours = intdiv($minutes, 60);

            return $hours === 1 ? '1 Stunde' : $hours . ' Stunden';
        }
        if ($minutes > 60) {
            $hours = intdiv($minutes, 60);
            $rest = $minutes % 60;

            return $hours . ' Std. ' . $rest . ' Min.';
        }

        return $minutes . ' Min.';
    }

    /**
     * Methode suggest article number.
     * @param string $catalogKind
     * @return string
     */
    public static function suggestArticleNumber(string $catalogKind = CalendarArticleCatalog::KIND_SERVICE): string
    {
        return self::allocateArticleNumber($catalogKind, false);
    }

    /**
     * Methode allocate article number.
     * @param string $catalogKind
     * @param bool $persist
     * @return string
     */
    public static function allocateArticleNumber(string $catalogKind, bool $persist = true): string
    {
        $catalogKind = CalendarArticleCatalog::normalizeKind($catalogKind);
        if (!Database::isConfigured()) {
            return $catalogKind === CalendarArticleCatalog::KIND_PRODUCT ? 'A-0001' : 'L-0001';
        }

        try {
            $type = CalendarArticleCatalog::numberRangeType($catalogKind);

            return NumberRangeSettings::allocateNext($type, $persist)['number'];
        } catch (Throwable) {
            return $catalogKind === CalendarArticleCatalog::KIND_PRODUCT ? 'A-0001' : 'L-0001';
        }
    }

    /**
     * Methode booking options.
     * @return array<string, mixed>
     */
    public static function bookingOptions(): array
    {
        $options = [];
        foreach (self::all(true, CalendarArticleCatalog::KIND_SERVICE) as $row) {
            $id = (int) ($row['id'] ?? 0);
            if ($id < 1) {
                continue;
            }
            $options[] = [
                'id' => $id,
                'title' => (string) ($row['title'] ?? ''),
                'work_minutes' => (int) ($row['work_minutes'] ?? 0),
                'area_id' => (int) ($row['area_id'] ?? 0),
                'uses_employees' => CalendarStaffRepository::usesEmployeeSchedulingForArticle($id),
                'price_gross' => (float) ($row['price_gross'] ?? 0),
                'price_label' => (string) ($row['price_label'] ?? ''),
            ];
        }

        return $options;
    }

    /**
     * Methode enrich row.
     * @param mixed $row
     * @return void
     */
    private static function enrichRow(array &$row): void
    {
        $row['duration_label'] = self::formatDuration((int) ($row['work_minutes'] ?? 0));
        $row['price_label'] = CalendarArticleValidator::formatPrice((float) ($row['price_gross'] ?? 0));
        $row['tax_label'] = CalendarArticleValidator::taxLabel((string) ($row['tax_type'] ?? ''));
        $row['kind_label'] = CalendarArticleCatalog::kindLabel((string) ($row['catalog_kind'] ?? CalendarArticleCatalog::KIND_SERVICE));
        $row['track_stock'] = !empty($row['track_stock']);
        $row['stock_qty'] = round((float) ($row['stock_qty'] ?? 0), 3);
        $row['min_stock'] = round((float) ($row['min_stock'] ?? 0), 3);
        $row['stock_position_code'] = StockPositionCode::fromRow($row);
        if ($row['track_stock']) {
            $unit = (string) ($row['unit'] ?? 'Stück');
            $row['stock_label'] = StockMovementService::formatQty((float) $row['stock_qty'], $unit);
            $row['is_low_stock'] = $row['min_stock'] > 0 && $row['stock_qty'] <= $row['min_stock'];
        } else {
            $row['stock_label'] = '—';
            $row['is_low_stock'] = false;
        }
    }

    /**
     * Methode assert unique article number.
     * @param string $articleNumber
     * @param int $excludeId
     * @return void
     * @throws InvalidArgumentException
     */
    private static function assertUniqueArticleNumber(string $articleNumber, int $excludeId): void
    {
        $sql = 'SELECT id FROM dg_calendar_articles WHERE article_number = :article_number';
        $params = ['article_number' => $articleNumber];
        if ($excludeId > 0) {
            $sql .= ' AND id <> :id';
            $params['id'] = $excludeId;
        }
        $sql .= ' LIMIT 1';

        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute($params);
        if ($stmt->fetchColumn()) {
            throw new InvalidArgumentException('Artikelnummer ist bereits vergeben.');
        }
    }

    /**
     * Resolve Work Minutes From Input.
     * @param array $input
     * @return int
     */
    private static function resolveWorkMinutesFromInput(array $input): int
    {
        $preset = (string) ($input['work_minutes'] ?? '');
        if ($preset === '__custom__') {
            return self::sanitizeWorkMinutes($input['custom_work_minutes'] ?? 0);
        }

        $minutes = self::sanitizeWorkMinutes($preset !== '' ? $preset : ($input['custom_work_minutes'] ?? 30));

        return $minutes > 0 ? $minutes : 30;
    }

    /**
     * Führt aus: sanitize work minutes.
     * @param mixed $value
     * @return int
     */
    private static function sanitizeWorkMinutes(mixed $value): int
    {
        if (is_string($value) && $value === '__custom__') {
            return 0;
        }

        $minutes = (int) $value;
        if ($minutes > 0 && $minutes <= 1440) {
            return $minutes;
        }

        return 0;
    }
}
