<?php
declare(strict_types=1);

/**
 * Lohn L2b: Rechte-Gate für MA-Lohnfelder (Sektion payroll) laut L2a.
 */
final class PayrollEmployeeAccess
{
    public static function moduleRegistered(): bool
    {
        return isset(DepartmentAccess::MODULE_LABELS['lohn']);
    }

    /**
     * Sektion Lohnabrechnung sehen.
     */
    public static function canView(User $user): bool
    {
        if (RoleResolver::isAdmin($user)) {
            return true;
        }
        if (!self::moduleRegistered()) {
            // Übergang: wie EmployeeData-Sicht — editierbare Nutzer / HR-Team
            return RoleResolver::canEdit($user) || DepartmentAccess::userInHrDepartment($user);
        }

        $level = DepartmentAccess::moduleLevel($user, 'lohn');

        return $level === 'partial' || $level === 'full';
    }

    /**
     * Sektion Lohnabrechnung speichern (POST-Übernahme).
     */
    public static function canEdit(User $user): bool
    {
        if (RoleResolver::isAdmin($user)) {
            return true;
        }
        if (!RoleResolver::canEdit($user)) {
            return false;
        }
        if (!self::moduleRegistered()) {
            return false;
        }

        $level = DepartmentAccess::moduleLevel($user, 'lohn');

        return $level === 'partial' || $level === 'full';
    }
}
