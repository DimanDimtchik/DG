<?php
declare(strict_types=1);

/** Verknüpfungen zwischen Firmen-Kontakten und Personen-Kontakten. */
final class ContactCompanyLinkRepository
{
    /**
     * ensureTables
     * @return void
     */
    public static function ensureTables(): void
    {
        if (!Database::isConfigured()) {
            return;
        }

        MigrationRunner::runPending();
    }

    /**
     * isWorkEmailInUse
     * @param string $email
     * @param int|null $excludePersonContactId
     * @return bool
     */
    public static function isWorkEmailInUse(string $email, ?int $excludePersonContactId = null): bool
    {
        $email = strtolower(trim($email));
        if ($email === '' || !Database::isConfigured()) {
            return false;
        }

        self::ensureTables();
        $sql = 'SELECT id FROM dg_contact_company_links WHERE LOWER(TRIM(work_email)) = :email';
        $params = ['email' => $email];
        if ($excludePersonContactId !== null && $excludePersonContactId > 0) {
            $sql .= ' AND person_contact_id <> :exclude_id';
            $params['exclude_id'] = $excludePersonContactId;
        }
        $sql .= ' LIMIT 1';

        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute($params);

        return (bool) $stmt->fetchColumn();
    }

        /**
     * employeesForCompany
     * @param int $companyContactId
     * @return list<array<string, mixed>>
     */
    public static function employeesForCompany(int $companyContactId): array
    {
        self::ensureTables();
        if ($companyContactId <= 0) {
            return [];
        }

        $stmt = Database::pdo()->prepare(
            'SELECT l.*, p.display_name, p.first_name, p.last_name, p.salutation, p.login, p.email AS person_email, p.phone_1 AS person_phone
             FROM dg_contact_company_links l
             INNER JOIN dg_contacts p ON p.id = l.person_contact_id
             WHERE l.company_contact_id = :company_id
             ORDER BY l.sort_order ASC, p.display_name ASC, p.last_name ASC, p.first_name ASC'
        );
        $stmt->execute(['company_id' => $companyContactId]);

        $rows = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $rows[] = self::mapEmployeeRow($row);
        }

        return $rows;
    }

        /**
     * employerForPerson
     * @param int $personContactId
     * @return array<string, mixed>|null
     */
    public static function employerForPerson(int $personContactId): ?array
    {
        self::ensureTables();
        if ($personContactId <= 0) {
            return null;
        }

        $stmt = Database::pdo()->prepare(
            'SELECT l.*, c.display_name, c.company_name, c.salutation, c.login, c.website AS company_website
             FROM dg_contact_company_links l
             INNER JOIN dg_contacts c ON c.id = l.company_contact_id
             WHERE l.person_contact_id = :person_id
             ORDER BY l.id ASC
             LIMIT 1'
        );
        $stmt->execute(['person_id' => $personContactId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? self::mapEmployerRow($row) : null;
    }

        /**
     * companyOptions
     * @param int $excludeContactId
     * @return list<array{id: int, label: string}>
     */
    public static function companyOptions(int $excludeContactId = 0): array
    {
        if (!Database::isConfigured()) {
            return [];
        }

        $sql = "SELECT id, display_name, company_name, salutation, login
                FROM dg_contacts
                WHERE salutation = 'Firma'";
        $params = [];
        if ($excludeContactId > 0) {
            $sql .= ' AND id <> :exclude_id';
            $params['exclude_id'] = $excludeContactId;
        }
        $sql .= ' ORDER BY display_name ASC, company_name ASC';

        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute($params);

        $options = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $options[] = [
                'id' => (int) $row['id'],
                'label' => self::companyLabel($row),
            ];
        }

        return $options;
    }

        /**
     * personOptions
     * @param int $excludeContactId
     * @return list<array{id: int, label: string}>
     */
    public static function personOptions(int $excludeContactId = 0): array
    {
        if (!Database::isConfigured()) {
            return [];
        }

        $sql = "SELECT id, display_name, first_name, last_name, salutation, login
                FROM dg_contacts
                WHERE salutation <> 'Firma'";
        $params = [];
        if ($excludeContactId > 0) {
            $sql .= ' AND id <> :exclude_id';
            $params['exclude_id'] = $excludeContactId;
        }
        $sql .= ' ORDER BY display_name ASC, last_name ASC, first_name ASC';

        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute($params);

        $options = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $options[] = [
                'id' => (int) $row['id'],
                'label' => self::personLabel($row),
            ];
        }

        return $options;
    }

    /**
     * emptyEmployeeRow.
     *
     * @return array<string, mixed>
     */
        public static function emptyEmployeeRow(): array
    {
        return [
            'person_contact_id' => 0,
            'responsibility' => '',
            'work_email' => '',
            'work_phone' => '',
            'availability' => '',
        ];
    }

        /**
     * employeeRowsForCompany
     * @param int $companyContactId
     * @return list<array<string, mixed>>
     */
    public static function employeeRowsForCompany(int $companyContactId): array
    {
        $rows = [];
        foreach (self::employeesForCompany($companyContactId) as $employee) {
            $rows[] = [
                'person_contact_id' => (int) $employee['person_contact_id'],
                'responsibility' => (string) $employee['responsibility'],
                'work_email' => (string) $employee['work_email'],
                'work_phone' => (string) $employee['work_phone'],
                'availability' => (string) $employee['availability'],
            ];
        }

        return $rows;
    }

        /**
     * employerFormForPerson
     * @param int $personContactId
     * @return array<string, mixed>
     */
    public static function employerFormForPerson(int $personContactId): array
    {
        $form = self::emptyEmployerForm();
        $employer = self::employerForPerson($personContactId);
        if ($employer === null) {
            return $form;
        }

        $form['employer_company_id'] = (int) $employer['company_contact_id'];
        $form['employer_responsibility'] = (string) $employer['responsibility'];
        $form['employer_work_email'] = (string) $employer['work_email'];
        $form['employer_work_phone'] = (string) $employer['work_phone'];
        $form['employer_availability'] = (string) $employer['availability'];

        return $form;
    }

    /**
     * emptyEmployerForm.
     *
     * @return array<string, mixed>
     */
        public static function emptyEmployerForm(): array
    {
        return [
            'employer_company_id' => 0,
            'employer_responsibility' => '',
            'employer_work_email' => '',
            'employer_work_phone' => '',
            'employer_availability' => '',
        ];
    }

        /**
     * companyEmployees: list<array<string, mixed>>,
     * @param Contact|null $contact
     * @param array $post
     * @return array{
     */
    public static function formContext(?Contact $contact = null, array $post = []): array
    {
        $contactId = $contact?->id ?? max(0, (int) ($post['id'] ?? 0));
        $salutation = trim((string) ($contact?->salutation ?? $post['salutation'] ?? ''));

        if ($post !== [] && array_key_exists('company_employees', $post)) {
            $companyEmployees = self::employeesFromPost($post);
        } elseif ($contact !== null && $contact->isCompany()) {
            $companyEmployees = self::employeeRowsForCompany($contact->id);
        } else {
            $companyEmployees = [];
        }

        if ($post !== [] && array_key_exists('employer_company_id', $post)) {
            $employerForm = self::employerFromPost($post);
        } elseif ($contact !== null && !$contact->isCompany()) {
            $employerForm = self::employerFormForPerson($contact->id);
        } else {
            $employerForm = self::emptyEmployerForm();
        }

        if ($salutation === 'Firma' && $companyEmployees === []) {
            $companyEmployees = [self::emptyEmployeeRow()];
        }

        return [
            'companyEmployees' => $companyEmployees,
            'employerForm' => $employerForm,
            'companyContactOptions' => self::companyOptions($contactId),
            'personContactOptions' => self::personOptions($contactId),
        ];
    }

        /**
     * employeesFromPost
     * @param array $post
     * @return array
     */
    public static function employeesFromPost(array $post): array
    {
        $raw = $post['company_employees'] ?? [];
        if (!is_array($raw)) {
            return [];
        }

        $rows = [];
        foreach ($raw as $row) {
            if (!is_array($row)) {
                continue;
            }
            $personId = (int) ($row['person_contact_id'] ?? 0);
            if ($personId <= 0) {
                continue;
            }
            $rows[] = [
                'person_contact_id' => $personId,
                'responsibility' => trim((string) ($row['responsibility'] ?? '')),
                'work_email' => trim((string) ($row['work_email'] ?? '')),
                'work_phone' => trim((string) ($row['work_phone'] ?? '')),
                'availability' => trim((string) ($row['availability'] ?? '')),
            ];
        }

        return $rows;
    }

        /**
     * employerFromPost
     * @param array $post
     * @return array
     */
    public static function employerFromPost(array $post): array
    {
        return [
            'employer_company_id' => max(0, (int) ($post['employer_company_id'] ?? 0)),
            'employer_responsibility' => trim((string) ($post['employer_responsibility'] ?? '')),
            'employer_work_email' => trim((string) ($post['employer_work_email'] ?? '')),
            'employer_work_phone' => trim((string) ($post['employer_work_phone'] ?? '')),
            'employer_availability' => trim((string) ($post['employer_availability'] ?? '')),
        ];
    }

        /**
     * syncEmployeesForCompany
     * @param int $companyContactId
     * @param array $post
     * @return void
     * @throws InvalidArgumentException
     */
    public static function syncEmployeesForCompany(int $companyContactId, array $post): void
    {
        self::ensureTables();
        self::assertCompanyContact($companyContactId);

        $rows = self::employeesFromPost($post);
        $seenPersonIds = [];
        foreach ($rows as $row) {
            $personId = (int) $row['person_contact_id'];
            if (isset($seenPersonIds[$personId])) {
                throw new InvalidArgumentException('Dieselbe Person darf nur einmal als Mitarbeiter der Firma eingetragen werden.');
            }
            $seenPersonIds[$personId] = true;
            self::assertPersonContact($personId);
            if ($personId === $companyContactId) {
                throw new InvalidArgumentException('Eine Firma kann nicht ihre eigene Mitarbeiterin sein.');
            }
        }

        $pdo = Database::pdo();
        $existing = self::employeesForCompany($companyContactId);
        $keepIds = [];

        foreach ($rows as $index => $row) {
            $personId = (int) $row['person_contact_id'];
            $existingRow = self::findExistingLink($existing, $personId);
            if ($existingRow !== null) {
                $stmt = $pdo->prepare(
                    'UPDATE dg_contact_company_links
                     SET responsibility = :responsibility, work_email = :work_email, work_phone = :work_phone,
                         availability = :availability, sort_order = :sort_order
                     WHERE id = :id'
                );
                $stmt->execute([
                    'id' => (int) $existingRow['id'],
                    'responsibility' => $row['responsibility'],
                    'work_email' => $row['work_email'],
                    'work_phone' => $row['work_phone'],
                    'availability' => $row['availability'],
                    'sort_order' => $index,
                ]);
                $keepIds[] = (int) $existingRow['id'];
                continue;
            }

            self::removePersonEmployerElsewhere($personId, $companyContactId);

            $stmt = $pdo->prepare(
                'INSERT INTO dg_contact_company_links
                 (company_contact_id, person_contact_id, responsibility, work_email, work_phone, availability, sort_order)
                 VALUES (:company_id, :person_id, :responsibility, :work_email, :work_phone, :availability, :sort_order)'
            );
            $stmt->execute([
                'company_id' => $companyContactId,
                'person_id' => $personId,
                'responsibility' => $row['responsibility'],
                'work_email' => $row['work_email'],
                'work_phone' => $row['work_phone'],
                'availability' => $row['availability'],
                'sort_order' => $index,
            ]);
            $keepIds[] = (int) $pdo->lastInsertId();
        }

        if ($existing === [] && $keepIds === []) {
            return;
        }

        $placeholders = implode(',', array_fill(0, count($keepIds), '?'));
        $sql = 'DELETE FROM dg_contact_company_links WHERE company_contact_id = ?';
        $params = [$companyContactId];
        if ($keepIds !== []) {
            $sql .= ' AND id NOT IN (' . $placeholders . ')';
            $params = array_merge($params, $keepIds);
        }
        $pdo->prepare($sql)->execute($params);
    }

        /**
     * syncEmployerForPerson
     * @param int $personContactId
     * @param array $post
     * @return void
     * @throws InvalidArgumentException
     */
    public static function syncEmployerForPerson(int $personContactId, array $post): void
    {
        self::ensureTables();
        self::assertPersonContact($personContactId);

        $form = self::employerFromPost($post);
        $companyId = (int) $form['employer_company_id'];

        $pdo = Database::pdo();
        if ($companyId <= 0) {
            $pdo->prepare('DELETE FROM dg_contact_company_links WHERE person_contact_id = :person_id')
                ->execute(['person_id' => $personContactId]);

            return;
        }

        if ($companyId === $personContactId) {
            throw new InvalidArgumentException('Eine Person kann nicht bei sich selbst arbeiten.');
        }

        self::assertCompanyContact($companyId);

        $stmt = $pdo->prepare(
            'SELECT id FROM dg_contact_company_links
             WHERE person_contact_id = :person_id AND company_contact_id = :company_id
             LIMIT 1'
        );
        $stmt->execute(['person_id' => $personContactId, 'company_id' => $companyId]);
        $existingId = $stmt->fetchColumn();

        if ($existingId !== false) {
            $pdo->prepare(
                'UPDATE dg_contact_company_links
                 SET responsibility = :responsibility, work_email = :work_email, work_phone = :work_phone,
                     availability = :availability
                 WHERE id = :id'
            )->execute([
                'id' => (int) $existingId,
                'responsibility' => $form['employer_responsibility'],
                'work_email' => $form['employer_work_email'],
                'work_phone' => $form['employer_work_phone'],
                'availability' => $form['employer_availability'],
            ]);
        } else {
            $pdo->prepare('DELETE FROM dg_contact_company_links WHERE person_contact_id = :person_id')
                ->execute(['person_id' => $personContactId]);

            $pdo->prepare(
                'INSERT INTO dg_contact_company_links
                 (company_contact_id, person_contact_id, responsibility, work_email, work_phone, availability, sort_order)
                 VALUES (:company_id, :person_id, :responsibility, :work_email, :work_phone, :availability, 0)'
            )->execute([
                'company_id' => $companyId,
                'person_id' => $personContactId,
                'responsibility' => $form['employer_responsibility'],
                'work_email' => $form['employer_work_email'],
                'work_phone' => $form['employer_work_phone'],
                'availability' => $form['employer_availability'],
            ]);
        }
    }

    /**
     * deleteForContact
     * @param int $contactId Kontakt-ID
     * @return void
     */
    public static function deleteForContact(int $contactId): void
    {
        if (!Database::isConfigured() || $contactId <= 0) {
            return;
        }

        self::ensureTables();
        Database::pdo()->prepare(
            'DELETE FROM dg_contact_company_links WHERE company_contact_id = :company_id OR person_contact_id = :person_id'
        )->execute(['company_id' => $contactId, 'person_id' => $contactId]);
    }

        /**
     * mapEmployeeRow
     * @param array $row Datenbankzeile
     * @return array
     */
    private static function mapEmployeeRow(array $row): array
    {
        return [
            'id' => (int) $row['id'],
            'company_contact_id' => (int) $row['company_contact_id'],
            'person_contact_id' => (int) $row['person_contact_id'],
            'responsibility' => (string) $row['responsibility'],
            'work_email' => (string) $row['work_email'],
            'work_phone' => (string) $row['work_phone'],
            'availability' => (string) $row['availability'],
            'person_label' => self::personLabel($row),
            'person_email' => (string) ($row['person_email'] ?? ''),
            'person_phone' => (string) ($row['person_phone'] ?? ''),
        ];
    }

        /**
     * mapEmployerRow
     * @param array $row Datenbankzeile
     * @return array
     */
    private static function mapEmployerRow(array $row): array
    {
        return [
            'id' => (int) $row['id'],
            'company_contact_id' => (int) $row['company_contact_id'],
            'person_contact_id' => (int) $row['person_contact_id'],
            'responsibility' => (string) $row['responsibility'],
            'work_email' => (string) $row['work_email'],
            'work_phone' => (string) $row['work_phone'],
            'availability' => (string) $row['availability'],
            'company_label' => self::companyLabel($row),
            'company_website' => (string) ($row['company_website'] ?? ''),
        ];
    }

        /**
     * personLabel
     * @param array $row Datenbankzeile
     * @return string
     */
    private static function personLabel(array $row): string
    {
        $display = trim((string) ($row['display_name'] ?? ''));
        if ($display !== '') {
            return $display;
        }

        $name = trim(((string) ($row['first_name'] ?? '')) . ' ' . ((string) ($row['last_name'] ?? '')));

        return $name !== '' ? $name : (string) ($row['login'] ?? 'Kontakt');
    }

        /**
     * companyLabel
     * @param array $row Datenbankzeile
     * @return string
     */
    private static function companyLabel(array $row): string
    {
        $company = trim((string) ($row['company_name'] ?? ''));
        if ($company !== '') {
            return $company;
        }

        $display = trim((string) ($row['display_name'] ?? ''));

        return $display !== '' ? $display : (string) ($row['login'] ?? 'Firma');
    }

    /**
     * assertCompanyContact
     * @param int $contactId Kontakt-ID
     * @return void
     * @throws InvalidArgumentException
     */
    private static function assertCompanyContact(int $contactId): void
    {
        $contact = ContactRepository::findById($contactId);
        if ($contact === null || !$contact->isCompany()) {
            throw new InvalidArgumentException('Ungültige Firma für die Verknüpfung.');
        }
    }

    /**
     * assertPersonContact
     * @param int $contactId Kontakt-ID
     * @return void
     * @throws InvalidArgumentException
     */
    private static function assertPersonContact(int $contactId): void
    {
        $contact = ContactRepository::findById($contactId);
        if ($contact === null || $contact->isCompany()) {
            throw new InvalidArgumentException('Ungültige Person für die Verknüpfung.');
        }
    }

        /**
     * findExistingLink
     * @param array $existing Bestehende Hinweisdaten
     * @param int $personId
     * @return ?array
     */
    private static function findExistingLink(array $existing, int $personId): ?array
    {
        foreach ($existing as $row) {
            if ((int) ($row['person_contact_id'] ?? 0) === $personId) {
                return $row;
            }
        }

        return null;
    }

    /**
     * removePersonEmployerElsewhere
     * @param int $personId
     * @param int $companyContactId
     * @return void
     */
    private static function removePersonEmployerElsewhere(int $personId, int $companyContactId): void
    {
        Database::pdo()->prepare(
            'DELETE FROM dg_contact_company_links
             WHERE person_contact_id = :person_id AND company_contact_id <> :company_id'
        )->execute(['person_id' => $personId, 'company_id' => $companyContactId]);
    }
}
