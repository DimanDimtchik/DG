<?php
declare(strict_types=1);

final class ContactAccessResolver
{
    public static function canViewAllContactTypes(User $user): bool
    {
        return RoleResolver::isAdmin($user) || DepartmentAccess::userInHrDepartment($user);
    }

    public static function isCustomerContact(Contact $contact): bool
    {
        return CrmRole::normalize($contact->contactRole) === 'dg_kunde';
    }

    public static function canViewContact(User $user, Contact $contact): bool
    {
        if (!DepartmentAccess::canAccessModule($user, 'kontakte')) {
            return false;
        }

        if (self::canViewAllContactTypes($user)) {
            return true;
        }

        return self::isCustomerContact($contact);
    }

    public static function canEditContact(User $user, ?Contact $contact = null): bool
    {
        if (!RoleResolver::canEdit($user)) {
            return false;
        }

        if (!DepartmentAccess::canAccessModule($user, 'kontakte')) {
            return false;
        }

        if ($contact === null) {
            return true;
        }

        return self::canViewContact($user, $contact);
    }

    public static function canDeleteContact(User $user, Contact $contact): bool
    {
        if (!self::canEditContact($user, $contact)) {
            return false;
        }

        if (!self::isCustomerContact($contact)) {
            return RoleResolver::isAdmin($user);
        }

        return DepartmentAccess::userCanDeleteContacts($user);
    }

    public static function canViewEmployeeHrData(User $user, Contact $contact): bool
    {
        if (!CrmRole::hasEmployeeProfile($contact->contactRole)) {
            return false;
        }

        return self::canViewAllContactTypes($user);
    }

    /** @return array<string, string> */
    public static function allowedContactRoleOptions(User $user): array
    {
        $all = CrmRole::options();
        if (self::canViewAllContactTypes($user)) {
            return $all;
        }

        return ['dg_kunde' => $all['dg_kunde']];
    }

    public static function assertCanView(User $user, Contact $contact): void
    {
        if (!self::canViewContact($user, $contact)) {
            throw new RuntimeException('Keine Berechtigung für diesen Kontakt.');
        }
    }

    public static function assertCanEdit(User $user, Contact $contact): void
    {
        if (!self::canEditContact($user, $contact)) {
            throw new RuntimeException('Keine Berechtigung zum Bearbeiten dieses Kontakts.');
        }
    }

    public static function assertCanDelete(User $user, Contact $contact): void
    {
        if (!self::canDeleteContact($user, $contact)) {
            throw new RuntimeException('Keine Berechtigung zum Löschen dieses Kontakts.');
        }
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public static function enforceContactRoleOnSave(User $user, array $data, ?Contact $existing = null): array
    {
        $role = CrmRole::normalize((string) ($data['contact_role'] ?? 'dg_kunde'));
        $allowed = self::allowedContactRoleOptions($user);
        if (!isset($allowed[$role])) {
            if ($existing !== null) {
                $role = CrmRole::normalize($existing->contactRole);
            } else {
                $role = 'dg_kunde';
            }
        }
        $data['contact_role'] = $role;

        return $data;
    }
}
