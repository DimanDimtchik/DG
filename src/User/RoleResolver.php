<?php
declare(strict_types=1);

/**
 * Ermittelt CRM-Rollen, Rechte und Navigationszugriff.
 */
final class RoleResolver
{
    /**
     * Prüft: is admin.
     * @param User $user
     * @return bool
     */
    public static function isAdmin(User $user): bool
    {
        return $user->hasRole((string) App::config('roles.admin', 'administrator'));
    }

    /**
     * Prüft: is active employee.
     * @param User $user
     * @return bool
     */
    public static function isActiveEmployee(User $user): bool
    {
        $employeeRole = (string) App::config('roles.employee', 'dg_eigenmitarbeiter');

        if (!$user->hasRole($employeeRole)) {
            return false;
        }

        return $user->employeeActive;
    }

    /**
     * Prüft: is customer.
     * @param User $user
     * @return bool
     */
    public static function isCustomer(User $user): bool
    {
        return $user->hasRole((string) App::config('roles.customer', 'dg_kunde'));
    }

    /**
     * Prüft: is staff.
     * @param User $user
     * @return bool
     */
    public static function isStaff(User $user): bool
    {
        return self::isAdmin($user) || self::isActiveEmployee($user);
    }

    /**
     * Prüft: can access crm.
     * @param User $user
     * @return bool
     */
    public static function canAccessCrm(User $user): bool
    {
        return self::isStaff($user) || self::isCustomer($user);
    }

    /**
     * Prüft: can edit.
     * @param User $user
     * @return bool
     */
    public static function canEdit(User $user): bool
    {
        return self::isAdmin($user) || self::isActiveEmployee($user);
    }

    /**
     * Prüft: can read.
     * @param User $user
     * @return bool
     */
    public static function canRead(User $user): bool
    {
        return self::canAccessCrm($user);
    }

    /**
     * Methode home path.
     * @param User $user
     * @return string
     */
    public static function homePath(User $user): string
    {
        if (self::isCustomer($user)) {
            return '/app?area=profile';
        }

        return '/app';
    }

    /**
     * Prüft: can access area.
     * @param User $user
     * @param string $page
     * @param string|null $area
     * @return bool
     */
    public static function canAccessArea(User $user, string $page, ?string $area = null): bool
    {
        if (self::isCustomer($user)) {
            return $area === 'profile';
        }

        if ($area === 'profile') {
            return true;
        }

        if ($area === 'users' || $area === 'departments') {
            return self::isAdmin($user);
        }

        if ($page === 'dashboard' || $page === '') {
            return self::isStaff($user) && DepartmentAccess::canAccessModule($user, 'dashboard');
        }

        return MenuRegistry::canAccess($user, $page);
    }

    /**
     * Methode nav mode.
     * @param User $user
     * @return string|null
     */
    public static function navMode(User $user): ?string
    {
        if (self::isCustomer($user)) {
            return null;
        }

        if (self::isAdmin($user)) {
            return 'admin';
        }

        if (self::isActiveEmployee($user) && self::departmentsFor($user) !== []) {
            return 'department';
        }

        return null;
    }

    /**
     * Methode role label.
     * @param User $user
     * @return string
     */
    public static function roleLabel(User $user): string
    {
        $slug = (string) ($user->roles[0] ?? '');

        return $slug !== '' ? CrmRole::label($slug) : '—';
    }

    /**
     * Methode departments for.
     * @param User $user
     * @return array<string, mixed>
     */
    public static function departmentsFor(User $user): array
    {
        return UserRepository::departmentsForUser($user->id);
    }
}
