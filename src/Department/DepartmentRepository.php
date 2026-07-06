<?php
declare(strict_types=1);

final class DepartmentRepository
{
    /**
     * @return list<array{
     *   id: string,
     *   name: string,
     *   description: string,
     *   sort_order: int,
     *   members: list<array{user_id: int, role: string}>
     * }>
     */
    public static function allWithMembers(): array
    {
        if (!self::useDatabase()) {
            return self::fromFile();
        }

        MigrationRunner::runPending();
        self::ensureSeeded();

        $pdo = Database::pdo();
        $stmt = $pdo->query(
            'SELECT id, name, description, is_hr, allow_contact_delete, is_purchasing, allow_article_catalog, sort_order
             FROM dg_departments
             ORDER BY sort_order ASC, name ASC'
        );

        $departments = [];
        while ($row = $stmt->fetch()) {
            $departments[] = [
                'id' => (string) $row['id'],
                'name' => (string) $row['name'],
                'description' => (string) ($row['description'] ?? ''),
                'is_hr' => (bool) ($row['is_hr'] ?? false),
                'allow_contact_delete' => (bool) ($row['allow_contact_delete'] ?? false),
                'is_purchasing' => (bool) ($row['is_purchasing'] ?? false),
                'allow_article_catalog' => (bool) ($row['allow_article_catalog'] ?? false)
                    || (bool) ($row['is_purchasing'] ?? false),
                'sort_order' => (int) ($row['sort_order'] ?? 0),
                'members' => [],
                'modules' => DepartmentAccess::defaultModules(),
            ];
        }

        if ($departments === []) {
            return [];
        }

        $ids = array_column($departments, 'id');
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $memberStmt = $pdo->prepare(
            "SELECT department_id, user_id, member_role
             FROM dg_department_members
             WHERE department_id IN ({$placeholders})
             ORDER BY member_role DESC, user_id ASC"
        );
        $memberStmt->execute($ids);

        $index = [];
        foreach ($departments as $i => $department) {
            $index[$department['id']] = $i;
        }

        while ($row = $memberStmt->fetch()) {
            $deptId = (string) $row['department_id'];
            if (!isset($index[$deptId])) {
                continue;
            }
            $departments[$index[$deptId]]['members'][] = [
                'user_id' => (int) $row['user_id'],
                'role' => (string) $row['member_role'],
            ];
        }

        if ($departments !== []) {
            $modStmt = $pdo->prepare(
                'SELECT department_id, module_key, access_level
                 FROM dg_department_module_access
                 WHERE department_id IN (' . $placeholders . ')'
            );
            $modStmt->execute($ids);
            while ($row = $modStmt->fetch()) {
                $deptId = (string) $row['department_id'];
                if (!isset($index[$deptId])) {
                    continue;
                }
                $key = (string) $row['module_key'];
                if (isset(DepartmentAccess::MODULE_LABELS[$key])) {
                    $departments[$index[$deptId]]['modules'][$key] = DepartmentAccess::normalizeLevel(
                        (string) $row['access_level']
                    );
                }
            }
        }

        return $departments;
    }

    /** @return list<User> */
    public static function assignableEmployees(): array
    {
        $employeeRole = (string) App::config('roles.employee', 'dg_eigenmitarbeiter');
        $users = [];

        foreach (UserRepository::all() as $user) {
            if ($user->hasRole($employeeRole)) {
                $users[] = $user;
            }
        }

        return $users;
    }

    /** @return list<int> */
    public static function userIdsForDepartment(string $departmentId): array
    {
        $departmentId = trim($departmentId);
        if ($departmentId === '' || !Database::isConfigured()) {
            return [];
        }

        $stmt = Database::pdo()->prepare(
            'SELECT user_id FROM dg_department_members WHERE department_id = :department_id'
        );
        $stmt->execute(['department_id' => $departmentId]);

        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN) ?: []);
    }

    public static function departmentName(string $departmentId): string
    {
        $departmentId = trim($departmentId);
        if ($departmentId === '' || !Database::isConfigured()) {
            return '';
        }

        $stmt = Database::pdo()->prepare('SELECT name FROM dg_departments WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $departmentId]);
        $name = $stmt->fetchColumn();

        return $name ? (string) $name : '';
    }

    public static function exists(string $departmentId): bool
    {
        $departmentId = trim($departmentId);
        if ($departmentId === '' || !Database::isConfigured()) {
            return false;
        }

        $stmt = Database::pdo()->prepare('SELECT 1 FROM dg_departments WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $departmentId]);

        return (bool) $stmt->fetchColumn();
    }

    /** @return list<array{id: string, name: string}> */
    public static function optionsForSelect(): array
    {
        $options = [];
        foreach (self::allWithMembers() as $department) {
            $options[] = [
                'id' => (string) $department['id'],
                'name' => (string) $department['name'],
            ];
        }

        return $options;
    }

    /** @param array<string, mixed> $input */
    public static function saveFromPost(array $input): void
    {
        if (!Database::isConfigured()) {
            throw new RuntimeException('Abteilungen können nur mit konfigurierter Datenbank gespeichert werden.');
        }

        MigrationRunner::runPending();

        $raw = $input['departments'] ?? [];
        if (!is_array($raw)) {
            throw new InvalidArgumentException('Ungültige Formulardaten.');
        }

        $departments = self::sanitizeDepartments($raw);
        $pdo = Database::pdo();
        $pdo->beginTransaction();

        try {
            $pdo->exec('DELETE FROM dg_department_module_access');
            $pdo->exec('DELETE FROM dg_department_members');
            $pdo->exec('DELETE FROM dg_departments');

            $deptStmt = $pdo->prepare(
                'INSERT INTO dg_departments (id, name, description, is_hr, allow_contact_delete, is_purchasing, allow_article_catalog, sort_order)
                 VALUES (:id, :name, :description, :is_hr, :allow_contact_delete, :is_purchasing, :allow_article_catalog, :sort_order)'
            );
            $memberStmt = $pdo->prepare(
                'INSERT INTO dg_department_members (department_id, user_id, member_role)
                 VALUES (:department_id, :user_id, :member_role)'
            );
            $moduleStmt = $pdo->prepare(
                'INSERT INTO dg_department_module_access (department_id, module_key, access_level)
                 VALUES (:department_id, :module_key, :access_level)'
            );

            foreach ($departments as $sortOrder => $department) {
                $isHr = !empty($department['is_hr']);
                $deptStmt->execute([
                    'id' => $department['id'],
                    'name' => $department['name'],
                    'description' => $department['description'],
                    'is_hr' => $isHr ? 1 : 0,
                    'allow_contact_delete' => $isHr && !empty($department['allow_contact_delete']) ? 1 : 0,
                    'is_purchasing' => 0,
                    'allow_article_catalog' => !empty($department['allow_article_catalog']) ? 1 : 0,
                    'sort_order' => $sortOrder,
                ]);

                foreach ($department['modules'] as $moduleKey => $level) {
                    $moduleStmt->execute([
                        'department_id' => $department['id'],
                        'module_key' => $moduleKey,
                        'access_level' => DepartmentAccess::normalizeLevel((string) $level),
                    ]);
                }

                $seenUsers = [];
                foreach ($department['members'] as $member) {
                    $userId = (int) $member['user_id'];
                    if ($userId < 1 || isset($seenUsers[$userId])) {
                        continue;
                    }
                    $seenUsers[$userId] = true;
                    $memberStmt->execute([
                        'department_id' => $department['id'],
                        'user_id' => $userId,
                        'member_role' => $member['role'],
                    ]);
                }
            }

            $pdo->commit();
            DepartmentAccess::resetCache();
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    /**
     * @param array<int|string, mixed> $raw
     * @return list<array{id: string, name: string, description: string, members: list<array{user_id: int, role: string}>}>
     */
    private static function sanitizeDepartments(array $raw): array
    {
        $assignable = [];
        foreach (self::assignableEmployees() as $user) {
            $assignable[$user->id] = true;
        }

        $existingIds = [];
        foreach (self::allWithMembers() as $department) {
            $existingIds[$department['id']] = true;
        }

        $out = [];
        foreach ($raw as $row) {
            if (!is_array($row)) {
                continue;
            }

            $name = trim((string) ($row['name'] ?? ''));
            $description = trim((string) ($row['description'] ?? ''));
            $id = trim((string) ($row['id'] ?? ''));

            $members = [];
            if (!empty($row['members']) && is_array($row['members'])) {
                foreach ($row['members'] as $member) {
                    if (!is_array($member)) {
                        continue;
                    }
                    $userId = (int) ($member['user_id'] ?? 0);
                    if ($userId < 1 || !isset($assignable[$userId])) {
                        continue;
                    }
                    $role = (string) ($member['role'] ?? 'member');
                    if (!in_array($role, ['member', 'leader'], true)) {
                        $role = 'member';
                    }
                    $members[] = [
                        'user_id' => $userId,
                        'role' => $role,
                    ];
                }
            }

            if ($name === '' && $members === []) {
                continue;
            }
            if ($name === '') {
                throw new InvalidArgumentException('Jede Abteilung mit Mitgliedern braucht einen Namen.');
            }

            if ($id === '' || !preg_match('/^[a-z0-9][a-z0-9_-]{0,62}$/i', $id)) {
                $id = self::generateId($name, $existingIds);
            }
            if (isset($existingIds[$id]) && !self::idInCurrentBatch($id, $out)) {
                // keep existing id
            }
            $existingIds[$id] = true;

            $out[] = [
                'id' => $id,
                'name' => $name,
                'description' => $description,
                'is_hr' => !empty($row['is_hr']),
                'allow_contact_delete' => !empty($row['allow_contact_delete']),
                'is_purchasing' => !empty($row['is_purchasing']),
                'allow_article_catalog' => !empty($row['allow_article_catalog']) || !empty($row['is_purchasing']),
                'modules' => DepartmentAccess::sanitizeModules(is_array($row['modules'] ?? null) ? $row['modules'] : []),
                'members' => $members,
            ];
        }

        return $out;
    }

    /** @param list<array{id: string}> $batch */
    private static function idInCurrentBatch(string $id, array $batch): bool
    {
        foreach ($batch as $row) {
            if ($row['id'] === $id) {
                return true;
            }
        }

        return false;
    }

    /** @param array<string, true> $used */
    private static function generateId(string $name, array &$used): string
    {
        $slug = preg_replace('/[^a-z0-9]+/', '-', strtolower($name)) ?? '';
        $slug = trim((string) $slug, '-');
        if ($slug === '') {
            $slug = bin2hex(random_bytes(4));
        }
        $slug = substr($slug, 0, 48);

        $base = 'dept-' . $slug;
        $id = $base;
        $suffix = 2;
        while (isset($used[$id])) {
            $id = $base . '-' . $suffix;
            ++$suffix;
        }
        $used[$id] = true;

        return $id;
    }

    public static function ensureSeeded(): void
    {
        if (!self::useDatabase()) {
            return;
        }

        $count = (int) Database::pdo()->query('SELECT COUNT(*) FROM dg_departments')->fetchColumn();
        if ($count > 0) {
            return;
        }

        self::ensureMissingDefaults();
    }

    /** Fehlende Standard-Abteilungen ergänzen (bestehende bleiben unverändert). */
    public static function ensureMissingDefaults(): int
    {
        if (!Database::isConfigured()) {
            throw new RuntimeException('Datenbank nicht konfiguriert.');
        }

        MigrationRunner::runPending();

        if (!self::useDatabase()) {
            throw new RuntimeException('Tabelle dg_departments fehlt.');
        }

        $pdo = Database::pdo();
        $existing = [];
        foreach ($pdo->query('SELECT id FROM dg_departments')->fetchAll(PDO::FETCH_COLUMN) as $id) {
            $existing[(string) $id] = true;
        }

        $toInsert = [];
        foreach (DefaultDepartments::definitions() as $definition) {
            if (!isset($existing[$definition['id']])) {
                $toInsert[] = $definition;
            }
        }

        if ($toInsert === []) {
            return 0;
        }

        $sortOrder = (int) $pdo->query('SELECT COALESCE(MAX(sort_order), -1) FROM dg_departments')->fetchColumn();

        $pdo->beginTransaction();

        try {
            $deptStmt = $pdo->prepare(
                'INSERT INTO dg_departments (id, name, description, is_hr, allow_contact_delete, is_purchasing, allow_article_catalog, sort_order)
                 VALUES (:id, :name, :description, :is_hr, :allow_contact_delete, :is_purchasing, :allow_article_catalog, :sort_order)'
            );
            $moduleStmt = $pdo->prepare(
                'INSERT INTO dg_department_module_access (department_id, module_key, access_level)
                 VALUES (:department_id, :module_key, :access_level)'
            );

            foreach ($toInsert as $definition) {
                ++$sortOrder;
                $deptStmt->execute([
                    'id' => $definition['id'],
                    'name' => $definition['name'],
                    'description' => $definition['description'],
                    'is_hr' => !empty($definition['is_hr']) ? 1 : 0,
                    'allow_contact_delete' => !empty($definition['allow_contact_delete']) ? 1 : 0,
                    'is_purchasing' => !empty($definition['is_purchasing']) ? 1 : 0,
                    'allow_article_catalog' => !empty($definition['allow_article_catalog']) ? 1 : 0,
                    'sort_order' => $sortOrder,
                ]);

                foreach (DefaultDepartments::modulesForDepartment($definition['id']) as $moduleKey => $level) {
                    $moduleStmt->execute([
                        'department_id' => $definition['id'],
                        'module_key' => $moduleKey,
                        'access_level' => $level,
                    ]);
                }
            }

            $pdo->commit();
            DepartmentAccess::resetCache();
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }

        return count($toInsert);
    }

    /** @return list<array{id: string, name: string, description: string, sort_order: int, members: list<array{user_id: int, role: string}>}> */
    private static function fromFile(): array
    {
        return DefaultDepartments::withModulesAndMembers(DefaultDepartments::membersFromConfigFile());
    }

    private static function useDatabase(): bool
    {
        if (!Database::isConfigured()) {
            return false;
        }

        try {
            Database::pdo()->query('SELECT 1 FROM dg_departments LIMIT 1');

            return true;
        } catch (Throwable) {
            return false;
        }
    }
}
