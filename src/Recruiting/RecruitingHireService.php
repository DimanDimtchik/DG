<?php
declare(strict_types=1);

/**
 * Zusage → Mitarbeiterstamm (dg_contacts, Rolle Eigenmitarbeiter).
 * Kein CRM-Benutzer-Login; Stammdaten wie bei Kontaktanlage.
 */
final class RecruitingHireService
{
    public const EMPLOYEE_ROLE = 'dg_eigenmitarbeiter';

    public static function contactsReady(): bool
    {
        if (!Database::isConfigured()) {
            return false;
        }
        try {
            Database::pdo()->query('SELECT 1 FROM dg_contacts LIMIT 1');

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * Nach Status „Eingestellt“: Kontakt anlegen oder verknüpfen und Bewerber.contact_id setzen.
     *
     * @return array{
     *   contact_id: int,
     *   created: bool,
     *   linked_existing: bool,
     *   promoted: bool,
     *   skipped: bool,
     *   message: string
     * }
     */
    public static function ensureEmployeeFromApplication(int $applicationId, ?int $userId = null): array
    {
        $empty = [
            'contact_id' => 0,
            'created' => false,
            'linked_existing' => false,
            'promoted' => false,
            'skipped' => true,
            'message' => '',
        ];

        if ($applicationId < 1 || !self::contactsReady()) {
            $empty['message'] = 'Kontaktstamm nicht verfügbar.';

            return $empty;
        }

        $app = JobApplicationRepository::find($applicationId);
        if ($app === null) {
            throw new InvalidArgumentException('Bewerbung nicht gefunden.');
        }
        if ((string) ($app['status'] ?? '') !== JobApplicationRepository::STATUS_HIRED) {
            $empty['message'] = 'Nur bei Status „Eingestellt“.';

            return $empty;
        }

        $applicantId = (int) ($app['applicant_id'] ?? 0);
        if ($applicantId < 1) {
            throw new RuntimeException('Bewerber fehlt.');
        }

        $email = trim((string) ($app['applicant_email'] ?? ''));
        $first = trim((string) ($app['applicant_first_name'] ?? ''));
        $last = trim((string) ($app['applicant_last_name'] ?? ''));
        $phone = trim((string) ($app['applicant_phone'] ?? ''));
        $city = trim((string) ($app['applicant_city'] ?? ''));
        $jobTitle = trim((string) ($app['job_title'] ?? ''));
        $entryDate = self::entryDateFromApplication($app);
        $companyName = self::companyName();

        $linkedId = (int) ($app['applicant_contact_id'] ?? 0);
        $contact = $linkedId > 0 ? ContactRepository::findById($linkedId) : null;
        if ($contact === null && $email !== '') {
            $contact = ContactRepository::findByEmailMatch($email);
        }

        // Bereits verknüpft und Mitarbeiterprofil vorhanden → nichts tun (idempotent).
        if (
            $contact !== null
            && $linkedId === $contact->id
            && CrmRole::hasEmployeeProfile($contact->contactRole)
        ) {
            return [
                'contact_id' => $contact->id,
                'created' => false,
                'linked_existing' => true,
                'promoted' => false,
                'skipped' => false,
                'message' => 'Mitarbeiter-Kontakt #' . $contact->id . ' bereits verknüpft.',
            ];
        }

        $created = false;
        $linkedExisting = false;
        $promoted = false;

        if ($contact !== null) {
            $linkedExisting = true;
            $promoted = self::promoteToEmployee($contact, $jobTitle, $entryDate, $city, $companyName);
            $contactId = $contact->id;
        } else {
            $contactId = self::createEmployeeContact(
                $email,
                $first,
                $last,
                $phone,
                $city,
                $jobTitle,
                $entryDate,
                $companyName,
                $applicationId
            );
            $created = true;
        }

        ApplicantRepository::linkContact($applicantId, $contactId);

        $body = $created
            ? 'Mitarbeiter-Kontakt #' . $contactId . ' angelegt.'
            : ($promoted
                ? 'Bestehender Kontakt #' . $contactId . ' als Mitarbeiter übernommen.'
                : 'Mit Mitarbeiter-Kontakt #' . $contactId . ' verknüpft.');
        JobApplicationRepository::addEvent($applicationId, 'hire', null, JobApplicationRepository::STATUS_HIRED, $body, $userId);

        return [
            'contact_id' => $contactId,
            'created' => $created,
            'linked_existing' => $linkedExisting,
            'promoted' => $promoted,
            'skipped' => false,
            'message' => $body,
        ];
    }

    /**
     * @param array<string, mixed> $app
     */
    private static function entryDateFromApplication(array $app): string
    {
        $hiredAt = trim((string) ($app['hired_at'] ?? ''));
        if ($hiredAt !== '') {
            $ts = strtotime($hiredAt);
            if ($ts !== false) {
                return date('Y-m-d', $ts);
            }
        }

        return date('Y-m-d');
    }

    private static function companyName(): string
    {
        try {
            if (!class_exists('CompanySettings')) {
                return '';
            }

            return trim((string) (CompanySettings::config()['name'] ?? ''));
        } catch (Throwable) {
            return '';
        }
    }

    private static function createEmployeeContact(
        string $email,
        string $first,
        string $last,
        string $phone,
        string $city,
        string $jobTitle,
        string $entryDate,
        string $companyName,
        int $applicationId
    ): int {
        $display = trim($first . ' ' . $last);
        if ($display === '') {
            $display = $email !== '' ? $email : 'Mitarbeiter';
        }
        $loginBase = $email !== '' ? $email : ($display !== '' ? $display : 'mitarbeiter');
        $login = InstallCsvHelper::uniqueLogin(
            $loginBase,
            static fn (string $candidate): bool => ContactRepository::loginExists($candidate)
        );

        $job = $jobTitle !== '' ? $jobTitle : 'Mitarbeiter';
        $note = 'Übernommen aus Bewerbung #' . $applicationId . '.';

        return ContactRepository::save([
            'login' => $login,
            'first_name' => $first,
            'last_name' => $last,
            'display_name' => $display,
            'company_name' => $companyName,
            'email' => $email,
            'phone_1' => $phone,
            'address1_city' => $city,
            'address1_country' => 'DE',
            'contact_role' => self::EMPLOYEE_ROLE,
            'contact_note' => $note,
            'employee' => [
                'job_type' => $job,
                'employment_relationship' => $job,
                'work_location' => $city,
                'employer_site' => $companyName,
                'entry_date' => $entryDate,
                'contract_start' => $entryDate,
                'social_security_status' => 'pending',
            ],
        ]);
    }

    /**
     * Bestehenden Kontakt zur Mitarbeiterrolle heben (ohne Firmenverknüpfungen zu löschen).
     */
    private static function promoteToEmployee(
        Contact $contact,
        string $jobTitle,
        string $entryDate,
        string $city,
        string $companyName
    ): bool {
        $wasEmployee = CrmRole::hasEmployeeProfile($contact->contactRole);
        $promoted = false;

        if (!$wasEmployee) {
            Database::pdo()->prepare(
                'UPDATE dg_contacts SET contact_role = :role WHERE id = :id'
            )->execute([
                'role' => self::EMPLOYEE_ROLE,
                'id' => $contact->id,
            ]);
            $promoted = true;
        }

        $employee = is_array($contact->employeeData) ? $contact->employeeData : EmployeeData::empty();
        $job = $jobTitle !== '' ? $jobTitle : 'Mitarbeiter';
        $changed = !$wasEmployee;

        $fill = static function (string $key, string $value) use (&$employee, &$changed): void {
            if ($value === '') {
                return;
            }
            if (trim((string) ($employee[$key] ?? '')) === '') {
                $employee[$key] = $value;
                $changed = true;
            }
        };

        $fill('entry_date', $entryDate);
        $fill('contract_start', $entryDate);
        $fill('job_type', $job);
        $fill('employment_relationship', $job);
        $fill('work_location', $city);
        $fill('employer_site', $companyName);
        if (trim((string) ($employee['social_security_status'] ?? '')) === '') {
            $employee['social_security_status'] = 'pending';
            $changed = true;
        }

        if ($changed) {
            ContactRepository::updateEmployeeData($contact->id, $employee);
        }

        return $promoted;
    }
}
