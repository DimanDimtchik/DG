<?php
declare(strict_types=1);

/**
 * Department Repository — Mitglieder über Kontakte (MA), CRM-Rechte nur bei verknüpftem User.
 */
final class DepartmentRepository
{
    /**
     * @return list<array{
     *   id: string,
     *   name: string,
     *   description: string,
     *   sort_order: int,
     *   members: list<array{contact_id: int, user_id: int, role: string}>
     * }>
     */
    public static function allWithMembers(): array
    {
        if (!self::useDatabase()) {
            return self::fromFile();
        }

        MigrationRunner::runPending();
        self::ensureSeeded();
        self::ensureExecutiveChefLeaders();

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

        if (!self::membersUseContactId($pdo)) {
            return $departments;
        }

        $memberStmt = $pdo->prepare(
            "SELECT department_id, contact_id, member_role
             FROM dg_department_members
             WHERE department_id IN ({$placeholders})
             ORDER BY member_role DESC, contact_id ASC"
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
            $contactId = (int) $row['contact_id'];
            $departments[$index[$deptId]]['members'][] = [
                'contact_id' => $contactId,
                'user_id' => self::resolveUserIdForContact($contactId),
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

    /**
     * MA-Kontakte für Abteilungs-Zuordnung (mit/ohne CRM-Login).
     *
     * @return list<array{id: int, label: string, has_crm_user: bool}>
     */
    public static function assignableStaffContacts(): array
    {
        if (!Database::isConfigured()) {
            return [];
        }
        $stmt = Database::pdo()->query(
            "SELECT id, display_name, company_name, first_name, last_name, email, login
             FROM dg_contacts
             WHERE contact_role IN ('dg_eigenmitarbeiter', 'administrator', 'mitarbeiter')
             ORDER BY display_name ASC, id ASC"
        );
        $out = [];
        while ($row = $stmt->fetch()) {
            $id = (int) $row['id'];
            $label = self::contactLabelFromRow($row);
            $out[] = [
                'id' => $id,
                'label' => $label,
                'has_crm_user' => self::resolveUserIdForContact($id) > 0,
            ];
        }

        return $out;
    }

    /** @deprecated Use assignableStaffContacts() */
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

    /**
     * CRM-User-IDs einer Abteilung (nur Kontakte mit auflösbarem Login).
     *
     * @return list<int>
     */
    public static function userIdsForDepartment(string $departmentId): array
    {
        $departmentId = trim($departmentId);
        if ($departmentId === '' || !Database::isConfigured()) {
            return [];
        }

        if (!self::membersUseContactId(Database::pdo())) {
            return [];
        }

        $stmt = Database::pdo()->prepare(
            'SELECT contact_id FROM dg_department_members WHERE department_id = :department_id'
        );
        $stmt->execute(['department_id' => $departmentId]);
        $out = [];
        $seen = [];
        while ($row = $stmt->fetch()) {
            $uid = self::resolveUserIdForContact((int) $row['contact_id']);
            if ($uid < 1 || isset($seen[$uid])) {
                continue;
            }
            $seen[$uid] = true;
            $out[] = $uid;
        }

        return $out;
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

    /**
     * @return list<array{id: string, name: string}>
     */
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
                'INSERT INTO dg_department_members (department_id, contact_id, member_role)
                 VALUES (:department_id, :contact_id, :member_role)'
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

                $seen = [];
                foreach ($department['members'] as $member) {
                    $contactId = (int) $member['contact_id'];
                    if ($contactId < 1 || isset($seen[$contactId])) {
                        continue;
                    }
                    $seen[$contactId] = true;
                    $memberStmt->execute([
                        'department_id' => $department['id'],
                        'contact_id' => $contactId,
                        'member_role' => $member['role'],
                    ]);
                }
            }

            $pdo->commit();
            self::ensureExecutiveChefLeaders(true);
            DepartmentAccess::resetCache();
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Schema-Umbau user_id → contact_id (Migration 109).
     */
    public static function migrateMembersSchemaToContacts(PDO $pdo): void
    {
        if (!self::pdoTableExists($pdo, 'dg_department_members')) {
            $pdo->exec(
                "CREATE TABLE dg_department_members (
                    department_id VARCHAR(64) NOT NULL,
                    contact_id INT UNSIGNED NOT NULL,
                    member_role ENUM('member', 'leader') NOT NULL DEFAULT 'member',
                    PRIMARY KEY (department_id, contact_id),
                    KEY idx_dept_members_contact (contact_id),
                    CONSTRAINT fk_dept_member_dept FOREIGN KEY (department_id) REFERENCES dg_departments (id) ON DELETE CASCADE,
                    CONSTRAINT fk_dept_member_contact FOREIGN KEY (contact_id) REFERENCES dg_contacts (id) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
            );

            return;
        }

        if (self::pdoColumnExists($pdo, 'dg_department_members', 'contact_id')
            && !self::pdoColumnExists($pdo, 'dg_department_members', 'user_id')
        ) {
            return;
        }

        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS dg_department_members_contact (
                department_id VARCHAR(64) NOT NULL,
                contact_id INT UNSIGNED NOT NULL,
                member_role ENUM('member', 'leader') NOT NULL DEFAULT 'member',
                PRIMARY KEY (department_id, contact_id),
                KEY idx_dept_members_contact (contact_id),
                CONSTRAINT fk_dept_member_v2_dept FOREIGN KEY (department_id) REFERENCES dg_departments (id) ON DELETE CASCADE,
                CONSTRAINT fk_dept_member_v2_contact FOREIGN KEY (contact_id) REFERENCES dg_contacts (id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        if (self::pdoColumnExists($pdo, 'dg_department_members', 'user_id')) {
            $rows = $pdo->query(
                'SELECT department_id, user_id, member_role FROM dg_department_members'
            )->fetchAll() ?: [];
            $ins = $pdo->prepare(
                'INSERT IGNORE INTO dg_department_members_contact (department_id, contact_id, member_role)
                 VALUES (:department_id, :contact_id, :member_role)'
            );
            foreach ($rows as $row) {
                $userId = (int) ($row['user_id'] ?? 0);
                if ($userId < 1) {
                    continue;
                }
                $user = UserRepository::findById($userId);
                if ($user === null) {
                    continue;
                }
                $contactId = ContactRepository::findStaffContactIdForUser($user);
                if ($contactId === null || $contactId < 1) {
                    continue;
                }
                $role = (string) ($row['member_role'] ?? 'member');
                if (!in_array($role, ['member', 'leader'], true)) {
                    $role = 'member';
                }
                $ins->execute([
                    'department_id' => (string) $row['department_id'],
                    'contact_id' => $contactId,
                    'member_role' => $role,
                ]);
            }
        }

        $pdo->exec('SET FOREIGN_KEY_CHECKS=0');
        $pdo->exec('DROP TABLE dg_department_members');
        $pdo->exec('RENAME TABLE dg_department_members_contact TO dg_department_members');
        $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
    }

    public static function resolveUserIdForContact(int $contactId): int
    {
        if ($contactId < 1) {
            return 0;
        }
        $contact = ContactRepository::findById($contactId);
        if ($contact === null) {
            return 0;
        }
        $uid = MailboxMemberResolver::findUserIdForContact($contact);

        return $uid !== null && $uid > 0 ? $uid : 0;
    }

    public static function resolveContactIdForUser(User $user): int
    {
        $cid = ContactRepository::findStaffContactIdForUser($user);

        return $cid !== null && $cid > 0 ? $cid : 0;
    }

    /**
     * Chef = Inhaber mit den meisten Anteilen (als Kontakt).
     * Nur der Chef ist Abteilungsleiter; andere Admins/Inhaber mit Kontakt = Mitglieder.
     */
    public static function ensureExecutiveChefLeaders(bool $force = false): void
    {
        static $done = false;
        if ($done && !$force) {
            return;
        }
        if (!self::useDatabase()) {
            return;
        }

        $pdo = Database::pdo();
        if (!self::membersUseContactId($pdo)) {
            $done = true;

            return;
        }

        $deptId = self::resolveExecutiveDepartmentId($pdo);
        if ($deptId === null) {
            $done = true;

            return;
        }

        $adminRole = (string) App::config('roles.admin', 'administrator');
        $adminContactIds = [];
        foreach (UserRepository::all() as $user) {
            if (!$user instanceof User || !$user->hasRole($adminRole) || $user->id < 1) {
                continue;
            }
            $cid = self::resolveContactIdForUser($user);
            if ($cid > 0) {
                $adminContactIds[$cid] = true;
            }
        }

        $ownership = CompanyExtendedSettings::resolveExecutiveOwnership();
        $ownerUserIds = $ownership['owner_user_ids'];
        $chefUserId = (int) $ownership['leader_user_id'];

        $ownerContactIds = [];
        foreach ($ownerUserIds as $uid) {
            $user = UserRepository::findById((int) $uid);
            if ($user === null) {
                continue;
            }
            $cid = self::resolveContactIdForUser($user);
            if ($cid > 0) {
                $ownerContactIds[] = $cid;
            }
        }

        $chefContactId = 0;
        if ($chefUserId > 0) {
            $chefUser = UserRepository::findById($chefUserId);
            if ($chefUser !== null) {
                $chefContactId = self::resolveContactIdForUser($chefUser);
            }
        }
        if ($chefContactId < 1) {
            foreach (array_keys($adminContactIds) as $cid) {
                $chefContactId = (int) $cid;
                break;
            }
        }
        if ($chefContactId < 1 && $ownerContactIds !== []) {
            $chefContactId = (int) $ownerContactIds[0];
        }

        if ($chefContactId < 1) {
            $done = true;

            return;
        }

        $existing = [];
        $stmt = $pdo->prepare(
            'SELECT contact_id, member_role FROM dg_department_members WHERE department_id = :department_id'
        );
        $stmt->execute(['department_id' => $deptId]);
        while ($row = $stmt->fetch()) {
            $existing[(int) $row['contact_id']] = (string) $row['member_role'];
        }

        $upsert = $pdo->prepare(
            'INSERT INTO dg_department_members (department_id, contact_id, member_role)
             VALUES (:department_id, :contact_id, :member_role)
             ON DUPLICATE KEY UPDATE member_role = VALUES(member_role)'
        );

        $desired = [];
        foreach ($ownerContactIds as $cid) {
            $desired[$cid] = 'member';
        }
        foreach (array_keys($adminContactIds) as $cid) {
            $desired[(int) $cid] = 'member';
        }
        $desired[$chefContactId] = 'leader';

        $changed = false;
        foreach ($desired as $cid => $role) {
            if (($existing[$cid] ?? '') === $role) {
                continue;
            }
            $upsert->execute([
                'department_id' => $deptId,
                'contact_id' => $cid,
                'member_role' => $role,
            ]);
            $changed = true;
            $existing[$cid] = $role;
        }

        foreach ($existing as $cid => $role) {
            if ($cid === $chefContactId || $role !== 'leader') {
                continue;
            }
            $upsert->execute([
                'department_id' => $deptId,
                'contact_id' => $cid,
                'member_role' => 'member',
            ]);
            $changed = true;
        }

        if ($changed) {
            DepartmentAccess::resetCache();
        }
        $done = true;
    }

    public static function resolveExecutiveDepartmentId(?PDO $pdo = null): ?string
    {
        if ($pdo === null) {
            if (!self::useDatabase()) {
                return null;
            }
            $pdo = Database::pdo();
        }

        $preferred = null;
        $byName = null;
        foreach ($pdo->query('SELECT id, name FROM dg_departments') as $row) {
            $id = (string) ($row['id'] ?? '');
            $name = mb_strtolower((string) ($row['name'] ?? ''));
            if ($id === 'dept-geschaeftsfuehrung') {
                $preferred = $id;
                break;
            }
            if ($byName === null
                && (str_contains($name, 'geschäftsführ') || str_contains($name, 'geschaeftsfuehr'))
            ) {
                $byName = $id;
            }
        }

        return $preferred ?? $byName;
    }

    public static function isExecutiveDepartment(string $departmentId, string $name = ''): bool
    {
        $departmentId = trim($departmentId);
        if ($departmentId === 'dept-geschaeftsfuehrung') {
            return true;
        }
        $nameLower = mb_strtolower($name);

        return str_contains($nameLower, 'geschäftsführ')
            || str_contains($nameLower, 'geschaeftsfuehr');
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

    /**
     * @param array $raw
     * @return list<array{id: string, name: string, description: string, members: list<array{contact_id: int, role: string}>}>
     */
    private static function sanitizeDepartments(array $raw): array
    {
        $assignable = [];
        foreach (self::assignableStaffContacts() as $contact) {
            $assignable[(int) $contact['id']] = true;
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
                    $contactId = (int) ($member['contact_id'] ?? $member['user_id'] ?? 0);
                    if ($contactId < 1 || !isset($assignable[$contactId])) {
                        continue;
                    }
                    $role = (string) ($member['role'] ?? 'member');
                    if (!in_array($role, ['member', 'leader'], true)) {
                        $role = 'member';
                    }
                    $members[] = [
                        'contact_id' => $contactId,
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

    private static function idInCurrentBatch(string $id, array $batch): bool
    {
        foreach ($batch as $row) {
            if ($row['id'] === $id) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string, true> $used
     */
    private static function generateId(string $name, array &$used): string
    {
        $base = strtolower(trim($name));
        $base = preg_replace('/[^a-z0-9]+/i', '-', $base) ?? 'dept';
        $base = trim($base, '-');
        if ($base === '') {
            $base = 'dept';
        }
        if (strlen($base) > 48) {
            $base = substr($base, 0, 48);
        }
        $id = $base;
        $suffix = 2;
        while (isset($used[$id])) {
            $id = $base . '-' . $suffix;
            ++$suffix;
        }
        $used[$id] = true;

        return $id;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function fromFile(): array
    {
        $departments = DefaultDepartments::withModulesAndMembers(DefaultDepartments::membersFromConfigFile());
        foreach ($departments as $i => $dept) {
            $mapped = [];
            foreach ($dept['members'] as $member) {
                $mapped[] = [
                    'contact_id' => 0,
                    'user_id' => (int) ($member['user_id'] ?? 0),
                    'role' => (string) ($member['role'] ?? 'member'),
                ];
            }
            $departments[$i]['members'] = $mapped;
        }

        return $departments;
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

    private static function membersUseContactId(PDO $pdo): bool
    {
        return self::pdoColumnExists($pdo, 'dg_department_members', 'contact_id')
            && !self::pdoColumnExists($pdo, 'dg_department_members', 'user_id');
    }

    private static function pdoTableExists(PDO $pdo, string $table): bool
    {
        $stmt = $pdo->query('SHOW TABLES LIKE ' . $pdo->quote($table));

        return $stmt !== false && $stmt->fetchColumn() !== false;
    }

    private static function pdoColumnExists(PDO $pdo, string $table, string $column): bool
    {
        $stmt = $pdo->query('SHOW COLUMNS FROM `' . str_replace('`', '``', $table) . '` LIKE ' . $pdo->quote($column));

        return $stmt !== false && $stmt->fetchColumn() !== false;
    }

    /**
     * @param array<string, mixed> $row
     */
    private static function contactLabelFromRow(array $row): string
    {
        $label = trim((string) ($row['display_name'] ?? ''));
        if ($label === '') {
            $label = trim((string) ($row['company_name'] ?? ''));
        }
        if ($label === '') {
            $label = trim(trim((string) ($row['first_name'] ?? '')) . ' ' . trim((string) ($row['last_name'] ?? '')));
        }

        return $label !== '' ? $label : ('Kontakt #' . (int) ($row['id'] ?? 0));
    }
}
