<?php
declare(strict_types=1);

/** CRM-Benutzer mit aktivem Mitarbeiter-Konto für Postfach-Rechte. */
final class MailboxMemberResolver
{
    /** @return list<array{user_id: int, label: string, contact_id: int|null, email: string}> */
    public static function staffOptions(): array
    {
        $options = [];
        foreach (UserRepository::all() as $user) {
            if (!self::isActiveStaffUser($user->id)) {
                continue;
            }
            $contactId = ContactRepository::findStaffContactIdForUser($user);
            $label = $user->displayName !== '' ? $user->displayName : $user->username;
            if ($contactId !== null) {
                $contact = ContactRepository::findById($contactId);
                if ($contact !== null) {
                    $label = $contact->displayName !== '' ? $contact->displayName : $label;
                }
            }
            $options[] = [
                'user_id' => $user->id,
                'contact_id' => $contactId,
                'label' => $label . ' (' . $user->username . ')',
                'email' => $user->email,
            ];
        }

        usort($options, static fn(array $a, array $b): int => strcasecmp($a['label'], $b['label']));

        return $options;
    }

    public static function isActiveStaffUser(int $userId): bool
    {
        $user = UserRepository::findById($userId);
        if ($user === null) {
            return false;
        }
        if (RoleResolver::isCustomer($user)) {
            return false;
        }

        return RoleResolver::isAdmin($user) || RoleResolver::isActiveEmployee($user);
    }

    public static function findUserIdForContact(Contact $contact): ?int
    {
        $email = trim($contact->email);
        if ($email !== '') {
            foreach (UserRepository::all() as $user) {
                if (strcasecmp($user->email, $email) === 0 && self::isActiveStaffUser($user->id)) {
                    return $user->id;
                }
            }
        }

        $login = trim($contact->login);
        if ($login !== '') {
            $user = UserRepository::findByUsername($login);
            if ($user !== null && self::isActiveStaffUser($user->id)) {
                return $user->id;
            }
        }

        return null;
    }
}
