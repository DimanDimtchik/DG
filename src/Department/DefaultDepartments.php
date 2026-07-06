<?php
declare(strict_types=1);

/** Standard-Abteilungen bei frischer CRM-Installation (leere dg_departments). */
final class DefaultDepartments
{
    /**
     * @return list<array{
     *   id: string,
     *   name: string,
     *   description: string,
     *   is_hr: bool,
     *   allow_contact_delete: bool
     * }>
     */
    public static function definitions(): array
    {
        return [
            ['id' => 'dept-geschaeftsfuehrung', 'name' => 'Geschäftsführung', 'description' => '', 'is_hr' => false, 'allow_contact_delete' => false],
            ['id' => 'dept-verwaltung', 'name' => 'Verwaltung', 'description' => 'Interne Verwaltung', 'is_hr' => false, 'allow_contact_delete' => false],
            ['id' => 'dept-buchhaltung', 'name' => 'Buchhaltung', 'description' => 'Finanzen und Buchhaltung', 'is_hr' => false, 'allow_contact_delete' => false],
            ['id' => 'dept-personal', 'name' => 'Personal', 'description' => 'Personalwesen und HR', 'is_hr' => true, 'allow_contact_delete' => false],
            ['id' => 'dept-einkauf', 'name' => 'Einkauf', 'description' => '', 'is_hr' => false, 'allow_contact_delete' => false, 'is_purchasing' => false, 'allow_article_catalog' => true],
            ['id' => 'dept-lager', 'name' => 'Lager', 'description' => '', 'is_hr' => false, 'allow_contact_delete' => false],
            ['id' => 'dept-produktion', 'name' => 'Produktion', 'description' => '', 'is_hr' => false, 'allow_contact_delete' => false],
            ['id' => 'dept-technik', 'name' => 'Technik', 'description' => '', 'is_hr' => false, 'allow_contact_delete' => false],
            ['id' => 'dept-it', 'name' => 'IT', 'description' => '', 'is_hr' => false, 'allow_contact_delete' => false],
            ['id' => 'dept-marketing', 'name' => 'Marketing', 'description' => '', 'is_hr' => false, 'allow_contact_delete' => false],
            ['id' => 'dept-assistenz', 'name' => 'Assistenz', 'description' => '', 'is_hr' => false, 'allow_contact_delete' => false],
        ];
    }

    /**
     * @param array<string, list<array{user_id: int, role: string}>> $membersByDepartmentId
     * @return list<array{
     *   id: string,
     *   name: string,
     *   description: string,
     *   sort_order: int,
     *   is_hr: bool,
     *   allow_contact_delete: bool,
     *   members: list<array{user_id: int, role: string}>,
     *   modules: array<string, string>
     * }>
     */
    public static function withModulesAndMembers(array $membersByDepartmentId = []): array
    {
        $departments = [];
        foreach (self::definitions() as $sortOrder => $definition) {
            $id = $definition['id'];
            $departments[] = [
                'id' => $id,
                'name' => $definition['name'],
                'description' => $definition['description'],
                'sort_order' => $sortOrder,
                'is_hr' => $definition['is_hr'],
                'allow_contact_delete' => $definition['allow_contact_delete'],
                'is_purchasing' => (bool) ($definition['is_purchasing'] ?? false),
                'allow_article_catalog' => (bool) ($definition['allow_article_catalog'] ?? false),
                'members' => $membersByDepartmentId[$id] ?? [],
                'modules' => self::modulesForDepartment($id),
            ];
        }

        return $departments;
    }

    /** @return array<string, string> */
    public static function modulesForDepartment(string $departmentId): array
    {
        $modules = DepartmentAccess::defaultModules();
        if ($departmentId === 'dept-buchhaltung') {
            $modules['buchhaltung'] = 'full';
        }

        return $modules;
    }

    /**
     * Demo-Zuordnungen aus config/users.php (Schlüssel department_members).
     *
     * @return array<string, list<array{user_id: int, role: string}>>
     */
    public static function membersFromConfigFile(): array
    {
        $path = DG_ROOT . '/config/users.php';
        if (!is_readable($path)) {
            return [];
        }

        $data = require $path;
        if (isset($data['department_members']) && is_array($data['department_members'])) {
            return self::normalizeMembersMap($data['department_members']);
        }

        $map = [];
        foreach (($data['departments'] ?? []) as $department) {
            if (!is_array($department)) {
                continue;
            }
            $id = trim((string) ($department['id'] ?? ''));
            if ($id === '') {
                continue;
            }
            $members = [];
            foreach (($department['members'] ?? []) as $member) {
                if (!is_array($member)) {
                    continue;
                }
                $userId = (int) ($member['user_id'] ?? 0);
                if ($userId < 1) {
                    continue;
                }
                $role = (string) ($member['role'] ?? 'member');
                $members[] = [
                    'user_id' => $userId,
                    'role' => in_array($role, ['member', 'leader'], true) ? $role : 'member',
                ];
            }
            if ($members !== []) {
                $map[$id] = $members;
            }
        }

        return $map;
    }

    /**
     * @param array<string, mixed> $raw
     * @return array<string, list<array{user_id: int, role: string}>>
     */
    private static function normalizeMembersMap(array $raw): array
    {
        $map = [];
        foreach ($raw as $departmentId => $members) {
            if (!is_string($departmentId) || !is_array($members)) {
                continue;
            }
            $id = trim($departmentId);
            if ($id === '') {
                continue;
            }
            $normalized = [];
            foreach ($members as $member) {
                if (!is_array($member)) {
                    continue;
                }
                $userId = (int) ($member['user_id'] ?? 0);
                if ($userId < 1) {
                    continue;
                }
                $role = (string) ($member['role'] ?? 'member');
                $normalized[] = [
                    'user_id' => $userId,
                    'role' => in_array($role, ['member', 'leader'], true) ? $role : 'member',
                ];
            }
            if ($normalized !== []) {
                $map[$id] = $normalized;
            }
        }

        return $map;
    }
}
