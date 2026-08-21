<?php
declare(strict_types=1);

/**
 * Authentifizierter CRM-Benutzer (Entity).
 */
final class User
{
    /**
     * Konstruktor.
     * @param int $id Datensatz-ID
     * @param string $username
     * @param string $displayName
     * @param string $email E-Mail-Adresse
     * @param list<string> $roles
     * @param bool $employeeActive
     */
    public function __construct(
        public readonly int $id,
        public readonly string $username,
        public readonly string $displayName,
        public readonly string $email,
        public readonly array $roles,
        public readonly bool $employeeActive,
    ) {
    }

    /**
     * Prüft, ob der Benutzer die Rolle besitzt.
     * @param string $role
     * @return bool
     */
    public function hasRole(string $role): bool
    {
        return in_array($role, $this->roles, true);
    }
}
