<?php
declare(strict_types=1);

final class RoleResolver
{
    public static function isAdmin(User $user): bool
    {
        return $user->hasRole((string) App::config('roles.admin', 'administrator'));
    }

    public static function isActiveEmployee(User $user): bool
    {
        $employeeRole = (string) App::config('roles.employee', 'dg_eigenmitarbeiter');

        if (!$user->hasRole($employeeRole)) {
            return false;
        }

        return $user->employeeActive;
    }

    public static function isCustomer(User $user): bool
    {
        return $user->hasRole((string) App::config('roles.customer', 'dg_kunde'));
    }

    public static function isStaff(User $user): bool
    {
        return self::isAdmin($user) || self::isActiveEmployee($user);
    }

    public static function canAccessCrm(User $user): bool
    {
        return self::isStaff($user) || self::isCustomer($user);
    }

    public static function canEdit(User $user): bool
    {
        return self::isAdmin($user) || self::isActiveEmployee($user);
    }

    public static function canRead(User $user): bool
    {
        return self::canAccessCrm($user);
    }

    public static function homePath(User $user): string
    {
        if (self::isCustomer($user)) {
            return '/app?area=profile';
        }

        return '/app';
    }

    /** Kunde: nur Profil. Mitarbeiter/Admin: volle Navigation. */
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
     * @return 'admin'|'department'|null
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

    public static function roleLabel(User $user): string
    {
        $slug = (string) ($user->roles[0] ?? '');

        return $slug !== '' ? CrmRole::label($slug) : '—';
    }

    /** @return list<array{id: string, name: string, member_role: string}> */
    public static function departmentsFor(User $user): array
    {
        return UserRepository::departmentsForUser($user->id);
    }
}
