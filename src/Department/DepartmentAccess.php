<?php
declare(strict_types=1);

/** Abteilungsbezogene Modul-Sichtbarkeit (Maximum über alle Zugehörigkeiten). */
final class DepartmentAccess
{
    /** @var array<string, string> */
    public const MODULE_LABELS = [
        'dashboard' => 'Dashboard',
        'kontakte' => 'Kontakte',
        'terminkalender' => 'Terminkalender',
        'post' => 'Post',
        'buchhaltung' => 'Buchhaltung',
    ];

    /** @var array<string, int> */
    private const LEVEL_RANK = [
        'none' => 0,
        'partial' => 1,
        'full' => 2,
    ];

    /** @var array<string, array{is_hr: bool, allow_contact_delete: bool, is_purchasing: bool, allow_article_catalog: bool, modules: array<string, string>}>|null */
    private static ?array $departmentIndex = null;

    /**
     * Liefert Standard-Modulrechte.
     *
     * @return array<string, string>
     */
        public static function defaultModules(): array
    {
        return [
            'dashboard' => 'full',
            'kontakte' => 'partial',
            'terminkalender' => 'full',
            'post' => 'full',
            'buchhaltung' => 'none',
        ];
    }

    /**
     * Prüft, ob ein Zugriffslevel gültig ist
     * @param string $level
     * @return bool
     */
    public static function isValidLevel(string $level): bool
    {
        return isset(self::LEVEL_RANK[$level]);
    }

    /**
     * Normalisiert ein Zugriffslevel
     * @param string $level
     * @return string
     */
    public static function normalizeLevel(string $level): string
    {
        $level = strtolower(trim($level));

        return self::isValidLevel($level) ? $level : 'partial';
    }

        /**
     * Liefert Abteilungs-IDs eines Benutzers
     * @param User $user Angemeldeter Benutzer
     * @return list<string>
     */
    public static function departmentIdsForUser(User $user): array
    {
        $ids = [];
        foreach (RoleResolver::departmentsFor($user) as $dept) {
            $id = trim((string) ($dept['id'] ?? ''));
            if ($id !== '') {
                $ids[] = $id;
            }
        }

        return $ids;
    }

    /**
     * Prüft HR-Zugehörigkeit
     * @param User $user Angemeldeter Benutzer
     * @return bool
     */
    public static function userInHrDepartment(User $user): bool
    {
        if (RoleResolver::isAdmin($user)) {
            return false;
        }

        foreach (self::departmentIdsForUser($user) as $id) {
            $dept = self::departmentIndex()[$id] ?? null;
            if ($dept !== null && $dept['is_hr']) {
                return true;
            }
        }

        return false;
    }

    /**
     * Prüft Kontakt-Löschrecht
     * @param User $user Angemeldeter Benutzer
     * @return bool
     */
    public static function userCanDeleteContacts(User $user): bool
    {
        if (RoleResolver::isAdmin($user)) {
            return true;
        }

        foreach (self::departmentIdsForUser($user) as $id) {
            $dept = self::departmentIndex()[$id] ?? null;
            if ($dept !== null && $dept['is_hr'] && $dept['allow_contact_delete']) {
                return true;
            }
        }

        return false;
    }

    /**
     * Prüft Artikelkatalog-Recht
     * @param User $user Angemeldeter Benutzer
     * @return bool
     */
    public static function userCanManageArticleCatalog(User $user): bool
    {
        if (RoleResolver::isAdmin($user)) {
            return true;
        }

        foreach (self::departmentIdsForUser($user) as $id) {
            $dept = self::departmentIndex()[$id] ?? null;
            if ($dept !== null && $dept['allow_article_catalog']) {
                return true;
            }
        }

        return false;
    }

    /**
     * Ermittelt das effektive Modul-Level
     * @param User $user Angemeldeter Benutzer
     * @param string $module Modul-Schlüssel
     * @return string
     */
    public static function moduleLevel(User $user, string $module): string
    {
        if (RoleResolver::isAdmin($user)) {
            return 'full';
        }

        if (!isset(self::MODULE_LABELS[$module])) {
            return 'none';
        }

        $deptIds = self::departmentIdsForUser($user);
        if ($deptIds === []) {
            return self::defaultModules()[$module] ?? 'none';
        }

        $best = 0;
        foreach ($deptIds as $id) {
            $dept = self::departmentIndex()[$id] ?? null;
            $modules = $dept['modules'] ?? self::defaultModules();
            $level = self::normalizeLevel((string) ($modules[$module] ?? self::defaultModules()[$module] ?? 'none'));
            $best = max($best, self::LEVEL_RANK[$level]);
        }

        foreach (self::LEVEL_RANK as $level => $rank) {
            if ($rank === $best) {
                return $level;
            }
        }

        return 'none';
    }

    /**
     * Prüft Modulzugriff
     * @param User $user Angemeldeter Benutzer
     * @param string $module Modul-Schlüssel
     * @return bool
     */
    public static function canAccessModule(User $user, string $module): bool
    {
        return self::moduleLevel($user, $module) !== 'none';
    }

    /**
     * @param array<string, string> $modules
     * @return array<string, string>
     */
    public static function sanitizeModules(array $modules): array
    {
        $out = self::defaultModules();
        foreach (self::MODULE_LABELS as $key => $_label) {
            if (isset($modules[$key])) {
                $out[$key] = self::normalizeLevel((string) $modules[$key]);
            }
        }

        return $out;
    }

    /**
     * departmentIndex.
     *
     * @return array<string, array{is_hr: bool, allow_contact_delete: bool, is_purchasing: bool, allow_article_catalog: bool, modules: array<string, string>}>
     */
        private static function departmentIndex(): array
    {
        if (self::$departmentIndex !== null) {
            return self::$departmentIndex;
        }

        self::$departmentIndex = [];
        foreach (DepartmentRepository::allWithMembers() as $dept) {
            $id = (string) ($dept['id'] ?? '');
            if ($id === '') {
                continue;
            }
            self::$departmentIndex[$id] = [
                'is_hr' => (bool) ($dept['is_hr'] ?? false),
                'allow_contact_delete' => (bool) ($dept['allow_contact_delete'] ?? false),
                'is_purchasing' => (bool) ($dept['is_purchasing'] ?? false),
                'allow_article_catalog' => (bool) ($dept['allow_article_catalog'] ?? false)
                    || (bool) ($dept['is_purchasing'] ?? false),
                'modules' => self::sanitizeModules(is_array($dept['modules'] ?? null) ? $dept['modules'] : []),
            ];
        }

        return self::$departmentIndex;
    }

    /**
     * Leert den internen Cache
     * @return void
     */
    public static function resetCache(): void
    {
        self::$departmentIndex = null;
    }
}
