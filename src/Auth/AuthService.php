<?php
declare(strict_types=1);

/**
 * Session-basierte CRM-Authentifizierung (Login, Logout, aktueller Benutzer).
 */
final class AuthService
{
    private const SESSION_KEY = 'dg_user_id';

    /**
     * Versucht Anmeldung mit Benutzername/E-Mail und Passwort.
     *
     * @return bool|string true bei Erfolg, false bei falschen Zugangsdaten, string bei Sperre.
     */
    public static function attempt(string $username, string $password): bool|string
    {
        $ip = Firewall::clientIp();

        $throttleMsg = LoginThrottle::check($ip);
        if ($throttleMsg !== null) {
            return $throttleMsg;
        }

        $user = UserRepository::findByEmailOrUsername($username);
        if (!$user || !UserRepository::verifyPassword($user, $password)) {
            LoginThrottle::recordFailure($ip, $username);
            AuditLog::record('login_failed', null, 'User: ' . $username);
            return false;
        }

        if (!RoleResolver::canAccessCrm($user)) {
            LoginThrottle::recordFailure($ip, $username);
            return false;
        }

        LoginThrottle::recordSuccess($ip, $username);
        AuditLog::record('login_success', $user->id);
        session_regenerate_id(true);

        return self::loginUser($user);
    }

    /**
     * Setzt die Session für einen bereits validierten Benutzer.
     */
    public static function loginUser(User $user): bool
    {
        if (!RoleResolver::canAccessCrm($user)) {
            return false;
        }

        if (class_exists('SupportSession')) {
            SupportSession::logout();
        }

        $_SESSION[self::SESSION_KEY] = $user->id;

        return true;
    }

    /**
     * Liefert den eingeloggten Benutzer oder null (mit CRM-Zugriffsprüfung).
     */
    public static function user(): ?User
    {
        if (class_exists('SupportSession')) {
            $supportUser = SupportSession::user();
            if ($supportUser !== null) {
                return $supportUser;
            }
        }

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

    /**
     * Prüft, ob ein gültiger CRM-Benutzer angemeldet ist.
     */
    public static function check(): bool
    {
        return self::user() !== null;
    }

    /**
     * Beendet die Session und protokolliert Logout.
     */
    public static function logout(): void
    {
        if (class_exists('SupportSession') && SupportSession::grantId() > 0) {
            SupportSession::logout();
        }

        $user = null;
        $id = (int) ($_SESSION[self::SESSION_KEY] ?? 0);
        if ($id > 0) {
            $user = UserRepository::findById($id);
        }
        if ($user !== null) {
            AuditLog::record('logout', $user->id);
        }
        unset($_SESSION[self::SESSION_KEY]);
    }
}
