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

    public const CHANNEL_XING = 'xing';
    public const CHANNEL_LINKEDIN = 'linkedin';

    public const PUBLISH_NONE = 'none';
    public const PUBLISH_QUEUED = 'queued';
    public const PUBLISH_PUBLISHED = 'published';
    public const PUBLISH_ERROR = 'error';
    public const PUBLISH_WITHDRAWN = 'withdrawn';

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

    /** Migration 137: Export-Flags + Publish-Status. */
    public static function publishChannelsReady(): bool
    {
        if (!self::tableReady()) {
            return false;
        }
        try {
            Database::pdo()->query('SELECT export_xing, xing_publish_status FROM dg_job_postings LIMIT 1');

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    public static function publishEventsReady(): bool
    {
        if (!Database::isConfigured()) {
            return false;
        }
        try {
            Database::pdo()->query('SELECT 1 FROM dg_job_posting_publish_events LIMIT 1');

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
     * @return array<string, string>
     */
    public static function channelOptions(): array
    {
        return [
            self::CHANNEL_XING => 'Xing',
            self::CHANNEL_LINKEDIN => 'LinkedIn',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function publishStatusOptions(): array
    {
        return [
            self::PUBLISH_NONE => 'Nicht geplant',
            self::PUBLISH_QUEUED => 'Vorgemerkt',
            self::PUBLISH_PUBLISHED => 'Auf Plattform veröffentlicht',
            self::PUBLISH_ERROR => 'Fehler',
            self::PUBLISH_WITHDRAWN => 'Zurückgezogen',
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
     * Öffentliche Stellenliste (nur veröffentlicht).
     *
     * @return list<array<string, mixed>>
     */
    public static function listPublished(int $limit = 100): array
    {
        self::ensureReady();
        if (!Database::isConfigured() || !self::tableReady()) {
            return [];
        }
        $limit = max(1, min(200, $limit));
        $stmt = Database::pdo()->prepare(
            'SELECT id, title, slug, summary, location, employment_type, published_at, updated_at
             FROM dg_job_postings
             WHERE status = :status
             ORDER BY published_at DESC, id DESC
             LIMIT ' . $limit
        );
        $stmt->execute(['status' => self::STATUS_PUBLISHED]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return is_array($rows) ? array_values(array_filter($rows, 'is_array')) : [];
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function findPublishedBySlug(string $slug): ?array
    {
        self::ensureReady();
        $slug = trim($slug);
        if ($slug === '' || !Database::isConfigured() || !self::tableReady()) {
            return null;
        }
        $stmt = Database::pdo()->prepare(
            'SELECT * FROM dg_job_postings
             WHERE slug = :slug AND status = :status
             LIMIT 1'
        );
        $stmt->execute([
            'slug' => $slug,
            'status' => self::STATUS_PUBLISHED,
        ]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    public static function publicUrl(string $slug = ''): string
    {
        $base = rtrim(App::publicBaseUrl(), '/') . '/rekrut';
        $slug = trim($slug);

        return $slug === '' ? $base : $base . '/' . rawurlencode($slug);
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
            'export_xing' => '0',
            'export_linkedin' => '0',
            'xing_publish_status' => self::PUBLISH_NONE,
            'linkedin_publish_status' => self::PUBLISH_NONE,
            'xing_last_message' => '',
            'linkedin_last_message' => '',
            'xing_published_at' => '',
            'linkedin_published_at' => '',
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
            'export_xing' => !empty($row['export_xing']) ? '1' : '0',
            'export_linkedin' => !empty($row['export_linkedin']) ? '1' : '0',
            'xing_publish_status' => self::sanitizePublishStatus((string) ($row['xing_publish_status'] ?? self::PUBLISH_NONE)),
            'linkedin_publish_status' => self::sanitizePublishStatus((string) ($row['linkedin_publish_status'] ?? self::PUBLISH_NONE)),
            'xing_last_message' => (string) ($row['xing_last_message'] ?? ''),
            'linkedin_last_message' => (string) ($row['linkedin_last_message'] ?? ''),
            'xing_published_at' => (string) ($row['xing_published_at'] ?? ''),
            'linkedin_published_at' => (string) ($row['linkedin_published_at'] ?? ''),
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
        $existing = null;
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

        $channelsReady = self::publishChannelsReady();
        $exportXing = !empty($input['export_xing']) ? 1 : 0;
        $exportLinkedin = !empty($input['export_linkedin']) ? 1 : 0;
        $prev = ($id !== null && $id > 0) ? ($existing ?? self::find($id)) : null;

        $xingStatus = self::PUBLISH_NONE;
        $linkedinStatus = self::PUBLISH_NONE;
        $xingMsg = '';
        $linkedinMsg = '';
        $xingPublishedAt = null;
        $linkedinPublishedAt = null;
        $xingRef = '';
        $linkedinRef = '';
        if (is_array($prev) && $channelsReady) {
            $xingStatus = self::sanitizePublishStatus((string) ($prev['xing_publish_status'] ?? self::PUBLISH_NONE));
            $linkedinStatus = self::sanitizePublishStatus((string) ($prev['linkedin_publish_status'] ?? self::PUBLISH_NONE));
            $xingMsg = (string) ($prev['xing_last_message'] ?? '');
            $linkedinMsg = (string) ($prev['linkedin_last_message'] ?? '');
            $xingPublishedAt = $prev['xing_published_at'] ?? null;
            $linkedinPublishedAt = $prev['linkedin_published_at'] ?? null;
            $xingRef = (string) ($prev['xing_external_ref'] ?? '');
            $linkedinRef = (string) ($prev['linkedin_external_ref'] ?? '');
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
            if ($channelsReady) {
                $xingPlan = self::planChannelState(
                    $exportXing === 1,
                    $status,
                    $xingStatus,
                    (string) ($prev['export_xing'] ?? '0') === '1' || !empty($prev['export_xing']),
                    'Xing'
                );
                $liPlan = self::planChannelState(
                    $exportLinkedin === 1,
                    $status,
                    $linkedinStatus,
                    (string) ($prev['export_linkedin'] ?? '0') === '1' || !empty($prev['export_linkedin']),
                    'LinkedIn'
                );
                $fields['export_xing'] = $exportXing;
                $fields['export_linkedin'] = $exportLinkedin;
                $fields['xing_publish_status'] = $xingPlan['status'];
                $fields['linkedin_publish_status'] = $liPlan['status'];
                $fields['xing_last_message'] = $xingPlan['message'] !== '' ? $xingPlan['message'] : $xingMsg;
                $fields['linkedin_last_message'] = $liPlan['message'] !== '' ? $liPlan['message'] : $linkedinMsg;
                $fields['xing_published_at'] = $xingPublishedAt;
                $fields['linkedin_published_at'] = $linkedinPublishedAt;
                $fields['xing_external_ref'] = $xingRef;
                $fields['linkedin_external_ref'] = $linkedinRef;
                if ($xingPlan['status'] === self::PUBLISH_WITHDRAWN) {
                    // published_at behalten für Historie
                }
                $stmt = $pdo->prepare(
                    'UPDATE dg_job_postings SET
                        title = :title, slug = :slug, summary = :summary, body = :body,
                        location = :location, employment_type = :employment_type, status = :status,
                        department_id = :department_id, published_at = :published_at, closed_at = :closed_at,
                        export_xing = :export_xing, export_linkedin = :export_linkedin,
                        xing_publish_status = :xing_publish_status, linkedin_publish_status = :linkedin_publish_status,
                        xing_published_at = :xing_published_at, linkedin_published_at = :linkedin_published_at,
                        xing_external_ref = :xing_external_ref, linkedin_external_ref = :linkedin_external_ref,
                        xing_last_message = :xing_last_message, linkedin_last_message = :linkedin_last_message
                     WHERE id = :id'
                );
                $stmt->execute($fields);
                self::logChannelPlan($id, self::CHANNEL_XING, $xingStatus, $xingPlan, $userId);
                self::logChannelPlan($id, self::CHANNEL_LINKEDIN, $linkedinStatus, $liPlan, $userId);
            } else {
                $stmt = $pdo->prepare(
                    'UPDATE dg_job_postings SET
                        title = :title, slug = :slug, summary = :summary, body = :body,
                        location = :location, employment_type = :employment_type, status = :status,
                        department_id = :department_id, published_at = :published_at, closed_at = :closed_at
                     WHERE id = :id'
                );
                $stmt->execute($fields);
            }

            return $id;
        }

        if ($channelsReady) {
            $xingPlan = self::planChannelState($exportXing === 1, $status, self::PUBLISH_NONE, false, 'Xing');
            $liPlan = self::planChannelState($exportLinkedin === 1, $status, self::PUBLISH_NONE, false, 'LinkedIn');
            $stmt = $pdo->prepare(
                'INSERT INTO dg_job_postings (
                    title, slug, summary, body, location, employment_type, status,
                    department_id, published_at, closed_at, created_by,
                    export_xing, export_linkedin, xing_publish_status, linkedin_publish_status,
                    xing_last_message, linkedin_last_message
                ) VALUES (
                    :title, :slug, :summary, :body, :location, :employment_type, :status,
                    :department_id, :published_at, :closed_at, :created_by,
                    :export_xing, :export_linkedin, :xing_publish_status, :linkedin_publish_status,
                    :xing_last_message, :linkedin_last_message
                )'
            );
            $stmt->execute([
                ...$fields,
                'created_by' => ($userId !== null && $userId > 0) ? $userId : null,
                'export_xing' => $exportXing,
                'export_linkedin' => $exportLinkedin,
                'xing_publish_status' => $xingPlan['status'],
                'linkedin_publish_status' => $liPlan['status'],
                'xing_last_message' => $xingPlan['message'],
                'linkedin_last_message' => $liPlan['message'],
            ]);
            $newId = (int) $pdo->lastInsertId();
            self::logChannelPlan($newId, self::CHANNEL_XING, self::PUBLISH_NONE, $xingPlan, $userId);
            self::logChannelPlan($newId, self::CHANNEL_LINKEDIN, self::PUBLISH_NONE, $liPlan, $userId);

            return $newId;
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

    /**
     * @return array{status: string, message: string, event_kind: string, changed: bool}
     */
    public static function planChannelState(
        bool $wantExport,
        string $jobStatus,
        string $prevPublishStatus,
        bool $prevWantExport,
        string $channelLabel
    ): array {
        $prevPublishStatus = self::sanitizePublishStatus($prevPublishStatus);
        $applyUrlHint = 'Bewerben-Link bleibt die eigene Seite (/rekrut/…).';

        if (!$wantExport) {
            if (!$prevWantExport && $prevPublishStatus === self::PUBLISH_NONE) {
                return [
                    'status' => self::PUBLISH_NONE,
                    'message' => '',
                    'event_kind' => 'note',
                    'changed' => false,
                ];
            }
            if (in_array($prevPublishStatus, [self::PUBLISH_QUEUED, self::PUBLISH_PUBLISHED, self::PUBLISH_ERROR], true)
                || $prevWantExport
            ) {
                return [
                    'status' => self::PUBLISH_WITHDRAWN,
                    'message' => $channelLabel . ': Export abgewählt.',
                    'event_kind' => 'withdrawn',
                    'changed' => $prevPublishStatus !== self::PUBLISH_WITHDRAWN || $prevWantExport,
                ];
            }

            return [
                'status' => $prevPublishStatus,
                'message' => '',
                'event_kind' => 'note',
                'changed' => false,
            ];
        }

        if ($jobStatus !== self::STATUS_PUBLISHED) {
            $msg = $channelLabel . ': Export gewünscht — Stelle zuerst auf „Veröffentlicht“ setzen.';
            if ($prevPublishStatus === self::PUBLISH_QUEUED || $prevPublishStatus === self::PUBLISH_PUBLISHED) {
                return [
                    'status' => self::PUBLISH_WITHDRAWN,
                    'message' => $channelLabel . ': Stelle nicht mehr öffentlich — Plattform-Export pausiert.',
                    'event_kind' => 'withdrawn',
                    'changed' => true,
                ];
            }

            return [
                'status' => self::PUBLISH_NONE,
                'message' => $msg,
                'event_kind' => $prevWantExport && $prevPublishStatus === self::PUBLISH_NONE ? 'note' : 'flag',
                'changed' => !$prevWantExport || $prevPublishStatus !== self::PUBLISH_NONE,
            ];
        }

        // Stelle veröffentlicht + Export an → vorgemerkt (API folgt in Phase B)
        if (in_array($prevPublishStatus, [self::PUBLISH_PUBLISHED, self::PUBLISH_QUEUED], true) && $prevWantExport) {
            return [
                'status' => $prevPublishStatus,
                'message' => '',
                'event_kind' => 'note',
                'changed' => false,
            ];
        }

        return [
            'status' => self::PUBLISH_QUEUED,
            'message' => $channelLabel . ': zur Veröffentlichung vorgemerkt (Plattform-API folgt). ' . $applyUrlHint,
            'event_kind' => 'queued',
            'changed' => true,
        ];
    }

    /**
     * @param array{status: string, message: string, event_kind: string, changed: bool} $plan
     */
    private static function logChannelPlan(
        int $jobId,
        string $channel,
        string $fromStatus,
        array $plan,
        ?int $userId
    ): void {
        if ($jobId < 1 || empty($plan['changed'])) {
            return;
        }
        self::addPublishEvent(
            $jobId,
            $channel,
            (string) ($plan['event_kind'] ?? 'note'),
            $fromStatus,
            (string) ($plan['status'] ?? self::PUBLISH_NONE),
            (string) ($plan['message'] ?? ''),
            $userId
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function publishEvents(int $jobPostingId, int $limit = 100): array
    {
        self::ensureReady();
        if ($jobPostingId < 1 || !self::publishEventsReady()) {
            return [];
        }
        $limit = max(1, min(200, $limit));
        $stmt = Database::pdo()->prepare(
            'SELECT * FROM dg_job_posting_publish_events
             WHERE job_posting_id = :id
             ORDER BY id DESC
             LIMIT ' . $limit
        );
        $stmt->execute(['id' => $jobPostingId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return is_array($rows) ? array_values(array_filter($rows, 'is_array')) : [];
    }

    public static function addPublishEvent(
        int $jobPostingId,
        string $channel,
        string $kind,
        ?string $fromStatus,
        ?string $toStatus,
        string $body,
        ?int $userId
    ): void {
        if ($jobPostingId < 1 || !self::publishEventsReady()) {
            return;
        }
        $channels = self::channelOptions();
        if (!isset($channels[$channel])) {
            $channel = self::CHANNEL_XING;
        }
        $stmt = Database::pdo()->prepare(
            'INSERT INTO dg_job_posting_publish_events
                (job_posting_id, channel, event_kind, from_status, to_status, body, created_by)
             VALUES
                (:job_id, :channel, :kind, :from_status, :to_status, :body, :by)'
        );
        $stmt->execute([
            'job_id' => $jobPostingId,
            'channel' => $channel,
            'kind' => mb_substr($kind, 0, 32),
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
            'body' => $body,
            'by' => ($userId !== null && $userId > 0) ? $userId : null,
        ]);
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

    public static function sanitizePublishStatus(string $status): string
    {
        $opts = self::publishStatusOptions();

        return isset($opts[$status]) ? $status : self::PUBLISH_NONE;
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
