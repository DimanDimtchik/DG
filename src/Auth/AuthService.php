<?php
declare(strict_types=1);

final class AuthService
{
    private const SESSION_KEY = 'dg_user_id';

    public static function attempt(string $username, string $password): bool
    {
        $user = UserRepository::findByEmailOrUsername($username);
        if (!$user || !UserRepository::verifyPassword($user, $password)) {
            return false;
        }

        if (!RoleResolver::canAccessCrm($user)) {
            return false;
        }

        return self::loginUser($user);
    }

    public static function loginUser(User $user): bool
    {
        if (!RoleResolver::canAccessCrm($user)) {
            return false;
        }

        $_SESSION[self::SESSION_KEY] = $user->id;

        return true;
    }

    public static function user(): ?User
    {
        $id = (int) ($_SESSION[self::SESSION_KEY] ?? 0);
        if ($id < 1) {
            return null;
        }

        $user = UserRepository::findById($id);

        if (!$user || !RoleResolver::canAccessCrm($user)) {
            self::logout();

            return null;
        }

        return $user;
    }

    public static function check(): bool
    {
        return self::user() !== null;
    }

    public static function logout(): void
    {
        unset($_SESSION[self::SESSION_KEY]);
    }
}
