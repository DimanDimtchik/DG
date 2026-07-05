<?php
declare(strict_types=1);

final class User
{
    /** @param list<string> $roles */
    public function __construct(
        public readonly int $id,
        public readonly string $username,
        public readonly string $displayName,
        public readonly string $email,
        public readonly array $roles,
        public readonly bool $employeeActive,
    ) {
    }

    public function hasRole(string $role): bool
    {
        return in_array($role, $this->roles, true);
    }
}
