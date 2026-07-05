<?php
declare(strict_types=1);

/** Buchbare Leistungen (Katalog, Dauer, Bereich → Mitarbeiter-Zuordnung). */
final class CalendarArticleRepository
{
    /** @var list<int> */
    public const WORK_MINUTE_PRESETS = [15, 30, 45, 60];

    /** @return list<array<string, mixed>> */
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

    /** @return array<string, mixed>|null */
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

    public static function getWorkMinutes(int $articleId): int
    {
        $article = self::findById($articleId);

        return $article ? max(0, (int) ($article['work_minutes'] ?? 0)) : 0;
    }

    public static function getAreaId(int $articleId): int
    {
        $article = self::findById($articleId);

        return $article ? max(0, (int) ($article['area_id'] ?? 0)) : 0;
    }

    public static function title(int $articleId): string
    {
        $article = self::findById($articleId);

        return $article ? (string) ($article['title'] ?? '') : '';
    }

    public static function priceGross(int $articleId): float
    {
        $article = self::findById($articleId);

        return $article ? (float) ($article['price_gross'] ?? 0) : 0.0;
    }

    /** @param array<string, mixed> $input */
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
        ];

        $pdo = Database::pdo();
        if ($id > 0) {
            $fields['id'] = $id;
            $stmt = $pdo->prepare(
                'UPDATE dg_calendar_articles
                 SET article_number = :article_number, catalog_kind = :catalog_kind, gtin = :gtin, title = :title, description = :description,
                     note = :note, unit = :unit, tax_type = :tax_type, price_gross = :price_gross,
                     work_minutes = :work_minutes, area_id = :area_id, sort_order = :sort_order, is_active = :is_active
                 WHERE id = :id'
            );
            $stmt->execute($fields);

            return;
        }

        $stmt = $pdo->prepare(
            'INSERT INTO dg_calendar_articles
             (article_number, catalog_kind, gtin, title, description, note, unit, tax_type, price_gross, work_minutes, area_id, sort_order, is_active)
             VALUES
             (:article_number, :catalog_kind, :gtin, :title, :description, :note, :unit, :tax_type, :price_gross, :work_minutes, :area_id, :sort_order, :is_active)'
        );
        $stmt->execute($fields);
    }

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

    /** Zuordnung beim Re-Import (normalisierte Bezeichnung, ältester Treffer). */
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

    public static function suggestArticleNumber(string $catalogKind = CalendarArticleCatalog::KIND_SERVICE): string
    {
        return self::allocateArticleNumber($catalogKind, false);
    }

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

    /** @return list<array{id: int, title: string, work_minutes: int, area_id: int, uses_employees: bool, price_gross: float, price_label: string}> */
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

    /** @param array<string, mixed> $row */
    private static function enrichRow(array &$row): void
    {
        $row['duration_label'] = self::formatDuration((int) ($row['work_minutes'] ?? 0));
        $row['price_label'] = CalendarArticleValidator::formatPrice((float) ($row['price_gross'] ?? 0));
        $row['tax_label'] = CalendarArticleValidator::taxLabel((string) ($row['tax_type'] ?? ''));
        $row['kind_label'] = CalendarArticleCatalog::kindLabel((string) ($row['catalog_kind'] ?? CalendarArticleCatalog::KIND_SERVICE));
    }

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

    /** @param array<string, mixed> $input */
    private static function resolveWorkMinutesFromInput(array $input): int
    {
        $preset = (string) ($input['work_minutes'] ?? '');
        if ($preset === '__custom__') {
            return self::sanitizeWorkMinutes($input['custom_work_minutes'] ?? 0);
        }

        $minutes = self::sanitizeWorkMinutes($preset !== '' ? $preset : ($input['custom_work_minutes'] ?? 30));

        return $minutes > 0 ? $minutes : 30;
    }

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
