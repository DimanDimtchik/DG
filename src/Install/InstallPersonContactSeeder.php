<?php
declare(strict_types=1);

/**
 * Legt bei der CRM-Installation Kontakte für Inhaber und eingeladene Benutzer an.
 * CRM-Login bleibt optional (nur wenn Benutzer in Schritt 6 angelegt wurde).
 */
final class InstallPersonContactSeeder
{
    /**
     * @param list<array{name: string, role?: string}> $owners
     * @param list<array{id: int, email: string, display_name: string, role: string}> $users
     * @param array{
     *   name?: string,
     *   street?: string,
     *   postal?: string,
     *   city?: string,
     *   country?: string,
     *   email?: string,
     *   phone?: string
     * } $company
     * @return array{
     *   owners: list<array{name: string, share_percent: string, user_id: string}>,
     *   created: int,
     *   linked: int,
     *   errors: list<string>
     * }
     */
    public static function seed(array $owners, array $users, array $company): array
    {
        $companyName = trim((string) ($company['name'] ?? ''));
        $street = trim((string) ($company['street'] ?? ''));
        $postal = trim((string) ($company['postal'] ?? ''));
        $city = trim((string) ($company['city'] ?? ''));
        $country = trim((string) ($company['country'] ?? 'DE')) ?: 'DE';
        $companyPhone = trim((string) ($company['phone'] ?? ''));

        $usersByName = [];
        foreach ($users as $u) {
            $key = self::nameKey((string) ($u['display_name'] ?? ''));
            if ($key !== '') {
                $usersByName[$key] = $u;
            }
        }

        $created = 0;
        $linked = 0;
        $errors = [];
        /** @var array<string, int> $contactIdByName */
        $contactIdByName = [];

        foreach ($users as $u) {
            $displayName = trim((string) ($u['display_name'] ?? ''));
            $email = strtolower(trim((string) ($u['email'] ?? '')));
            if ($displayName === '' && $email === '') {
                continue;
            }
            if ($displayName === '') {
                $displayName = explode('@', $email)[0] ?: 'Benutzer';
            }

            $role = CrmRole::normalize((string) ($u['role'] ?? 'administrator'));
            if (!CrmRole::hasEmployeeProfile($role)) {
                $role = 'dg_eigenmitarbeiter';
            }

            try {
                $existing = $email !== '' ? ContactRepository::findByEmailMatch($email) : null;
                if ($existing !== null) {
                    $contactIdByName[self::nameKey($displayName)] = $existing->id;
                    $linked++;
                    continue;
                }

                $parts = self::splitPersonName($displayName);
                $login = InstallCsvHelper::uniqueLogin(
                    $email !== '' ? $email : $displayName,
                    static fn (string $candidate): bool => ContactRepository::loginExists($candidate)
                );

                $job = $role === 'administrator' ? 'Geschäftsführer / Inhaber' : 'Mitarbeiter';
                ContactRepository::save([
                    'login' => $login,
                    'first_name' => $parts['first_name'],
                    'last_name' => $parts['last_name'],
                    'display_name' => $displayName,
                    'company_name' => $companyName,
                    'email' => $email,
                    'phone_1' => $companyPhone,
                    'address1_street' => $street,
                    'address1_postal' => $postal,
                    'address1_city' => $city,
                    'address1_country' => $country,
                    'contact_role' => $role,
                    'contact_note' => 'Automatisch bei CRM-Installation angelegt (Benutzer).',
                    'employee' => [
                        'job_type' => $job,
                        'employment_relationship' => $job,
                        'work_location' => $city,
                        'employer_site' => $companyName,
                        'social_security_status' => 'pending',
                    ],
                ]);
                $contactIdByName[self::nameKey($displayName)] = 0;
                $created++;
            } catch (Throwable $e) {
                $errors[] = 'Benutzer „' . $displayName . '“: ' . $e->getMessage();
            }
        }

        $ownerRows = [];
        foreach ($owners as $o) {
            $name = trim((string) ($o['name'] ?? ''));
            if ($name === '') {
                continue;
            }
            $function = trim((string) ($o['role'] ?? ''));
            $nameKey = self::nameKey($name);
            $matchedUser = $usersByName[$nameKey] ?? null;
            $userId = $matchedUser !== null ? (string) (int) $matchedUser['id'] : '0';

            $ownerRows[] = [
                'name' => $name,
                'share_percent' => '',
                'user_id' => $userId,
            ];

            if ($userId !== '0') {
                $linked++;
                // Kontakt wurde ggf. schon über den Benutzer angelegt — Tätigkeit ergänzen.
                if ($function !== '' && isset($contactIdByName[$nameKey])) {
                    self::trySetJobTypeByEmail((string) ($matchedUser['email'] ?? ''), $function);
                }
                continue;
            }

            if (isset($contactIdByName[$nameKey])) {
                continue;
            }

            try {
                $parts = self::splitPersonName($name);
                $login = InstallCsvHelper::uniqueLogin(
                    $name,
                    static fn (string $candidate): bool => ContactRepository::loginExists($candidate)
                );
                $job = $function !== '' ? $function : 'Inhaber / Geschäftsführer';
                ContactRepository::save([
                    'login' => $login,
                    'first_name' => $parts['first_name'],
                    'last_name' => $parts['last_name'],
                    'display_name' => $name,
                    'company_name' => $companyName,
                    'email' => '',
                    'phone_1' => $companyPhone,
                    'address1_street' => $street,
                    'address1_postal' => $postal,
                    'address1_city' => $city,
                    'address1_country' => $country,
                    'contact_role' => 'administrator',
                    'contact_note' => 'Automatisch bei CRM-Installation angelegt (Inhaber/Geschäftsführer).'
                        . ($function !== '' ? ' Funktion: ' . $function . '.' : ''),
                    'employee' => [
                        'job_type' => $job,
                        'employment_relationship' => $job,
                        'work_location' => $city,
                        'employer_site' => $companyName,
                        'social_security_status' => 'pending',
                    ],
                ]);
                $contactIdByName[$nameKey] = 0;
                $created++;
            } catch (Throwable $e) {
                $errors[] = 'Inhaber „' . $name . '“: ' . $e->getMessage();
            }
        }

        if ($ownerRows === []) {
            $ownerRows = [['name' => '', 'share_percent' => '', 'user_id' => '0']];
        }

        return [
            'owners' => $ownerRows,
            'created' => $created,
            'linked' => $linked,
            'errors' => $errors,
        ];
    }

    private static function trySetJobTypeByEmail(string $email, string $jobType): void
    {
        $email = strtolower(trim($email));
        if ($email === '' || $jobType === '') {
            return;
        }
        $contact = ContactRepository::findByEmailMatch($email);
        if ($contact === null || !CrmRole::hasEmployeeProfile($contact->contactRole)) {
            return;
        }
        $data = $contact->employeeData;
        if (!is_array($data)) {
            $data = EmployeeData::empty();
        }
        if (trim((string) ($data['job_type'] ?? '')) === '') {
            $data['job_type'] = $jobType;
        }
        if (trim((string) ($data['employment_relationship'] ?? '')) === '') {
            $data['employment_relationship'] = $jobType;
        }
        ContactRepository::updateEmployeeData($contact->id, $data);
    }

    /** @return array{first_name: string, last_name: string} */
    public static function splitPersonName(string $full): array
    {
        $full = trim(preg_replace('/\s+/u', ' ', $full) ?? $full);
        if ($full === '') {
            return ['first_name' => '', 'last_name' => ''];
        }
        $parts = explode(' ', $full);
        if (count($parts) === 1) {
            return ['first_name' => '', 'last_name' => $parts[0]];
        }
        $last = array_pop($parts);

        return ['first_name' => implode(' ', $parts), 'last_name' => (string) $last];
    }

    private static function nameKey(string $name): string
    {
        $name = mb_strtolower(trim(preg_replace('/\s+/u', ' ', $name) ?? $name), 'UTF-8');

        return $name;
    }
}
