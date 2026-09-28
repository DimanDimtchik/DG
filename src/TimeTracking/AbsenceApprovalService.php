<?php
declare(strict_types=1);

/**
 * Wer darf Abwesenheitsanträge freigeben?
 *
 * Default-Kette: Abteilungsleiter → HR → Geschäftsführung (Chef) → Admin.
 * Optional: fester Freigeber global oder je Abteilung (Kontakt-ID).
 */
final class AbsenceApprovalService
{
    /**
     * Darf $user den Antrag von Mitarbeiter-Kontakt $employeeContactId freigeben/ablehnen?
     */
    public static function canDecide(User $user, int $employeeContactId): bool
    {
        if ($employeeContactId < 1) {
            return false;
        }
        if (RoleResolver::isAdmin($user)) {
            return true;
        }
        $uid = (int) ($user->id ?? 0);
        if ($uid < 1) {
            return false;
        }

        return in_array($uid, self::approverUserIdsForEmployee($employeeContactId), true);
    }

    /**
     * @return list<int> CRM-User-IDs
     */
    public static function approverUserIdsForEmployee(int $employeeContactId): array
    {
        if ($employeeContactId < 1 || !Database::isConfigured()) {
            return self::adminUserIds();
        }

        MigrationRunner::runPending();
        $cfg = TimeTrackingSettings::config();
        $deptIds = self::departmentIdsForEmployeeContact($employeeContactId);

        // 1) Feste Freigeber je Abteilung des Mitarbeiters
        $byDept = is_array($cfg['absence_approver_by_department'] ?? null)
            ? $cfg['absence_approver_by_department']
            : [];
        $overrideContactIds = [];
        foreach ($deptIds as $deptId) {
            $cid = (int) ($byDept[$deptId] ?? 0);
            if ($cid > 0) {
                $overrideContactIds[] = $cid;
            }
        }
        if ($overrideContactIds !== []) {
            $users = self::userIdsFromContactIds($overrideContactIds);
            if ($users !== []) {
                return $users;
            }
        }

        // 2) Globaler fester Freigeber
        $globalCid = (int) ($cfg['absence_approver_contact_id'] ?? 0);
        if ($globalCid > 0) {
            $users = self::userIdsFromContactIds([$globalCid]);
            if ($users !== []) {
                return $users;
            }
        }

        // 3) Auto: Abteilungsleiter
        $leaders = self::leaderUserIdsForDepartments($deptIds);
        if ($leaders !== []) {
            return $leaders;
        }

        // 4) Auto: HR-Abteilung
        $hr = self::hrUserIds();
        if ($hr !== []) {
            return $hr;
        }

        // 5) Auto: Geschäftsführung / Chef
        $exec = self::executiveUserIds();
        if ($exec !== []) {
            return $exec;
        }

        return self::adminUserIds();
    }

    /**
     * Kurzbeschreibung für UI (Einstellungen / Debug).
     *
     * @return array{mode: string, label: string, user_ids: list<int>}
     */
    public static function describeForEmployee(int $employeeContactId): array
    {
        $cfg = TimeTrackingSettings::config();
        $deptIds = self::departmentIdsForEmployeeContact($employeeContactId);
        $byDept = is_array($cfg['absence_approver_by_department'] ?? null)
            ? $cfg['absence_approver_by_department']
            : [];
        foreach ($deptIds as $deptId) {
            if ((int) ($byDept[$deptId] ?? 0) > 0) {
                return [
                    'mode' => 'department_override',
                    'label' => 'Fester Freigeber (Abteilung)',
                    'user_ids' => self::approverUserIdsForEmployee($employeeContactId),
                ];
            }
        }
        if ((int) ($cfg['absence_approver_contact_id'] ?? 0) > 0) {
            return [
                'mode' => 'global_override',
                'label' => 'Fester Freigeber (alle)',
                'user_ids' => self::approverUserIdsForEmployee($employeeContactId),
            ];
        }
        if (self::leaderUserIdsForDepartments($deptIds) !== []) {
            return [
                'mode' => 'leader',
                'label' => 'Abteilungsleiter',
                'user_ids' => self::approverUserIdsForEmployee($employeeContactId),
            ];
        }
        if (self::hrUserIds() !== []) {
            return [
                'mode' => 'hr',
                'label' => 'Personal / HR',
                'user_ids' => self::approverUserIdsForEmployee($employeeContactId),
            ];
        }
        if (self::executiveUserIds() !== []) {
            return [
                'mode' => 'executive',
                'label' => 'Geschäftsführung',
                'user_ids' => self::approverUserIdsForEmployee($employeeContactId),
            ];
        }

        return [
            'mode' => 'admin',
            'label' => 'Administrator',
            'user_ids' => self::approverUserIdsForEmployee($employeeContactId),
        ];
    }

    /**
     * @return list<string>
     */
    public static function departmentIdsForEmployeeContact(int $employeeContactId): array
    {
        if ($employeeContactId < 1 || !Database::isConfigured()) {
            return [];
        }
        $ids = [];
        foreach (DepartmentRepository::allWithMembers() as $department) {
            foreach ($department['members'] ?? [] as $member) {
                if ((int) ($member['contact_id'] ?? 0) !== $employeeContactId) {
                    continue;
                }
                $id = trim((string) ($department['id'] ?? ''));
                if ($id !== '') {
                    $ids[] = $id;
                }
                break;
            }
        }

        return $ids;
    }

    /**
     * @param list<int> $contactIds
     * @return list<int>
     */
    private static function userIdsFromContactIds(array $contactIds): array
    {
        $out = [];
        $seen = [];
        foreach ($contactIds as $cid) {
            $cid = (int) $cid;
            if ($cid < 1) {
                continue;
            }
            $user = self::userForStaffContact($cid);
            if ($user === null) {
                continue;
            }
            $uid = (int) ($user->id ?? 0);
            if ($uid < 1 || isset($seen[$uid])) {
                continue;
            }
            $seen[$uid] = true;
            $out[] = $uid;
        }

        return $out;
    }

    private static function userForStaffContact(int $contactId): ?User
    {
        $contact = ContactRepository::findById($contactId);
        if ($contact === null) {
            return null;
        }
        $email = strtolower(trim((string) ($contact->email ?? '')));
        if ($email !== '') {
            $user = UserRepository::findByEmail($email);
            if ($user !== null) {
                return $user;
            }
        }
        $login = trim((string) ($contact->login ?? ''));
        if ($login !== '') {
            return UserRepository::findByUsername($login);
        }

        return null;
    }

    /**
     * @param list<string> $departmentIds
     * @return list<int>
     */
    private static function leaderUserIdsForDepartments(array $departmentIds): array
    {
        if ($departmentIds === []) {
            return [];
        }
        $lookup = array_fill_keys($departmentIds, true);
        $out = [];
        $seen = [];
        foreach (DepartmentRepository::allWithMembers() as $department) {
            $deptId = (string) ($department['id'] ?? '');
            if ($deptId === '' || !isset($lookup[$deptId])) {
                continue;
            }
            foreach ($department['members'] ?? [] as $member) {
                if (($member['role'] ?? '') !== 'leader') {
                    continue;
                }
                $uid = (int) ($member['user_id'] ?? 0);
                if ($uid < 1 || isset($seen[$uid])) {
                    continue;
                }
                $seen[$uid] = true;
                $out[] = $uid;
            }
        }

        return $out;
    }

    /**
     * @return list<int>
     */
    private static function hrUserIds(): array
    {
        $out = [];
        $seen = [];
        foreach (DepartmentRepository::allWithMembers() as $department) {
            if (empty($department['is_hr'])) {
                continue;
            }
            foreach ($department['members'] ?? [] as $member) {
                $uid = (int) ($member['user_id'] ?? 0);
                if ($uid < 1 || isset($seen[$uid])) {
                    continue;
                }
                $seen[$uid] = true;
                $out[] = $uid;
            }
        }

        return $out;
    }

    /**
     * @return list<int>
     */
    private static function executiveUserIds(): array
    {
        $out = [];
        $seen = [];
        $fallbackIds = ['dept-geschaeftsfuehrung'];
        foreach (DepartmentRepository::allWithMembers() as $department) {
            $deptId = (string) ($department['id'] ?? '');
            $name = mb_strtolower((string) ($department['name'] ?? ''));
            $isExecutive = in_array($deptId, $fallbackIds, true)
                || str_contains($name, 'geschäftsführ')
                || str_contains($name, 'geschaeftsfuehr')
                || str_contains($name, 'leitung');
            if (!$isExecutive) {
                continue;
            }
            $leaders = [];
            $members = [];
            foreach ($department['members'] ?? [] as $member) {
                $uid = (int) ($member['user_id'] ?? 0);
                if ($uid < 1) {
                    continue;
                }
                if (($member['role'] ?? '') === 'leader') {
                    $leaders[] = $uid;
                } else {
                    $members[] = $uid;
                }
            }
            foreach (array_merge($leaders, $members) as $uid) {
                if (isset($seen[$uid])) {
                    continue;
                }
                $seen[$uid] = true;
                $out[] = $uid;
            }
        }

        return $out;
    }

    /**
     * @return list<int>
     */
    private static function adminUserIds(): array
    {
        $adminRole = (string) App::config('roles.admin', 'administrator');
        $out = [];
        foreach (UserRepository::all() as $user) {
            if (!$user instanceof User || !$user->hasRole($adminRole)) {
                continue;
            }
            $uid = (int) ($user->id ?? 0);
            if ($uid > 0) {
                $out[] = $uid;
            }
        }

        return $out;
    }
}
