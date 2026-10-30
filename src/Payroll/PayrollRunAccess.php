<?php
declare(strict_types=1);

/**
 * Lohn L5b: Rechte für Monatslauf-Aktionen (L0c / L5a).
 */
final class PayrollRunAccess
{
    public static function canView(User $user): bool
    {
        return PayrollEmployeeAccess::canView($user);
    }

    /** Entwurf anlegen, Lines, calculate, reopen. */
    public static function canEditRun(User $user): bool
    {
        return PayrollEmployeeAccess::canEdit($user);
    }

    /** Freigabe, Sperre, Storno/Correction. */
    public static function canApprove(User $user): bool
    {
        if (RoleResolver::isAdmin($user)) {
            return true;
        }
        if (!RoleResolver::canEdit($user)) {
            return false;
        }
        if (!PayrollEmployeeAccess::moduleRegistered()) {
            return false;
        }

        return DepartmentAccess::moduleLevel($user, 'lohn') === 'full';
    }

    /** FiBu-Übernahme (L7): full + Buchhaltung. */
    public static function canPostFibu(User $user): bool
    {
        if (!self::canApprove($user)) {
            return false;
        }

        return MenuRegistry::canAccessBuchhaltung($user);
    }

    /** SEPA-Auszahlung (L8): lohn full — Live-Flag steuert Probe vs. produktiv. */
    public static function canExportSepa(User $user): bool
    {
        return self::canApprove($user);
    }

    /** LStA ERiC-Test (L10b): lohn full. Live erst L10c. */
    public static function canSubmitLstaTest(User $user): bool
    {
        return self::canApprove($user);
    }
}
