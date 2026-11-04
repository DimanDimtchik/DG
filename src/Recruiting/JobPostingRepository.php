<?php
declare(strict_types=1);

/** Stellenanzeigen für Recruiting (CRM + später öffentliche /rekrut-Seite). */
final class JobPostingRepository
{
    public const STATUS_DRAFT = 'draft';
    public const STATUS_PUBLISHED = 'published';
    public const STATUS_CLOSED = 'closed';
    public const STATUS_ARCHIVED = 'archived';

    public const TYPE_FULL_TIME = 'full_time';
    public const TYPE_PART_TIME = 'part_time';
    public const TYPE_MINI = 'mini';
    public const TYPE_INTERNSHIP = 'internship';
    public const TYPE_OTHER = 'other';

    public static function ensureReady(): void
    {
        if (!Database::isConfigured()) {
            return;
        }
        MigrationRunner::runPending();
    }

    public static function tableReady(): bool
    {
        if (!Database::isConfigured()) {
            return false;
        }
        try {
            Database::pdo()->query('SELECT 1 FROM dg_job_postings LIMIT 1');

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * @return array<string, string>
     */
    public static function statusOptions(): array
    {
        return [
            self::STATUS_DRAFT => 'Entwurf',
            self::STATUS_PUBLISHED => 'Veröffentlicht',
            self::STATUS_CLOSED => 'Geschlossen',
            self::STATUS_ARCHIVED => 'Archiviert',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function employmentTypeOptions(): array
    {
        return [
            self::TYPE_FULL_TIME => 'Vollzeit',
            self::TYPE_PART_TIME => 'Teilzeit',
            self::TYPE_MINI => 'Minijob',
            self::TYPE_INTERNSHIP => 'Praktikum / Ausbildung',
            self::TYPE_OTHER => 'Sonstiges',
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function listAll(int $limit = 200): array
    {
        self::ensureReady();
        if (!Database::isConfigured() || !self::tableReady()) {
            return [];
        }
        $limit = max(1, min(500, $limit));
        $stmt = Database::pdo()->query(
            'SELECT p.*,
                    (SELECT COUNT(*) FROM dg_job_applications a WHERE a.job_posting_id = p.id) AS application_count
             FROM dg_job_postings p
             ORDER BY
                CASE p.status
                    WHEN \'published\' THEN 0
                    WHEN \'draft\' THEN 1
                    WHEN \'closed\' THEN 2
                    ELSE 3
                END,
                p.updated_at DESC
             LIMIT ' . $limit
        );
        $rows = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];

        return is_array($rows) ? array_values(array_filter($rows, 'is_array')) : [];
    }

    /**
     * @return list<array{id: int, title: string}>
     */
    public static function optionsForSelect(bool $onlyOpen = false): array
    {
        self::ensureReady();
        if (!Database::isConfigured() || !self::tableReady()) {
            return [];
        }
        $sql = 'SELECT id, title FROM dg_job_postings';
        if ($onlyOpen) {
            $sql .= " WHERE status IN ('draft','published')";
        }
        $sql .= ' ORDER BY title ASC';
        $stmt = Database::pdo()->query($sql);
        $rows = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
        $out = [];
        foreach (is_array($rows) ? $rows : [] as $row) {
            if (!is_array($row)) {
                continue;
            }
            $out[] = [
                'id' => (int) ($row['id'] ?? 0),
                'title' => (string) ($row['title'] ?? ''),
            ];
        }

        return $out;
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function find(int $id): ?array
    {
        self::ensureReady();
        if (!Database::isConfigured() || !self::tableReady() || $id < 1) {
            return null;
        }
        $stmt = Database::pdo()->prepare('SELECT * FROM dg_job_postings WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    /**
     * @return array<string, mixed>
     */
    public static function emptyForm(): array
    {
        return [
            'title' => '',
            'slug' => '',
            'summary' => '',
            'body' => '',
            'location' => '',
            'employment_type' => self::TYPE_FULL_TIME,
            'status' => self::STATUS_DRAFT,
            'department_id' => '',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function formForId(int $id): array
    {
        $row = self::find($id);
        if ($row === null) {
            return self::emptyForm();
        }

        return [
            'title' => (string) ($row['title'] ?? ''),
            'slug' => (string) ($row['slug'] ?? ''),
            'summary' => (string) ($row['summary'] ?? ''),
            'body' => (string) ($row['body'] ?? ''),
            'location' => (string) ($row['location'] ?? ''),
            'employment_type' => self::sanitizeEmploymentType((string) ($row['employment_type'] ?? self::TYPE_FULL_TIME)),
            'status' => self::sanitizeStatus((string) ($row['status'] ?? self::STATUS_DRAFT)),
            'department_id' => (string) ($row['department_id'] ?? ''),
        ];
    }

    /**
     * @param array<string, mixed> $input
     */
    public static function save(array $input, ?int $id = null, ?int $userId = null): int
    {
        self::ensureReady();
        if (!Database::isConfigured() || !self::tableReady()) {
            throw new RuntimeException('Stellen-Tabelle fehlt — Migration 136 ausführen.');
        }

        $title = trim((string) ($input['title'] ?? ''));
        if ($title === '') {
            throw new InvalidArgumentException('Titel der Stelle ist erforderlich.');
        }

        $status = self::sanitizeStatus((string) ($input['status'] ?? self::STATUS_DRAFT));
        $employmentType = self::sanitizeEmploymentType((string) ($input['employment_type'] ?? self::TYPE_FULL_TIME));
        $slugInput = trim((string) ($input['slug'] ?? ''));
        $slug = self::uniqueSlug($slugInput !== '' ? $slugInput : $title, $id);
        $departmentId = trim((string) ($input['department_id'] ?? ''));
        if ($departmentId === '') {
            $departmentId = null;
        }

        $publishedAt = null;
        $closedAt = null;
        if ($id !== null && $id > 0) {
            $existing = self::find($id);
            if ($existing === null) {
                throw new InvalidArgumentException('Stelle nicht gefunden.');
            }
            $prevStatus = (string) ($existing['status'] ?? '');
            $publishedAt = $existing['published_at'] ?? null;
            $closedAt = $existing['closed_at'] ?? null;
            if ($status === self::STATUS_PUBLISHED && ($publishedAt === null || $publishedAt === '')) {
                $publishedAt = date('Y-m-d H:i:s');
            }
            if (in_array($status, [self::STATUS_CLOSED, self::STATUS_ARCHIVED], true)
                && ($closedAt === null || $closedAt === '' || $prevStatus !== $status)
            ) {
                $closedAt = date('Y-m-d H:i:s');
            }
            if ($status === self::STATUS_DRAFT || $status === self::STATUS_PUBLISHED) {
                $closedAt = null;
            }
        } else {
            if ($status === self::STATUS_PUBLISHED) {
                $publishedAt = date('Y-m-d H:i:s');
            }
            if (in_array($status, [self::STATUS_CLOSED, self::STATUS_ARCHIVED], true)) {
                $closedAt = date('Y-m-d H:i:s');
            }
        }

        $fields = [
            'title' => mb_substr($title, 0, 191),
            'slug' => $slug,
            'summary' => trim((string) ($input['summary'] ?? '')),
            'body' => trim((string) ($input['body'] ?? '')),
            'location' => mb_substr(trim((string) ($input['location'] ?? '')), 0, 191),
            'employment_type' => $employmentType,
            'status' => $status,
            'department_id' => $departmentId,
            'published_at' => $publishedAt,
            'closed_at' => $closedAt,
        ];

        $pdo = Database::pdo();
        if ($id !== null && $id > 0) {
            $fields['id'] = $id;
            $stmt = $pdo->prepare(
                'UPDATE dg_job_postings SET
                    title = :title, slug = :slug, summary = :summary, body = :body,
                    location = :location, employment_type = :employment_type, status = :status,
                    department_id = :department_id, published_at = :published_at, closed_at = :closed_at
                 WHERE id = :id'
            );
            $stmt->execute($fields);

            return $id;
        }

        $stmt = $pdo->prepare(
            'INSERT INTO dg_job_postings (
                title, slug, summary, body, location, employment_type, status,
                department_id, published_at, closed_at, created_by
            ) VALUES (
                :title, :slug, :summary, :body, :location, :employment_type, :status,
                :department_id, :published_at, :closed_at, :created_by
            )'
        );
        $stmt->execute([
            ...$fields,
            'created_by' => ($userId !== null && $userId > 0) ? $userId : null,
        ]);

        return (int) $pdo->lastInsertId();
    }

    public static function sanitizeStatus(string $status): string
    {
        $opts = self::statusOptions();

        return isset($opts[$status]) ? $status : self::STATUS_DRAFT;
    }

    public static function sanitizeEmploymentType(string $type): string
    {
        $opts = self::employmentTypeOptions();

        return isset($opts[$type]) ? $type : self::TYPE_FULL_TIME;
    }

    public static function slugify(string $value): string
    {
        $s = mb_strtolower(trim($value), 'UTF-8');
        $map = ['ä' => 'ae', 'ö' => 'oe', 'ü' => 'ue', 'ß' => 'ss'];
        $s = strtr($s, $map);
        $s = preg_replace('/[^a-z0-9]+/', '-', $s) ?? '';
        $s = trim($s, '-');
        if ($s === '') {
            $s = 'stelle';
        }

        return mb_substr($s, 0, 180);
    }

    private static function uniqueSlug(string $raw, ?int $ignoreId): string
    {
        $base = self::slugify($raw);
        $slug = $base;
        $n = 2;
        while (self::slugExists($slug, $ignoreId)) {
            $slug = mb_substr($base, 0, 170) . '-' . $n;
            $n++;
            if ($n > 200) {
                $slug = $base . '-' . bin2hex(random_bytes(3));
                break;
            }
        }

        return $slug;
    }

    private static function slugExists(string $slug, ?int $ignoreId): bool
    {
        $sql = 'SELECT id FROM dg_job_postings WHERE slug = :slug';
        $params = ['slug' => $slug];
        if ($ignoreId !== null && $ignoreId > 0) {
            $sql .= ' AND id <> :id';
            $params['id'] = $ignoreId;
        }
        $sql .= ' LIMIT 1';
        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchColumn() !== false;
    }
}
