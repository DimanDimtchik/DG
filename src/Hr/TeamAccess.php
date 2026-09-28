<?php
declare(strict_types=1);

/**
 * Rechte für HR-Teams.
 * Verwalten: Admin, HR-Abteilung, oder Abteilungsleiter („Planner“) der Team-Abteilung —
 * Leiter nur, wenn der Kontakt einen CRM-User hat und der aktuelle User dieser ist.
 */
final class TeamAccess
{
    public static function canView(User $user): bool
    {
        if (RoleResolver::isCustomer($user)) {
            return false;
        }

        return RoleResolver::canEdit($user);
    }

    public static function canManageAny(User $user): bool
    {
        if (!self::canView($user)) {
            return false;
        }
        if (RoleResolver::isAdmin($user)) {
            return true;
        }
        if (DepartmentAccess::userInHrDepartment($user)) {
            return true;
        }

        return self::isLeaderOfAnyDepartment($user);
    }

    public static function canManageDepartment(User $user, string $departmentId): bool
    {
        if (!self::canView($user)) {
            return false;
        }
        if (RoleResolver::isAdmin($user) || DepartmentAccess::userInHrDepartment($user)) {
            return true;
        }

        return self::isLeaderOfDepartment($user, $departmentId);
    }

    public static function canManageTeam(User $user, array $team): bool
    {
        $departmentId = trim((string) ($team['department_id'] ?? ''));

        return $departmentId !== '' && self::canManageDepartment($user, $departmentId);
    }

    private static function isLeaderOfAnyDepartment(User $user): bool
    {
        $userContactId = DepartmentRepository::resolveContactIdForUser($user);
        if ($userContactId < 1) {
            return false;
        }

        foreach (DepartmentRepository::allWithMembers() as $dept) {
            foreach ($dept['members'] as $member) {
                if ((int) ($member['contact_id'] ?? 0) === $userContactId
                    && (string) ($member['role'] ?? '') === 'leader'
                    && (int) ($member['user_id'] ?? 0) === $user->id
                ) {
                    return true;
                }
            }
        }

        return false;
    }

    private static function isLeaderOfDepartment(User $user, string $departmentId): bool
    {
        $departmentId = trim($departmentId);
        if ($departmentId === '') {
            return false;
        }
        $userContactId = DepartmentRepository::resolveContactIdForUser($user);
        if ($userContactId < 1) {
            return false;
        }

        foreach (DepartmentRepository::allWithMembers() as $dept) {
            if ((string) ($dept['id'] ?? '') !== $departmentId) {
                continue;
            }
            foreach ($dept['members'] as $member) {
                if ((int) ($member['contact_id'] ?? 0) === $userContactId
                    && (string) ($member['role'] ?? '') === 'leader'
                    && (int) ($member['user_id'] ?? 0) === $user->id
                ) {
                    return true;
                }
            }

            return false;
        }

        return false;
    }
}
