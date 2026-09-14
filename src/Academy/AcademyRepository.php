<?php
declare(strict_types=1);

final class AcademyRepository
{
    /** @return list<array{id: string, name: string}> */
    public static function allDepartments(): array
    {
        if (!Database::isConfigured()) {
            return [];
        }

        return DepartmentRepository::optionsForSelect();
    }

    /** @return list<array<string, mixed>> */
    public static function allAreas(): array
    {
        return self::allDepartments();
    }

    /** @return array<string, mixed>|null */
    public static function findCourseById(int $id): ?array
    {
        if ($id < 1 || !Database::isConfigured()) {
            return null;
        }

        $stmt = Database::pdo()->prepare(
            'SELECT c.*, d.name AS department_name, d.id AS department_id,
                    a.code AS area_code, a.label AS area_label
             FROM dg_academy_courses c
             LEFT JOIN dg_departments d ON d.id = c.department_id
             LEFT JOIN dg_academy_areas a ON a.id = c.area_id
             WHERE c.id = :id LIMIT 1'
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? self::normalizeCourseRow($row) : null;
    }

    /** @return array<string, mixed>|null */
    public static function findCourseBySlug(string $slug): ?array
    {
        $slug = trim($slug);
        if ($slug === '' || !Database::isConfigured()) {
            return null;
        }

        $stmt = Database::pdo()->prepare(
            'SELECT c.*, d.name AS department_name, d.id AS department_id,
                    a.code AS area_code, a.label AS area_label
             FROM dg_academy_courses c
             LEFT JOIN dg_departments d ON d.id = c.department_id
             LEFT JOIN dg_academy_areas a ON a.id = c.area_id
             WHERE c.slug = :slug LIMIT 1'
        );
        $stmt->execute(['slug' => $slug]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? self::normalizeCourseRow($row) : null;
    }

    /** @return list<array<string, mixed>> */
    public static function publishedCourses(?string $departmentId = null): array
    {
        if (!Database::isConfigured()) {
            return [];
        }

        $sql = 'SELECT c.*, d.name AS department_name, d.id AS department_id,
                       a.code AS area_code, a.label AS area_label
                FROM dg_academy_courses c
                LEFT JOIN dg_departments d ON d.id = c.department_id
                LEFT JOIN dg_academy_areas a ON a.id = c.area_id
                WHERE c.is_published = 1';
        $params = [];
        if ($departmentId !== null && $departmentId !== '') {
            $sql .= ' AND c.department_id = :department_id';
            $params['department_id'] = $departmentId;
        }
        $sql .= ' ORDER BY d.sort_order ASC, d.name ASC, c.sort_order ASC, c.title ASC';

        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        return array_map([self::class, 'normalizeCourseRow'], $rows);
    }

    /** @return list<array<string, mixed>> */
    public static function allCourses(): array
    {
        if (!Database::isConfigured()) {
            return [];
        }

        $stmt = Database::pdo()->query(
            'SELECT c.*, d.name AS department_name, d.id AS department_id,
                    a.code AS area_code, a.label AS area_label
             FROM dg_academy_courses c
             LEFT JOIN dg_departments d ON d.id = c.department_id
             LEFT JOIN dg_academy_areas a ON a.id = c.area_id
             ORDER BY d.sort_order ASC, d.name ASC, c.sort_order ASC, c.title ASC'
        );
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        return array_map([self::class, 'normalizeCourseRow'], $rows);
    }

    /**
     * Alle CRM-Abteilungen mit zugehörigen Kursen (leere Abteilungen inklusive).
     *
     * @return list<array{department: array{id: string, name: string}, courses: list<array<string, mixed>>}>
     */
    public static function coursesGroupedByDepartment(): array
    {
        $departments = self::allDepartments();
        $generalCourses = [];
        $coursesByDept = [];
        foreach (self::allCourses() as $course) {
            $courseDeptIds = self::courseDepartmentIds((int) ($course['id'] ?? 0));
            if ($courseDeptIds === []) {
                $generalCourses[] = $course;
                continue;
            }
            foreach ($courseDeptIds as $deptId) {
                $coursesByDept[$deptId][] = $course;
            }
        }

        $out = [
            [
                'department' => ['id' => '', 'name' => 'Allgemein (alle Abteilungen)'],
                'courses' => $generalCourses,
            ],
        ];
        foreach ($departments as $dept) {
            $deptId = (string) ($dept['id'] ?? '');
            $out[] = [
                'department' => $dept,
                'courses' => $coursesByDept[$deptId] ?? [],
            ];
        }

        return $out;
    }

    /** @return list<string> */
    public static function moduleDepartmentIds(int $moduleId): array
    {
        if ($moduleId < 1 || !Database::isConfigured()) {
            return [];
        }
        if (!self::tableExists('dg_academy_module_departments')) {
            $module = self::findModule($moduleId);
            $legacy = trim((string) ($module['department_id'] ?? ''));

            return $legacy !== '' ? [$legacy] : [];
        }

        $stmt = Database::pdo()->prepare(
            'SELECT department_id FROM dg_academy_module_departments WHERE module_id = :module_id ORDER BY department_id ASC'
        );
        $stmt->execute(['module_id' => $moduleId]);

        return array_map('strval', $stmt->fetchAll(PDO::FETCH_COLUMN) ?: []);
    }

    /** @return list<string> */
    public static function courseDepartmentIds(int $courseId): array
    {
        if ($courseId < 1 || !Database::isConfigured()) {
            return [];
        }
        if (!self::tableExists('dg_academy_course_departments')) {
            $course = self::findCourseById($courseId);
            $legacy = trim((string) ($course['department_id'] ?? ''));

            return $legacy !== '' ? [$legacy] : [];
        }

        $stmt = Database::pdo()->prepare(
            'SELECT department_id FROM dg_academy_course_departments WHERE course_id = :course_id ORDER BY department_id ASC'
        );
        $stmt->execute(['course_id' => $courseId]);

        return array_map('strval', $stmt->fetchAll(PDO::FETCH_COLUMN) ?: []);
    }

    /** @param list<string> $departmentIds */
    public static function saveModuleDepartments(int $moduleId, array $departmentIds): void
    {
        if ($moduleId < 1 || !Database::isConfigured() || !self::tableExists('dg_academy_module_departments')) {
            return;
        }

        $unique = [];
        foreach ($departmentIds as $deptId) {
            $deptId = trim((string) $deptId);
            if ($deptId !== '' && DepartmentRepository::exists($deptId)) {
                $unique[$deptId] = $deptId;
            }
        }

        $pdo = Database::pdo();
        $pdo->prepare('DELETE FROM dg_academy_module_departments WHERE module_id = :module_id')
            ->execute(['module_id' => $moduleId]);
        if ($unique === []) {
            return;
        }

        $ins = $pdo->prepare(
            'INSERT INTO dg_academy_module_departments (module_id, department_id) VALUES (:module_id, :department_id)'
        );
        foreach ($unique as $deptId) {
            $ins->execute(['module_id' => $moduleId, 'department_id' => $deptId]);
        }
    }

    /** @param list<string> $departmentIds */
    public static function saveCourseDepartments(int $courseId, array $departmentIds): void
    {
        if ($courseId < 1 || !Database::isConfigured() || !self::tableExists('dg_academy_course_departments')) {
            return;
        }

        $unique = [];
        foreach ($departmentIds as $deptId) {
            $deptId = trim((string) $deptId);
            if ($deptId !== '' && DepartmentRepository::exists($deptId)) {
                $unique[$deptId] = $deptId;
            }
        }

        $pdo = Database::pdo();
        $pdo->prepare('DELETE FROM dg_academy_course_departments WHERE course_id = :course_id')
            ->execute(['course_id' => $courseId]);
        if ($unique === []) {
            return;
        }

        $ins = $pdo->prepare(
            'INSERT INTO dg_academy_course_departments (course_id, department_id) VALUES (:course_id, :department_id)'
        );
        foreach ($unique as $deptId) {
            $ins->execute(['course_id' => $courseId, 'department_id' => $deptId]);
        }
    }

    /** @return list<array<string, mixed>> */
    public static function libraryVideos(bool $activeOnly = true): array
    {
        return self::allVideos($activeOnly);
    }

    /** @return list<array<string, mixed>> */
    public static function modulesForCourse(int $courseId, bool $activeOnly = true): array
    {
        if ($courseId < 1 || !Database::isConfigured()) {
            return [];
        }

        $sql = 'SELECT m.*
                FROM dg_academy_modules m
                INNER JOIN dg_academy_course_modules cm ON cm.module_id = m.id
                WHERE cm.course_id = :course_id';
        if ($activeOnly) {
            $sql .= ' AND m.is_active = 1';
        }
        $sql .= ' ORDER BY cm.sort_order ASC, m.id ASC';

        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute(['course_id' => $courseId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /** @return list<int> */
    public static function moduleIdsForCourse(int $courseId): array
    {
        if ($courseId < 1 || !Database::isConfigured()) {
            return [];
        }

        $stmt = Database::pdo()->prepare(
            'SELECT module_id FROM dg_academy_course_modules
             WHERE course_id = :course_id ORDER BY sort_order ASC, id ASC'
        );
        $stmt->execute(['course_id' => $courseId]);

        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN) ?: []);
    }

    /** @return list<array<string, mixed>> */
    public static function videosForDepartment(string $departmentId, bool $activeOnly = false): array
    {
        $departmentId = trim($departmentId);
        if ($departmentId === '' || !Database::isConfigured()) {
            return [];
        }

        $sql = 'SELECT * FROM dg_academy_modules WHERE department_id = :department_id';
        if ($activeOnly) {
            $sql .= ' AND is_active = 1';
        }
        $sql .= ' ORDER BY sort_order ASC, title ASC, id ASC';

        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute(['department_id' => $departmentId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /** @return list<array<string, mixed>> */
    public static function allVideos(bool $activeOnly = false): array
    {
        if (!Database::isConfigured()) {
            return [];
        }

        $sql = 'SELECT m.* FROM dg_academy_modules m';
        if ($activeOnly) {
            $sql .= ' WHERE m.is_active = 1';
        }
        $sql .= ' ORDER BY m.sort_order ASC, m.title ASC, m.id ASC';
        $rows = Database::pdo()->query($sql)->fetchAll(PDO::FETCH_ASSOC) ?: [];

        return self::attachDepartmentLabelsToModules($rows);
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

    /** @param list<int> $moduleIds */
    public static function saveCourseModules(int $courseId, array $moduleIds): void
    {
        if ($courseId < 1) {
            throw new InvalidArgumentException('Kurs ungültig.');
        }

        $course = self::findCourseById($courseId);
        if ($course === null) {
            throw new InvalidArgumentException('Kurs nicht gefunden.');
        }

        $deptId = (string) ($course['department_id'] ?? '');
        $unique = [];
        foreach ($moduleIds as $moduleId) {
            $moduleId = (int) $moduleId;
            if ($moduleId > 0) {
                $unique[$moduleId] = $moduleId;
            }
        }

        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            $del = $pdo->prepare('DELETE FROM dg_academy_course_modules WHERE course_id = :course_id');
            $del->execute(['course_id' => $courseId]);

            if ($unique !== []) {
                $ins = $pdo->prepare(
                    'INSERT INTO dg_academy_course_modules (course_id, module_id, sort_order)
                     VALUES (:course_id, :module_id, :sort_order)'
                );
                $sort = 10;
                foreach ($unique as $moduleId) {
                    $module = self::findModule($moduleId);
                    if ($module === null) {
                        continue;
                    }
                    $ins->execute([
                        'course_id' => $courseId,
                        'module_id' => $moduleId,
                        'sort_order' => $sort,
                    ]);
                    $sort += 10;
                }
            }

            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    /** @param array<string, mixed> $data */
    public static function saveModule(array $data): int
    {
        $id = (int) ($data['id'] ?? 0);
        $departmentId = trim((string) ($data['department_id'] ?? ''));
        if ($departmentId !== '' && !DepartmentRepository::exists($departmentId)) {
            throw new InvalidArgumentException('Abteilung nicht gefunden.');
        }

        $title = trim((string) ($data['title'] ?? ''));
        if ($title === '') {
            throw new InvalidArgumentException('Titel ist Pflicht.');
        }

        $departmentIds = [];
        if (!empty($data['department_ids']) && is_array($data['department_ids'])) {
            $departmentIds = array_map('strval', $data['department_ids']);
        } elseif ($departmentId !== '') {
            $departmentIds = [$departmentId];
        }

        $params = [
            'department_id' => $departmentId !== '' ? $departmentId : null,
            'title' => $title,
            'description' => trim((string) ($data['description'] ?? '')),
            'provider' => 'file',
            'video_path' => trim((string) ($data['video_path'] ?? '')),
            'external_ref' => '',
            'duration_sec' => max(1, (int) ($data['duration_sec'] ?? 180)),
            'min_watch_percent' => max(1, min(100, (int) ($data['min_watch_percent'] ?? 90))),
            'subtitle_vtt_path' => trim((string) ($data['subtitle_vtt_path'] ?? '')),
            'is_active' => !empty($data['is_active']) ? 1 : 0,
            'sort_order' => (int) ($data['sort_order'] ?? 0),
        ];

        if ($id > 0) {
            $stmt = Database::pdo()->prepare(
                'UPDATE dg_academy_modules SET
                    department_id = :department_id, title = :title, description = :description,
                    provider = :provider, video_path = :video_path, external_ref = :external_ref,
                    duration_sec = :duration_sec, min_watch_percent = :min_watch_percent,
                    subtitle_vtt_path = :subtitle_vtt_path, is_active = :is_active, sort_order = :sort_order
                 WHERE id = :id'
            );
            $stmt->execute($params + ['id' => $id]);
            self::saveModuleDepartments($id, $departmentIds);

            return $id;
        }

        $stmt = Database::pdo()->prepare(
            'INSERT INTO dg_academy_modules
                (department_id, sort_order, title, description, provider, video_path, external_ref,
                 duration_sec, min_watch_percent, subtitle_vtt_path, is_active)
             VALUES
                (:department_id, :sort_order, :title, :description, :provider, :video_path, :external_ref,
                 :duration_sec, :min_watch_percent, :subtitle_vtt_path, :is_active)'
        );
        $stmt->execute($params);
        $newId = (int) Database::pdo()->lastInsertId();
        self::saveModuleDepartments($newId, $departmentIds);

        return $newId;
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
                    c.min_tier, d.name AS department_name, d.id AS department_id,
                    a.code AS area_code, a.label AS area_label
             FROM dg_academy_assignments asn
             INNER JOIN dg_academy_courses c ON c.id = asn.course_id
             LEFT JOIN dg_departments d ON d.id = c.department_id
             LEFT JOIN dg_academy_areas a ON a.id = c.area_id
             WHERE asn.user_id = :user_id
             ORDER BY asn.updated_at DESC, asn.id DESC'
        );
        $stmt->execute(['user_id' => $userId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        return array_map(static function (array $row): array {
            if (!isset($row['area_label']) || $row['area_label'] === '') {
                $row['area_label'] = (string) ($row['department_name'] ?? '');
            }

            return $row;
        }, $rows);
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
            'SELECT p.*, m.title AS module_title, cm.sort_order, m.duration_sec
             FROM dg_academy_module_progress p
             INNER JOIN dg_academy_modules m ON m.id = p.module_id
             INNER JOIN dg_academy_assignments asn ON asn.id = p.assignment_id
             INNER JOIN dg_academy_course_modules cm ON cm.course_id = asn.course_id AND cm.module_id = m.id
             WHERE p.assignment_id = :assignment_id
             ORDER BY cm.sort_order ASC, m.id ASC'
        );
        $stmt->execute(['assignment_id' => $assignmentId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /** @param array<string, mixed> $data */
    public static function saveCourse(array $data): int
    {
        $id = (int) ($data['id'] ?? 0);
        $departmentId = trim((string) ($data['department_id'] ?? ''));
        if ($departmentId === '' && (int) ($data['area_id'] ?? 0) > 0) {
            $departmentId = self::departmentIdFromLegacyArea((int) $data['area_id']);
        }

        $params = [
            'department_id' => $departmentId,
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

        if ($params['title'] === '') {
            throw new InvalidArgumentException('Titel ist Pflicht.');
        }

        $courseDepartmentIds = [];
        if (!empty($data['department_ids']) && is_array($data['department_ids'])) {
            $courseDepartmentIds = array_map('strval', $data['department_ids']);
        } elseif ($params['department_id'] !== '') {
            $courseDepartmentIds = [(string) $params['department_id']];
        }

        if ($params['department_id'] !== '' && !DepartmentRepository::exists($params['department_id'])) {
            throw new InvalidArgumentException('Abteilung nicht gefunden.');
        }
        if ($params['slug'] === '') {
            $params['slug'] = self::slugify($params['title']);
        }

        if ($params['area_id'] < 1) {
            $params['area_id'] = $params['department_id'] !== ''
                ? self::legacyAreaIdForDepartment($params['department_id'])
                : self::defaultLegacyAreaId();
        }
        if ($params['department_id'] === '') {
            $params['department_id'] = null;
        }

        if ($id > 0) {
            $stmt = Database::pdo()->prepare(
                'UPDATE dg_academy_courses SET
                    department_id = :department_id, area_id = :area_id, title = :title, slug = :slug,
                    description = :description, version = :version, min_tier = :min_tier,
                    access_mode_default = :access_mode_default, certificate_enabled = :certificate_enabled,
                    certificate_scope = :certificate_scope, certificate_valid_days = :certificate_valid_days,
                    quiz_enabled = :quiz_enabled, is_published = :is_published, sort_order = :sort_order
                 WHERE id = :id'
            );
            $stmt->execute($params + ['id' => $id]);
            self::saveCourseDepartments($id, $courseDepartmentIds);

            return $id;
        }

        $stmt = Database::pdo()->prepare(
            'INSERT INTO dg_academy_courses
                (department_id, area_id, title, slug, description, version, min_tier, access_mode_default,
                 certificate_enabled, certificate_scope, certificate_valid_days, quiz_enabled, is_published, sort_order)
             VALUES
                (:department_id, :area_id, :title, :slug, :description, :version, :min_tier, :access_mode_default,
                 :certificate_enabled, :certificate_scope, :certificate_valid_days, :quiz_enabled, :is_published, :sort_order)'
        );
        $stmt->execute($params);
        $newId = (int) Database::pdo()->lastInsertId();
        self::saveCourseDepartments($newId, $courseDepartmentIds);

        return $newId;
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

    /** @param array<string, mixed> $row */
    private static function normalizeCourseRow(array $row): array
    {
        if (!isset($row['area_label']) || $row['area_label'] === '' || $row['area_label'] === null) {
            $row['area_label'] = (string) ($row['department_name'] ?? '');
        }
        if ($row['area_label'] === '') {
            $row['area_label'] = 'Allgemein';
        }

        return $row;
    }

    /** @param list<array<string, mixed>> $rows */
    private static function attachDepartmentLabelsToModules(array $rows): array
    {
        $deptNames = [];
        foreach (self::allDepartments() as $dept) {
            $deptNames[(string) ($dept['id'] ?? '')] = (string) ($dept['name'] ?? '');
        }

        foreach ($rows as &$row) {
            $ids = self::moduleDepartmentIds((int) ($row['id'] ?? 0));
            if ($ids === []) {
                $row['department_name'] = 'Alle Abteilungen';
                $row['department_ids'] = [];
            } else {
                $labels = [];
                foreach ($ids as $id) {
                    $labels[] = $deptNames[$id] ?? $id;
                }
                $row['department_name'] = implode(', ', $labels);
                $row['department_ids'] = $ids;
            }
        }
        unset($row);

        return $rows;
    }

    private static function tableExists(string $table): bool
    {
        if (!Database::isConfigured()) {
            return false;
        }
        $stmt = Database::pdo()->query('SHOW TABLES LIKE ' . Database::pdo()->quote($table));

        return $stmt !== false && $stmt->fetchColumn() !== false;
    }

    private static function defaultLegacyAreaId(): int
    {
        if (!Database::isConfigured()) {
            return 1;
        }

        $stmt = Database::pdo()->query(
            "SELECT id FROM dg_academy_areas WHERE code = 'allgemein' ORDER BY id ASC LIMIT 1"
        );
        $id = $stmt ? $stmt->fetchColumn() : false;
        if ($id) {
            return (int) $id;
        }

        $stmt = Database::pdo()->query('SELECT id FROM dg_academy_areas ORDER BY sort_order ASC, id ASC LIMIT 1');
        $fallback = $stmt ? $stmt->fetchColumn() : false;

        return $fallback ? (int) $fallback : 1;
    }

    private static function departmentIdFromLegacyArea(int $areaId): string
    {
        if ($areaId < 1 || !Database::isConfigured()) {
            return '';
        }

        $stmt = Database::pdo()->prepare('SELECT code, label FROM dg_academy_areas WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $areaId]);
        $area = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!is_array($area)) {
            return '';
        }

        $label = trim((string) ($area['label'] ?? ''));
        if ($label !== '') {
            $match = Database::pdo()->prepare(
                'SELECT id FROM dg_departments WHERE LOWER(TRIM(name)) = LOWER(TRIM(:name)) LIMIT 1'
            );
            $match->execute(['name' => $label]);
            $id = $match->fetchColumn();
            if ($id) {
                return (string) $id;
            }
        }

        $code = trim((string) ($area['code'] ?? ''));
        if ($code !== '') {
            $match = Database::pdo()->prepare(
                'SELECT id FROM dg_departments
                 WHERE id LIKE :pattern OR LOWER(name) LIKE :name_pattern
                 ORDER BY sort_order ASC LIMIT 1'
            );
            $match->execute([
                'pattern' => '%' . $code . '%',
                'name_pattern' => '%' . $code . '%',
            ]);
            $id = $match->fetchColumn();
            if ($id) {
                return (string) $id;
            }
        }

        return '';
    }

    private static function legacyAreaIdForDepartment(string $departmentId): int
    {
        $name = DepartmentRepository::departmentName($departmentId);
        if ($name === '') {
            return 1;
        }

        $stmt = Database::pdo()->prepare(
            'SELECT id FROM dg_academy_areas
             WHERE LOWER(TRIM(label)) = LOWER(TRIM(:name))
             ORDER BY id ASC LIMIT 1'
        );
        $stmt->execute(['name' => $name]);
        $id = $stmt->fetchColumn();
        if ($id) {
            return (int) $id;
        }

        $stmt = Database::pdo()->query('SELECT id FROM dg_academy_areas ORDER BY sort_order ASC, id ASC LIMIT 1');
        $fallback = $stmt ? $stmt->fetchColumn() : false;

        return $fallback ? (int) $fallback : 1;
    }

    private static function slugify(string $title): string
    {
        $slug = strtolower(trim($title));
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug) ?? '';
        $slug = trim($slug, '-');

        return $slug !== '' ? $slug : 'kurs';
    }
}
