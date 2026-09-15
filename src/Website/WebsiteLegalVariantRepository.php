<?php
declare(strict_types=1);

/**
 * Produkt-/Gruppen-Varianten für Rechtstext-Seiten (Tabs).
 */
final class WebsiteLegalVariantRepository
{
    /**
     * @return list<array{id: int, page_slug: string, product_key: string, label: string, status: string, layout: array, sort_order: int}>
     */
    public static function listForPage(string $pageSlug): array
    {
        if (!Database::isConfigured()) {
            return [];
        }
        MigrationRunner::runPending();
        $pageSlug = LegalProductSettings::sanitizeSlug($pageSlug);
        if ($pageSlug === '') {
            return [];
        }

        $stmt = Database::pdo()->prepare(
            'SELECT * FROM dg_website_legal_variants
             WHERE page_slug = :page_slug
             ORDER BY sort_order ASC, label ASC, id ASC'
        );
        $stmt->execute(['page_slug' => $pageSlug]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return is_array($rows) ? array_map([self::class, 'map'], $rows) : [];
    }

    /**
     * @return array{id: int, page_slug: string, product_key: string, label: string, status: string, layout: array, sort_order: int}|null
     */
    public static function find(string $pageSlug, string $productKey): ?array
    {
        $pageSlug = LegalProductSettings::sanitizeSlug($pageSlug);
        $productKey = LegalProductSettings::sanitizeProductKey($productKey);
        if ($pageSlug === '' || $productKey === '') {
            return null;
        }

        $stmt = Database::pdo()->prepare(
            'SELECT * FROM dg_website_legal_variants
             WHERE page_slug = :page_slug AND product_key = :product_key LIMIT 1'
        );
        $stmt->execute(['page_slug' => $pageSlug, 'product_key' => $productKey]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? self::map($row) : null;
    }

    /**
     * Varianten für die öffentliche Anzeige (Online = published; Vorschau auch draft/private).
     *
     * @return list<array{id: int, page_slug: string, product_key: string, label: string, status: string, layout: array, sort_order: int}>
     */
    public static function listForPublic(string $pageSlug, bool $previewMode = false): array
    {
        $all = self::listForPage($pageSlug);
        if ($previewMode) {
            return $all;
        }

        return array_values(array_filter(
            $all,
            static fn (array $v): bool => ($v['status'] ?? '') === WebsitePageRepository::STATUS_PUBLISHED
        ));
    }

    /**
     * Legt fehlende Varianten für alle Produkt-Tabs und Rechtsslugs an.
     */
    public static function ensureVariantsForAllProducts(): void
    {
        if (!Database::isConfigured()) {
            return;
        }
        MigrationRunner::runPending();

        foreach (LegalProductSettings::LEGAL_PAGES as $slug => $title) {
            self::ensureDefaultsForPage($slug);
            foreach (LegalProductSettings::allProductTabs() as $tab) {
                self::ensureVariant($slug, $tab['key'], $tab['label']);
            }
        }
    }

    public static function ensureDefaultsForPage(string $pageSlug): void
    {
        $pageSlug = LegalProductSettings::sanitizeSlug($pageSlug);
        if ($pageSlug === '') {
            return;
        }

        $page = WebsitePageRepository::findBySlugAnyStatus($pageSlug);
        if ($page === null) {
            return;
        }

        $html = self::extractHtmlFromLayout($page['layout'] ?? []);
        if ($html === '') {
            return;
        }

        self::saveHtmlVariant(
            $pageSlug,
            LegalProductSettings::DEFAULT_PRODUCT_KEY,
            'Allgemein',
            (string) ($page['status'] ?? WebsitePageRepository::STATUS_PUBLISHED),
            $html,
            0,
            false
        );
    }

    public static function ensureVariant(string $pageSlug, string $productKey, string $label): void
    {
        if (self::find($pageSlug, $productKey) !== null) {
            return;
        }

        $source = self::find($pageSlug, LegalProductSettings::DEFAULT_PRODUCT_KEY);
        $html = $source !== null ? self::extractHtmlFromLayout($source['layout']) : '';

        self::saveHtmlVariant(
            $pageSlug,
            $productKey,
            $label,
            WebsitePageRepository::STATUS_DRAFT,
            $html,
            self::nextSortOrder($pageSlug),
            false
        );
    }

    public static function saveHtmlVariant(
        string $pageSlug,
        string $productKey,
        string $label,
        string $status,
        string $html,
        int $sortOrder = 0,
        bool $allowEmpty = true
    ): int {
        if (!Database::isConfigured()) {
            throw new RuntimeException('Datenbank nicht verbunden.');
        }
        MigrationRunner::runPending();

        $pageSlug = LegalProductSettings::sanitizeSlug($pageSlug);
        $productKey = LegalProductSettings::sanitizeProductKey($productKey);
        if ($pageSlug === '' || $productKey === '') {
            throw new InvalidArgumentException('Ungültige Rechtstext-Variante.');
        }

        $label = trim($label);
        if ($label === '') {
            $label = ucfirst($productKey);
        }

        $status = self::sanitizeStatus($status);
        if (!$allowEmpty && trim($html) === '') {
            throw new InvalidArgumentException('Inhalt darf nicht leer sein.');
        }

        $layout = self::layoutFromHtml($html);
        $layoutJson = json_encode($layout, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        $existing = self::find($pageSlug, $productKey);

        if ($existing !== null) {
            $stmt = Database::pdo()->prepare(
                'UPDATE dg_website_legal_variants
                 SET label = :label, status = :status, layout_json = :layout_json, sort_order = :sort_order
                 WHERE id = :id'
            );
            $stmt->execute([
                'label' => $label,
                'status' => $status,
                'layout_json' => $layoutJson,
                'sort_order' => $sortOrder,
                'id' => $existing['id'],
            ]);

            return (int) $existing['id'];
        }

        $stmt = Database::pdo()->prepare(
            'INSERT INTO dg_website_legal_variants (page_slug, product_key, label, status, layout_json, sort_order)
             VALUES (:page_slug, :product_key, :label, :status, :layout_json, :sort_order)'
        );
        $stmt->execute([
            'page_slug' => $pageSlug,
            'product_key' => $productKey,
            'label' => $label,
            'status' => $status,
            'layout_json' => $layoutJson,
            'sort_order' => $sortOrder,
        ]);

        return (int) Database::pdo()->lastInsertId();
    }

    /**
     * @param array{rows?: list<array<string, mixed>>} $layout
     */
    public static function extractHtmlFromLayout(array $layout): string
    {
        foreach ($layout['rows'] ?? [] as $row) {
            foreach ($row['columns'] ?? [] as $column) {
                foreach ($column['blocks'] ?? [] as $block) {
                    if (($block['type'] ?? '') === 'html') {
                        return (string) ($block['code'] ?? '');
                    }
                }
            }
        }

        return '';
    }

    /**
     * @return array{rows: list<array<string, mixed>>}
     */
    public static function layoutFromHtml(string $html): array
    {
        return [
            'rows' => [
                [
                    'id' => self::newId('row'),
                    'columns' => [
                        [
                            'id' => self::newId('col'),
                            'width' => 12,
                            'blocks' => [
                                [
                                    'id' => self::newId('blk'),
                                    'type' => 'html',
                                    'code' => $html,
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * @param array<string, mixed> $row
     * @return array{id: int, page_slug: string, product_key: string, label: string, status: string, layout: array, sort_order: int}
     */
    private static function map(array $row): array
    {
        $layout = WebsitePageRepository::emptyLayout();
        $raw = (string) ($row['layout_json'] ?? '');
        if ($raw !== '') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded) && isset($decoded['rows'])) {
                $layout = WebsiteContent::normalizeLayout($decoded);
            }
        }

        return [
            'id' => (int) ($row['id'] ?? 0),
            'page_slug' => (string) ($row['page_slug'] ?? ''),
            'product_key' => (string) ($row['product_key'] ?? ''),
            'label' => (string) ($row['label'] ?? ''),
            'status' => self::sanitizeStatus((string) ($row['status'] ?? WebsitePageRepository::STATUS_DRAFT)),
            'layout' => $layout,
            'sort_order' => (int) ($row['sort_order'] ?? 0),
        ];
    }

    private static function sanitizeStatus(string $status): string
    {
        $status = strtolower(trim($status));

        return in_array($status, [
            WebsitePageRepository::STATUS_DRAFT,
            WebsitePageRepository::STATUS_PUBLISHED,
            WebsitePageRepository::STATUS_PRIVATE,
        ], true) ? $status : WebsitePageRepository::STATUS_DRAFT;
    }

    private static function nextSortOrder(string $pageSlug): int
    {
        $variants = self::listForPage($pageSlug);
        if ($variants === []) {
            return 0;
        }

        return max(array_column($variants, 'sort_order')) + 1;
    }

    private static function newId(string $prefix): string
    {
        return $prefix . '-' . bin2hex(random_bytes(4));
    }
}
