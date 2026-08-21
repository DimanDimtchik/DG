<?php
declare(strict_types=1);

/**
 * Support-Session in der Kundeninstanz (nach Token-Login).
 */
final class SupportSession
{
    private const GRANT_KEY = 'dg_support_grant_id';
    private const TOKEN_KEY = 'dg_support_token';

    public static function login(array $grant, string $token): void
    {
        session_regenerate_id(true);
        $_SESSION[self::GRANT_KEY] = (int) ($grant['id'] ?? 0);
        $_SESSION[self::TOKEN_KEY] = $token;
        unset($_SESSION['dg_user_id']);
        AuditLog::record('support_login', null, 'grant_id=' . (int) ($grant['id'] ?? 0));
    }

    public static function logout(): void
    {
        $id = self::grantId();
        unset($_SESSION[self::GRANT_KEY], $_SESSION[self::TOKEN_KEY]);
        if ($id > 0) {
            AuditLog::record('support_logout', null, 'grant_id=' . $id);
        }
    }

    public static function grantId(): int
    {
        return (int) ($_SESSION[self::GRANT_KEY] ?? 0);
    }

    public static function token(): string
    {
        return (string) ($_SESSION[self::TOKEN_KEY] ?? '');
    }

    public static function isActive(): bool
    {
        $id = self::grantId();
        if ($id < 1) {
            return false;
        }
        $grant = SupportAccessService::findById($id);
        if ($grant === null || ($grant['status'] ?? '') !== 'active') {
            self::logout();

            return false;
        }
        if (strtotime((string) ($grant['expires_at'] ?? '')) <= time()) {
            self::logout();

            return false;
        }
        $token = self::token();
        if ($token === '' || !hash_equals((string) ($grant['token_hash'] ?? ''), hash('sha256', $token))) {
            self::logout();

            return false;
        }

        return true;
    }

    public static function user(): ?User
    {
        return self::isActive() ? SupportAccessService::supportUser() : null;
    }

    /** @return array<string, mixed>|null */
    public static function grant(): ?array
    {
        if (!self::isActive()) {
            return null;
        }

        return SupportAccessService::findById(self::grantId());
    }
}
