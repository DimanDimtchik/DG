<?php
declare(strict_types=1);

/**
 * Persistence and helpers for public website pages (CMS builder layouts).
 *
 * Status values: draft, published, private (logged-in only).
 */
final class WebsitePageRepository
{
    public const STATUS_DRAFT = 'draft';
    public const STATUS_PUBLISHED = 'published';
    public const STATUS_PRIVATE = 'private';

    /**
     * Human-readable status labels for the editor UI.
     *
     * @return array<string, string> status code => German label
     */
    public static function statusOptions(): array
    {
        return [
            self::STATUS_DRAFT => 'Entwurf',
            self::STATUS_PUBLISHED => 'Veröffentlicht',
            self::STATUS_PRIVATE => 'Nur eingeloggt',
        ];
    }

    /**
     * Default builder layout for a newly created page.
     *
     * @return array{rows: list<array<string, mixed>>}
     */
    public static function emptyLayout(): array
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
                                    'type' => 'heading',
                                    'text' => 'Neue Seite',
                                    'level' => 'h1',
                                ],
                                [
                                    'id' => self::newId('blk'),
                                    'type' => 'text',
                                    'text' => 'Beschreiben Sie hier Ihr Angebot. Weitere Blöcke fügen Sie links hinzu.',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * Empty form values for create/edit screens.
     *
     * @return array{title: string, slug: string, status: string, layout: array{rows: list<array<string, mixed>>}}
     */
    public static function emptyForm(): array
    {
        return [
            'title' => '',
            'slug' => '',
            'status' => self::STATUS_DRAFT,
            'layout' => self::emptyLayout(),
        ];
    }

    /**
     * Published pages for sitemap and SEO (slug + updated_at).
     *
     * @return list<array{slug: string, updated_at: string}>
     */
    public static function listAll(): array
    {
        if (!Database::isConfigured()) {
            return [];
        }
        MigrationRunner::runPending();

        $stmt = Database::pdo()->query(
            "SELECT slug, updated_at FROM dg_website_pages WHERE status = 'published' ORDER BY title ASC, id ASC"
        );
        $rows = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];

        return is_array($rows) ? $rows : [];
    }

    /**
     * Create or update a page whose body is a single HTML block (legal pages, generated content).
     *
     * @return array{slug: string, title: string, id: int, action: string}
     */
    public static function upsertHtmlPage(
        string $slug,
        string $title,
        string $html,
        ?int $userId = null,
        bool $overwrite = true,
        string $status = self::STATUS_PUBLISHED
    ): array {
        $slug = self::sanitizeSlug($slug);
        if ($slug === '') {
            throw new InvalidArgumentException('Ungültiger Seiten-Slug.');
        }

        $existing = self::findBySlugAnyStatus($slug);
        if ($existing !== null && !$overwrite) {
            return [
                'slug' => $slug,
                'title' => $title,
                'id' => (int) $existing['id'],
                'action' => 'skipped',
            ];
        }

        $layout = [
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

        $id = self::save([
            'title' => $title,
            'slug' => $slug,
            'status' => $status,
            'layout' => $layout,
        ], $existing !== null ? (int) $existing['id'] : null, $userId);

        return [
            'slug' => $slug,
            'title' => $title,
            'id' => $id,
            'action' => $existing !== null ? 'updated' : 'created',
        ];
    }

    /**
     * List all pages (summary columns only).
     *
     * @return list<array<string, mixed>>
     */
    public static function list(): array
    {
        if (!Database::isConfigured()) {
            return [];
        }
        MigrationRunner::runPending();

        $stmt = Database::pdo()->query(
            'SELECT id, title, slug, status, updated_at FROM dg_website_pages ORDER BY title ASC, id ASC'
        );
        $rows = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];

        return is_array($rows) ? $rows : [];
    }

    /**
     * Resolve a public page by slug: published for everyone, private only when logged in.
     *
     * @return array<string, mixed>|null Mapped page or null if not found / not visible
     */
    public static function findBySlug(string $slug): ?array
    {
        $slug = trim($slug);
        if ($slug === '' || !Database::isConfigured()) {
            return null;
        }
        MigrationRunner::runPending();

        $stmt = Database::pdo()->prepare(
            'SELECT * FROM dg_website_pages WHERE slug = :slug AND status = :status LIMIT 1'
        );
        $stmt->execute(['slug' => $slug, 'status' => self::STATUS_PUBLISHED]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            return self::map($row);
        }

        // Private pages: only for logged-in users
        if (!class_exists('AuthService') || !AuthService::check()) {
            return null;
        }
        $stmt->execute(['slug' => $slug, 'status' => self::STATUS_PRIVATE]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? self::map($row) : null;
    }

    /**
     * Find a page by slug regardless of status (editor / preview).
     *
     * @return array<string, mixed>|null
     */
    public static function findBySlugAnyStatus(string $slug): ?array
    {
        $slug = trim($slug);
        if ($slug === '' || !Database::isConfigured()) {
            return null;
        }
        MigrationRunner::runPending();

        $stmt = Database::pdo()->prepare('SELECT * FROM dg_website_pages WHERE slug = :slug LIMIT 1');
        $stmt->execute(['slug' => $slug]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? self::map($row) : null;
    }

    /**
     * Homepage page (slug startseite), respecting visibility rules of findBySlug().
     *
     * @return array<string, mixed>|null
     */
    public static function findHomepage(): ?array
    {
        return self::findBySlug('startseite');
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function findById(int $id): ?array
    {
        if ($id < 1 || !Database::isConfigured()) {
            return null;
        }
        MigrationRunner::runPending();

        $stmt = Database::pdo()->prepare('SELECT * FROM dg_website_pages WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? self::map($row) : null;
    }

    /**
     * Create or update a page.
     *
     * @param array<string, mixed> $data title, slug, status, layout (array or JSON string)
     * @param int|null $id Existing page id, or null to insert
     * @param int|null $userId Creator user id on insert
     * @return int Saved page id
     *
     * @throws RuntimeException When the database is not configured
     * @throws InvalidArgumentException On validation errors
     */
    public static function save(array $data, ?int $id = null, ?int $userId = null): int
    {
        if (!Database::isConfigured()) {
            throw new RuntimeException('Datenbank nicht verbunden.');
        }
        MigrationRunner::runPending();

        $title = trim((string) ($data['title'] ?? ''));
        if ($title === '') {
            throw new InvalidArgumentException('Bitte einen Seitentitel angeben.');
        }

        $slug = self::sanitizeSlug((string) ($data['slug'] ?? ''));
        if ($slug === '') {
            $slug = self::sanitizeSlug($title);
        }
        if ($slug === '') {
            throw new InvalidArgumentException('Bitte eine URL (Slug) angeben.');
        }
        if (self::slugExists($slug, $id)) {
            throw new InvalidArgumentException('Diese URL wird bereits von einer anderen Seite verwendet.');
        }

        $status = (string) ($data['status'] ?? self::STATUS_DRAFT);
        if (!isset(self::statusOptions()[$status])) {
            $status = self::STATUS_DRAFT;
        }

        $layout = $data['layout'] ?? null;
        if (is_string($layout)) {
            $decoded = json_decode($layout, true);
            $layout = is_array($decoded) ? $decoded : self::emptyLayout();
        }
        if (!is_array($layout) || !isset($layout['rows']) || !is_array($layout['rows'])) {
            $layout = self::emptyLayout();
        }
        $layoutChanged = false;
        $layout = WebsiteFormRepository::convertContactBlocksInLayout($layout, $userId, $layoutChanged);
        $layout = WebsiteContent::normalizeLayout($layout);

        $layoutJson = json_encode($layout, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        $pdo = Database::pdo();

        if ($id !== null && $id > 0) {
            $stmt = $pdo->prepare(
                'UPDATE dg_website_pages SET title = :title, slug = :slug, status = :status, layout_json = :layout_json
                 WHERE id = :id'
            );
            $stmt->execute([
                'title' => $title,
                'slug' => $slug,
                'status' => $status,
                'layout_json' => $layoutJson,
                'id' => $id,
            ]);

            return $id;
        }

        $stmt = $pdo->prepare(
            'INSERT INTO dg_website_pages (title, slug, status, layout_json, created_by)
             VALUES (:title, :slug, :status, :layout_json, :created_by)'
        );
        $stmt->execute([
            'title' => $title,
            'slug' => $slug,
            'status' => $status,
            'layout_json' => $layoutJson,
            'created_by' => $userId,
        ]);

        return (int) $pdo->lastInsertId();
    }

    /**
     * Delete a page by id (no-op if id invalid or DB missing).
     */
    public static function delete(int $id): void
    {
        if ($id < 1 || !Database::isConfigured()) {
            return;
        }
        MigrationRunner::runPending();
        Database::pdo()->prepare('DELETE FROM dg_website_pages WHERE id = :id')->execute(['id' => $id]);
    }

    /**
     * Normalize a free-text slug to ASCII kebab-case.
     */
    public static function sanitizeSlug(string $value): string
    {
        $value = strtolower(trim($value));
        $value = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value) ?: $value;
        $value = preg_replace('/[^a-z0-9]+/', '-', $value) ?? '';

        return trim($value, '-');
    }

    /**
     * Public URL path for a page slug (`/` for homepage).
     */
    public static function publicPath(string $slug): string
    {
        $slug = self::sanitizeSlug($slug);
        if ($slug === '' || $slug === 'startseite') {
            return '/';
        }

        return '/' . $slug;
    }

    /**
     * Pages that are not yet linked in the given menu (including nested children).
     *
     * @param array{items?: list<array{label?: string, url?: string, children?: list<array<string, mixed>>}>} $menu
     * @return list<array{title: string, slug: string, url: string, status: string}>
     */
    public static function unusedInMenu(array $menu): array
    {
        $used = [];
        $collect = static function (array $items) use (&$used, &$collect): void {
            foreach ($items as $item) {
                if (!is_array($item)) {
                    continue;
                }
                $url = trim((string) ($item['url'] ?? ''));
                if ($url !== '' && $url !== '#') {
                    $used[rtrim($url, '/') ?: '/'] = true;
                }
                $children = $item['children'] ?? [];
                if (is_array($children) && $children !== []) {
                    $collect($children);
                }
            }
        };
        $collect($menu['items'] ?? []);

        $unused = [];
        foreach (self::list() as $page) {
            $url = self::publicPath((string) ($page['slug'] ?? ''));
            $key = rtrim($url, '/') ?: '/';
            if (isset($used[$key])) {
                continue;
            }
            $unused[] = [
                'title' => (string) ($page['title'] ?? ''),
                'slug' => (string) ($page['slug'] ?? ''),
                'url' => $url,
                'status' => (string) ($page['status'] ?? self::STATUS_DRAFT),
            ];
        }

        return $unused;
    }

    /**
     * @param int|null $excludeId Page id to ignore (current page when renaming slug)
     */
    private static function slugExists(string $slug, ?int $excludeId): bool
    {
        $sql = 'SELECT id FROM dg_website_pages WHERE slug = :slug';
        $params = ['slug' => $slug];
        if ($excludeId !== null && $excludeId > 0) {
            $sql .= ' AND id <> :id';
            $params['id'] = $excludeId;
        }
        $stmt = Database::pdo()->prepare($sql . ' LIMIT 1');
        $stmt->execute($params);

        return (bool) $stmt->fetchColumn();
    }

    /**
     * Map a DB row to the editor/public page structure (decodes layout JSON).
     *
     * @param array<string, mixed> $row
     * @return array{id: int, title: string, slug: string, status: string, layout: array<string, mixed>, updated_at: string}
     */
    private static function map(array $row): array
    {
        $layout = self::emptyLayout();
        $raw = (string) ($row['layout_json'] ?? '');
        if ($raw !== '') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded) && isset($decoded['rows']) && is_array($decoded['rows'])) {
                $layout = WebsiteContent::normalizeLayout($decoded);
            }
        }

        return [
            'id' => (int) ($row['id'] ?? 0),
            'title' => (string) ($row['title'] ?? ''),
            'slug' => (string) ($row['slug'] ?? ''),
            'status' => (string) ($row['status'] ?? self::STATUS_DRAFT),
            'layout' => $layout,
            'updated_at' => (string) ($row['updated_at'] ?? ''),
        ];
    }

    /**
     * Generate a short unique id for builder rows/columns/blocks.
     */
    private static function newId(string $prefix): string
    {
        return $prefix . '-' . bin2hex(random_bytes(4));
    }
}
