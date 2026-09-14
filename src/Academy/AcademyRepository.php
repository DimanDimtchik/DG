<?php
declare(strict_types=1);

final class AcademyRepository
{
    /** @return list<array<string, mixed>> */
    public static function allAreas(): array
    {
        if (!Database::isConfigured()) {
            return [];
        }

        $stmt = Database::pdo()->query(
            'SELECT * FROM dg_academy_areas WHERE is_active = 1 ORDER BY sort_order ASC, label ASC'
        );

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /** @return array<string, mixed>|null */
    public static function findCourseById(int $id): ?array
    {
        if ($id < 1 || !Database::isConfigured()) {
            return null;
        }

        $stmt = Database::pdo()->prepare(
            'SELECT c.*, a.code AS area_code, a.label AS area_label
             FROM dg_academy_courses c
             INNER JOIN dg_academy_areas a ON a.id = c.area_id
             WHERE c.id = :id LIMIT 1'
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    /** @return array<string, mixed>|null */
    public static function findCourseBySlug(string $slug): ?array
    {
        $slug = trim($slug);
        if ($slug === '' || !Database::isConfigured()) {
            return null;
        }

        $stmt = Database::pdo()->prepare(
            'SELECT c.*, a.code AS area_code, a.label AS area_label
             FROM dg_academy_courses c
             INNER JOIN dg_academy_areas a ON a.id = c.area_id
             WHERE c.slug = :slug LIMIT 1'
        );
        $stmt->execute(['slug' => $slug]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    /** @return list<array<string, mixed>> */
    public static function publishedCourses(?string $areaCode = null): array
    {
        if (!Database::isConfigured()) {
            return [];
        }

        $sql = 'SELECT c.*, a.code AS area_code, a.label AS area_label
                FROM dg_academy_courses c
                INNER JOIN dg_academy_areas a ON a.id = c.area_id
                WHERE c.is_published = 1';
        $params = [];
        if ($areaCode !== null && $areaCode !== '') {
            $sql .= ' AND a.code = :area_code';
            $params['area_code'] = $areaCode;
        }
        $sql .= ' ORDER BY c.sort_order ASC, c.title ASC';

        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /** @return list<array<string, mixed>> */
    public static function modulesForCourse(int $courseId, bool $activeOnly = true): array
    {
        if ($courseId < 1 || !Database::isConfigured()) {
            return [];
        }

        $sql = 'SELECT * FROM dg_academy_modules WHERE course_id = :course_id';
        if ($activeOnly) {
            $sql .= ' AND is_active = 1';
        }
        $sql .= ' ORDER BY sort_order ASC, id ASC';

        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute(['course_id' => $courseId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /** @return array<string, mixed>|null */
    public static function findModule(int $moduleId): ?array
    {
        if ($moduleId < 1 || !Database::isConfigured()) {
            return null;
        }

        $stmt = Database::pdo()->prepare('SELECT * FROM dg_academy_modules WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $moduleId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    /** @return array<string, mixed>|null */
    public static function findAssignment(int $userId, int $courseId): ?array
    {
        if ($userId < 1 || $courseId < 1 || !Database::isConfigured()) {
            return null;
        }

        $stmt = Database::pdo()->prepare(
            'SELECT * FROM dg_academy_assignments WHERE user_id = :user_id AND course_id = :course_id LIMIT 1'
        );
        $stmt->execute(['user_id' => $userId, 'course_id' => $courseId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    /** @return array<string, mixed> */
    public static function ensureAssignment(int $userId, int $courseId, int $assignedBy = 0, string $accessMode = ''): array
    {
        $existing = self::findAssignment($userId, $courseId);
        if ($existing !== null) {
            return $existing;
        }

        $course = self::findCourseById($courseId);
        if ($course === null) {
            throw new InvalidArgumentException('Kurs nicht gefunden.');
        }

        $mode = $accessMode !== ''
            ? AcademyAccessMode::sanitize($accessMode)
            : AcademyAccessMode::sanitize((string) ($course['access_mode_default'] ?? AcademyAccessMode::COMPARE));

        $stmt = Database::pdo()->prepare(
            'INSERT INTO dg_academy_assignments (user_id, course_id, assigned_by, access_mode, status)
             VALUES (:user_id, :course_id, :assigned_by, :access_mode, :status)'
        );
        $stmt->execute([
            'user_id' => $userId,
            'course_id' => $courseId,
            'assigned_by' => $assignedBy > 0 ? $assignedBy : null,
            'access_mode' => $mode,
            'status' => 'open',
        ]);

        $id = (int) Database::pdo()->lastInsertId();
        $assignment = self::findAssignmentById($id);
        if ($assignment === null) {
            throw new RuntimeException('Zuweisung konnte nicht angelegt werden.');
        }

        return $assignment;
    }

    /** @return array<string, mixed>|null */
    public static function findAssignmentById(int $id): ?array
    {
        if ($id < 1 || !Database::isConfigured()) {
            return null;
        }

        $stmt = Database::pdo()->prepare(
            'SELECT asn.*, c.title AS course_title, c.slug AS course_slug, c.version AS course_version,
                    c.certificate_enabled, c.certificate_scope, c.certificate_valid_days
             FROM dg_academy_assignments asn
             INNER JOIN dg_academy_courses c ON c.id = asn.course_id
             WHERE asn.id = :id LIMIT 1'
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    /** @return list<array<string, mixed>> */
    public static function assignmentsForUser(int $userId): array
    {
        if ($userId < 1 || !Database::isConfigured()) {
            return [];
        }

        $stmt = Database::pdo()->prepare(
            'SELECT asn.*, c.title AS course_title, c.slug AS course_slug, c.version AS course_version,
                    c.min_tier, a.code AS area_code, a.label AS area_label
             FROM dg_academy_assignments asn
             INNER JOIN dg_academy_courses c ON c.id = asn.course_id
             INNER JOIN dg_academy_areas a ON a.id = c.area_id
             WHERE asn.user_id = :user_id
             ORDER BY asn.updated_at DESC, asn.id DESC'
        );
        $stmt->execute(['user_id' => $userId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /** @return list<array<string, mixed>> */
    public static function pendingHrReviews(): array
    {
        if (!Database::isConfigured()) {
            return [];
        }

        $stmt = Database::pdo()->query(
            'SELECT asn.*, c.title AS course_title, c.slug AS course_slug, c.version AS course_version,
                    u.display_name AS user_name, u.email AS user_email
             FROM dg_academy_assignments asn
             INNER JOIN dg_academy_courses c ON c.id = asn.course_id
             INNER JOIN dg_users u ON u.id = asn.user_id
             WHERE asn.status IN (\'pending_review\', \'flagged\')
             ORDER BY asn.completed_at DESC, asn.id DESC'
        );

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public static function hasAcceptedRules(int $userId, int $courseId, string $courseVersion): bool
    {
        if (!Database::isConfigured()) {
            return false;
        }

        $stmt = Database::pdo()->prepare(
            'SELECT 1 FROM dg_academy_rules_acceptance
             WHERE user_id = :user_id AND course_id = :course_id AND course_version = :version LIMIT 1'
        );
        $stmt->execute([
            'user_id' => $userId,
            'course_id' => $courseId,
            'version' => $courseVersion,
        ]);

        return (bool) $stmt->fetchColumn();
    }

    public static function recordRulesAcceptance(int $userId, int $courseId, string $courseVersion, string $ip = ''): void
    {
        $stmt = Database::pdo()->prepare(
            'INSERT INTO dg_academy_rules_acceptance (user_id, course_id, course_version, ip_address)
             VALUES (:user_id, :course_id, :version, :ip)
             ON DUPLICATE KEY UPDATE accepted_at = CURRENT_TIMESTAMP, ip_address = VALUES(ip_address)'
        );
        $stmt->execute([
            'user_id' => $userId,
            'course_id' => $courseId,
            'version' => $courseVersion,
            'ip' => $ip,
        ]);
    }

    /** @return array<string, mixed>|null */
    public static function moduleProgress(int $assignmentId, int $moduleId): ?array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT * FROM dg_academy_module_progress
             WHERE assignment_id = :assignment_id AND module_id = :module_id LIMIT 1'
        );
        $stmt->execute(['assignment_id' => $assignmentId, 'module_id' => $moduleId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    /** @return list<array<string, mixed>> */
    public static function progressForAssignment(int $assignmentId): array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT p.*, m.title AS module_title, m.sort_order, m.duration_sec
             FROM dg_academy_module_progress p
             INNER JOIN dg_academy_modules m ON m.id = p.module_id
             WHERE p.assignment_id = :assignment_id
             ORDER BY m.sort_order ASC, m.id ASC'
        );
        $stmt->execute(['assignment_id' => $assignmentId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /** @param array<string, mixed> $data */
    public static function saveCourse(array $data): int
    {
        $id = (int) ($data['id'] ?? 0);
        $params = [
            'area_id' => (int) ($data['area_id'] ?? 0),
            'title' => trim((string) ($data['title'] ?? '')),
            'slug' => trim((string) ($data['slug'] ?? '')),
            'description' => trim((string) ($data['description'] ?? '')),
            'version' => trim((string) ($data['version'] ?? '1.0')),
            'min_tier' => AcademyTier::sanitize((string) ($data['min_tier'] ?? AcademyTier::STARTER)),
            'access_mode_default' => AcademyAccessMode::sanitize((string) ($data['access_mode_default'] ?? AcademyAccessMode::COMPARE)),
            'certificate_enabled' => !empty($data['certificate_enabled']) ? 1 : 0,
            'certificate_scope' => in_array(($data['certificate_scope'] ?? ''), ['platform', 'company'], true)
                ? (string) $data['certificate_scope'] : 'company',
            'certificate_valid_days' => max(1, (int) ($data['certificate_valid_days'] ?? 365)),
            'quiz_enabled' => !empty($data['quiz_enabled']) ? 1 : 0,
            'is_published' => !empty($data['is_published']) ? 1 : 0,
            'sort_order' => (int) ($data['sort_order'] ?? 0),
        ];

        if ($params['title'] === '' || $params['area_id'] < 1) {
            throw new InvalidArgumentException('Titel und Bereich sind Pflicht.');
        }
        if ($params['slug'] === '') {
            $params['slug'] = self::slugify($params['title']);
        }

        if ($id > 0) {
            $stmt = Database::pdo()->prepare(
                'UPDATE dg_academy_courses SET
                    area_id = :area_id, title = :title, slug = :slug, description = :description,
                    version = :version, min_tier = :min_tier, access_mode_default = :access_mode_default,
                    certificate_enabled = :certificate_enabled, certificate_scope = :certificate_scope,
                    certificate_valid_days = :certificate_valid_days, quiz_enabled = :quiz_enabled,
                    is_published = :is_published, sort_order = :sort_order
                 WHERE id = :id'
            );
            $stmt->execute($params + ['id' => $id]);

            return $id;
        }

        $stmt = Database::pdo()->prepare(
            'INSERT INTO dg_academy_courses
                (area_id, title, slug, description, version, min_tier, access_mode_default,
                 certificate_enabled, certificate_scope, certificate_valid_days, quiz_enabled, is_published, sort_order)
             VALUES
                (:area_id, :title, :slug, :description, :version, :min_tier, :access_mode_default,
                 :certificate_enabled, :certificate_scope, :certificate_valid_days, :quiz_enabled, :is_published, :sort_order)'
        );
        $stmt->execute($params);

        return (int) Database::pdo()->lastInsertId();
    }

    /** @param array<string, mixed> $data */
    public static function assignUser(int $userId, int $courseId, int $assignedBy, string $accessMode): void
    {
        $existing = self::findAssignment($userId, $courseId);
        $mode = AcademyAccessMode::sanitize($accessMode);

        if ($existing !== null) {
            $stmt = Database::pdo()->prepare(
                'UPDATE dg_academy_assignments SET access_mode = :access_mode, assigned_by = :assigned_by, updated_at = NOW()
                 WHERE id = :id'
            );
            $stmt->execute([
                'access_mode' => $mode,
                'assigned_by' => $assignedBy > 0 ? $assignedBy : null,
                'id' => (int) $existing['id'],
            ]);

            return;
        }

        self::ensureAssignment($userId, $courseId, $assignedBy, $mode);
    }

    /** @return array<string, mixed>|null */
    public static function gateForModule(string $moduleKey): ?array
    {
        $moduleKey = trim($moduleKey);
        if ($moduleKey === '' || !Database::isConfigured()) {
            return null;
        }

        $stmt = Database::pdo()->prepare(
            'SELECT g.*, c.title AS course_title, c.slug AS course_slug
             FROM dg_academy_gates g
             INNER JOIN dg_academy_courses c ON c.id = g.course_id
             WHERE g.module_key = :module_key AND g.is_active = 1 LIMIT 1'
        );
        $stmt->execute(['module_key' => $moduleKey]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public static function setGate(string $moduleKey, int $courseId, bool $active): void
    {
        $stmt = Database::pdo()->prepare(
            'INSERT INTO dg_academy_gates (module_key, course_id, is_active)
             VALUES (:module_key, :course_id, :is_active)
             ON DUPLICATE KEY UPDATE course_id = VALUES(course_id), is_active = VALUES(is_active)'
        );
        $stmt->execute([
            'module_key' => trim($moduleKey),
            'course_id' => $courseId,
            'is_active' => $active ? 1 : 0,
        ]);
    }

    /** @return list<array<string, mixed>> */
    public static function userOptions(): array
    {
        if (!Database::isConfigured()) {
            return [];
        }

        $stmt = Database::pdo()->query(
            'SELECT id, display_name, email FROM dg_users WHERE employee_active = 1 ORDER BY display_name ASC'
        );

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    private static function slugify(string $title): string
    {
        $slug = strtolower(trim($title));
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug) ?? '';
        $slug = trim($slug, '-');

        return $slug !== '' ? $slug : 'kurs';
    }
}
