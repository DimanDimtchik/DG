<?php
declare(strict_types=1);

/** Ermittelt E-Mail-Empfänger für Überstunden-Erinnerungen (HR → Leiter → Chef → Admin). */
final class OvertimeNotificationRecipients
{
    /**
     * Verantwortliche: Personal + Abteilungsleiter; Fallback Geschäftsführung, dann Admin.
     *
     * @return list<string>
     */
    public static function managerEmailsForEmployee(?int $employeeContactId = null): array
    {
        if (!Database::isConfigured()) {
            return self::uniqueValidEmails(self::adminFallbackEmails());
        }

        MigrationRunner::runPending();

        $hrEmails = self::emailsForHrDepartments();
        $leaderEmails = self::emailsForEmployeeDepartmentLeaders($employeeContactId);

        $emails = self::uniqueValidEmails(array_merge($hrEmails, $leaderEmails));
        if ($emails !== []) {
            return $emails;
        }

        $executiveEmails = self::emailsForExecutiveDepartment();
        $emails = self::uniqueValidEmails($executiveEmails);
        if ($emails !== []) {
            return $emails;
        }

        return self::uniqueValidEmails(self::adminFallbackEmails());
    }

    /**
     * Alle Verantwortlichen für Sammel-Liste (HR + alle Leiter; gleiche Fallback-Kette).
     *
     * @return list<string>
     */
    public static function managerEmailsForDigest(): array
    {
        if (!Database::isConfigured()) {
            return self::uniqueValidEmails(self::adminFallbackEmails());
        }

        MigrationRunner::runPending();

        $hrEmails = self::emailsForHrDepartments();
        $leaderEmails = self::emailsForAllDepartmentLeaders();

        $emails = self::uniqueValidEmails(array_merge($hrEmails, $leaderEmails));
        if ($emails !== []) {
            return $emails;
        }

        $executiveEmails = self::emailsForExecutiveDepartment();
        $emails = self::uniqueValidEmails($executiveEmails);
        if ($emails !== []) {
            return $emails;
        }

        return self::uniqueValidEmails(self::adminFallbackEmails());
    }

    public static function employeeEmailForContact(int $contactId): ?string
    {
        if ($contactId < 1 || !Database::isConfigured()) {
            return null;
        }

        $contact = ContactRepository::findById($contactId);
        if ($contact === null) {
            return null;
        }

        foreach ([$contact->email, $contact->email2] as $candidate) {
            $email = strtolower(trim((string) $candidate));
            if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                return $email;
            }
        }

        $login = trim((string) ($contact->login ?? ''));
        if ($login !== '' && str_contains($login, '@')) {
            $email = strtolower($login);
            if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                return $email;
            }
        }

        if ($login !== '') {
            $user = UserRepository::findByUsername($login);
            if ($user !== null) {
                $email = strtolower(trim($user->email));
                if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    return $email;
                }
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    private static function emailsForHrDepartments(): array
    {
        $emails = [];
        foreach (DepartmentRepository::allWithMembers() as $department) {
            if (empty($department['is_hr'])) {
                continue;
            }
            foreach ($department['members'] ?? [] as $member) {
                $email = self::emailForUserId((int) ($member['user_id'] ?? 0));
                if ($email !== null) {
                    $emails[] = $email;
                }
            }
        }

        return $emails;
    }

    /**
     * @return list<string>
     */
    private static function emailsForEmployeeDepartmentLeaders(?int $employeeContactId): array
    {
        $departmentIds = self::departmentIdsForEmployeeContact($employeeContactId);
        if ($departmentIds === []) {
            return self::emailsForAllDepartmentLeaders();
        }

        return self::emailsForLeadersInDepartments($departmentIds);
    }

    /**
     * @return list<string>
     */
    private static function emailsForAllDepartmentLeaders(): array
    {
        $emails = [];
        foreach (DepartmentRepository::allWithMembers() as $department) {
            foreach ($department['members'] ?? [] as $member) {
                if (($member['role'] ?? '') !== 'leader') {
                    continue;
                }
                $email = self::emailForUserId((int) ($member['user_id'] ?? 0));
                if ($email !== null) {
                    $emails[] = $email;
                }
            }
        }

        return $emails;
    }

    /**
     * @param list<string> $departmentIds
     * @return list<string>
     */
    private static function emailsForLeadersInDepartments(array $departmentIds): array
    {
        $lookup = array_fill_keys($departmentIds, true);
        $emails = [];

        foreach (DepartmentRepository::allWithMembers() as $department) {
            $deptId = (string) ($department['id'] ?? '');
            if ($deptId === '' || !isset($lookup[$deptId])) {
                continue;
            }
            foreach ($department['members'] ?? [] as $member) {
                if (($member['role'] ?? '') !== 'leader') {
                    continue;
                }
                $email = self::emailForUserId((int) ($member['user_id'] ?? 0));
                if ($email !== null) {
                    $emails[] = $email;
                }
            }
        }

        return $emails;
    }

    /**
     * @return list<string>
     */
    private static function emailsForExecutiveDepartment(): array
    {
        $emails = [];
        $fallbackIds = ['dept-geschaeftsfuehrung'];

        foreach (DepartmentRepository::allWithMembers() as $department) {
            $deptId = (string) ($department['id'] ?? '');
            $name = mb_strtolower((string) ($department['name'] ?? ''));
            $isExecutive = in_array($deptId, $fallbackIds, true)
                || str_contains($name, 'geschäftsführ')
                || str_contains($name, 'geschaeftsfuehr')
                || str_contains($name, 'leitung');
            if (!$isExecutive) {
                continue;
            }

            $leaders = [];
            $members = [];
            foreach ($department['members'] ?? [] as $member) {
                $email = self::emailForUserId((int) ($member['user_id'] ?? 0));
                if ($email === null) {
                    continue;
                }
                if (($member['role'] ?? '') === 'leader') {
                    $leaders[] = $email;
                } else {
                    $members[] = $email;
                }
            }
            $emails = array_merge($emails, $leaders, $members);
        }

        return $emails;
    }

    /**
     * @return list<string>
     */
    private static function adminFallbackEmails(): array
    {
        $adminRole = (string) App::config('roles.admin', 'administrator');
        $emails = [];

        foreach (UserRepository::all() as $user) {
            if (!$user instanceof User || !$user->hasRole($adminRole)) {
                continue;
            }
            $email = strtolower(trim($user->email));
            if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $emails[] = $email;
            }
        }

        if ($emails === []) {
            $company = strtolower(trim(CompanySettings::mailEmail()));
            if ($company !== '' && filter_var($company, FILTER_VALIDATE_EMAIL)) {
                $emails[] = $company;
            }
        }

        return $emails;
    }

    /**
     * @return list<string>
     */
    private static function departmentIdsForEmployeeContact(?int $employeeContactId): array
    {
        if ($employeeContactId === null || $employeeContactId < 1) {
            return [];
        }

        $contact = ContactRepository::findById($employeeContactId);
        if ($contact === null) {
            return [];
        }

        $user = null;
        $email = strtolower(trim($contact->email));
        if ($email !== '') {
            $user = UserRepository::findByEmail($email);
        }
        if ($user === null) {
            $login = trim((string) ($contact->login ?? ''));
            if ($login !== '') {
                $user = UserRepository::findByUsername($login);
            }
        }
        if ($user === null) {
            return [];
        }

        $ids = [];
        foreach (RoleResolver::departmentsFor($user) as $department) {
            $id = trim((string) ($department['id'] ?? ''));
            if ($id !== '') {
                $ids[] = $id;
            }
        }

        return $ids;
    }

    private static function emailForUserId(int $userId): ?string
    {
        if ($userId < 1) {
            return null;
        }
        $user = UserRepository::findById($userId);
        if ($user === null) {
            return null;
        }
        $email = strtolower(trim($user->email));
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return null;
        }

        return $email;
    }

    /**
     * @param list<string> $emails
     * @return list<string>
     */
    private static function uniqueValidEmails(array $emails): array
    {
        $out = [];
        $seen = [];
        foreach ($emails as $email) {
            $email = strtolower(trim($email));
            if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || isset($seen[$email])) {
                continue;
            }
            $seen[$email] = true;
            $out[] = $email;
        }

        return $out;
    }
}
