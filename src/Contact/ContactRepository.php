<?php
declare(strict_types=1);

final class ContactRepository
{
    private const PER_PAGE = 20;

    private static bool $retentionPurgedOnLastFind = false;

    public static function consumeRetentionPurged(): bool
    {
        $purged = self::$retentionPurgedOnLastFind;
        self::$retentionPurgedOnLastFind = false;

        return $purged;
    }

    /**
     * @return array{
     *   items: list<Contact>,
     *   total: int,
     *   page: int,
     *   per_page: int,
     *   total_pages: int
     * }
     */
    public static function paginate(string $search = '', int $page = 1, ?User $viewer = null): array
    {
        $page = max(1, $page);
        $perPage = self::PER_PAGE;
        $offset = ($page - 1) * $perPage;
        [$where, $params] = self::buildSearchWhere($search, $viewer);
        $pdo = Database::pdo();

        $countSql = 'SELECT COUNT(*) FROM dg_contacts ' . $where;
        $stmt = $pdo->prepare($countSql);
        $stmt->execute($params);
        $total = (int) $stmt->fetchColumn();

        $sql = 'SELECT * FROM dg_contacts ' . $where . ' ORDER BY display_name ASC, company_name ASC LIMIT :limit OFFSET :offset';
        $stmt = $pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue(':' . $key, $value);
        }
        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        $items = [];
        while ($row = $stmt->fetch()) {
            $contactId = (int) $row['id'];
            if (EmployeeRetentionService::applyIfExpired($contactId)) {
                $refetch = $pdo->prepare('SELECT * FROM dg_contacts WHERE id = :id LIMIT 1');
                $refetch->execute(['id' => $contactId]);
                $row = $refetch->fetch() ?: $row;
            }
            $items[] = self::map($row);
        }

        $totalPages = max(1, (int) ceil($total / $perPage));

        return [
            'items' => $items,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'total_pages' => $totalPages,
        ];
    }

    /** @return list<Contact> */
    public static function searchPicker(string $search, int $limit = 15, ?User $viewer = null): array
    {
        $limit = max(1, min(50, $limit));
        [$where, $params] = self::buildSearchWhere($search, $viewer);
        if ($search === '') {
            return [];
        }

        $stmt = Database::pdo()->prepare(
            'SELECT * FROM dg_contacts ' . $where . ' ORDER BY display_name ASC, company_name ASC LIMIT ' . $limit
        );
        $stmt->execute($params);

        $items = [];
        while ($row = $stmt->fetch()) {
            $contactId = (int) $row['id'];
            if (EmployeeRetentionService::applyIfExpired($contactId)) {
                $refetch = Database::pdo()->prepare('SELECT * FROM dg_contacts WHERE id = :id LIMIT 1');
                $refetch->execute(['id' => $contactId]);
                $row = $refetch->fetch() ?: $row;
            }
            $items[] = self::map($row);
        }

        return $items;
    }

    public static function findById(int $id): ?Contact
    {
        self::$retentionPurgedOnLastFind = EmployeeRetentionService::applyIfExpired($id);

        $stmt = Database::pdo()->prepare('SELECT * FROM dg_contacts WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row ? self::map($row) : null;
    }

    public static function loginExists(string $login, ?int $excludeId = null): bool
    {
        $sql = 'SELECT id FROM dg_contacts WHERE login = :login';
        $params = ['login' => $login];
        if ($excludeId) {
            $sql .= ' AND id != :id';
            $params['id'] = $excludeId;
        }
        $stmt = Database::pdo()->prepare($sql . ' LIMIT 1');
        $stmt->execute($params);

        return (bool) $stmt->fetchColumn();
    }

    /**
     * @return array{0: string, 1: array<string, string>}
     */
    private static function buildSearchWhere(string $search, ?User $viewer = null): array
    {
        $whereParts = [];
        $params = [];

        if ($viewer !== null && !ContactAccessResolver::canViewAllContactTypes($viewer)) {
            $whereParts[] = "contact_role IN ('dg_kunde', 'kunde', 'lieferant')";
        }

        if ($search !== '') {
            $searchFields = [
                'login',
                'display_name',
                'company_name',
                'email',
                'customer_number',
                'first_name',
                'last_name',
            ];
            $searchLikes = [];
            foreach ($searchFields as $index => $field) {
                $param = 'q' . $index;
                $searchLikes[] = $field . ' LIKE :' . $param;
                $params[$param] = '%' . $search . '%';
            }
            $whereParts[] = '(
                ' . implode(' OR ', $searchLikes) . '
            )';
        }

        return [
            $whereParts !== [] ? 'WHERE ' . implode(' AND ', $whereParts) : '',
            $params,
        ];
    }

    /** @param array<string, mixed> $data */
    /** @param array<string, mixed> $uploads */
    public static function save(array $data, ?int $id = null, array $uploads = []): int
    {
        $login = trim((string) ($data['login'] ?? ''));
        if ($login === '') {
            throw new InvalidArgumentException('Benutzername ist erforderlich.');
        }
        if (self::loginExists($login, $id)) {
            throw new InvalidArgumentException('Benutzername ist bereits vergeben.');
        }

        if ($id && EmployeeRetentionService::applyIfExpired($id)) {
            $data['contact_role'] = 'dg_kunde';
            unset($data['employee']);
            $uploads = [];
            Flash::set(
                'success',
                'Mitarbeiterdaten wurden nach Ablauf der 10-jährigen Aufbewahrungsfrist entfernt. Rolle ist jetzt Kunde.'
            );
        }

        $salutation = trim((string) ($data['salutation'] ?? ''));
        $firstName = trim((string) ($data['first_name'] ?? ''));
        $lastName = trim((string) ($data['last_name'] ?? ''));
        $companyName = trim((string) ($data['company_name'] ?? ''));
        $displayName = trim((string) ($data['display_name'] ?? ''));
        if ($displayName === '') {
            $displayName = $salutation === 'Firma' && $companyName !== ''
                ? $companyName
                : trim($firstName . ' ' . $lastName);
        }

        $fields = [
            'login' => $login,
            'salutation' => $salutation,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'display_name' => $displayName,
            'company_name' => $companyName,
            'email' => trim((string) ($data['email'] ?? '')),
            'email_2' => trim((string) ($data['email_2'] ?? '')),
            'phone_1' => trim((string) ($data['phone_1'] ?? '')),
            'phone_2' => trim((string) ($data['phone_2'] ?? '')),
            'customer_number' => trim((string) ($data['customer_number'] ?? '')),
            'supplier_number' => trim((string) ($data['supplier_number'] ?? '')),
            'tax_number' => trim((string) ($data['tax_number'] ?? '')),
            'vat_id' => trim((string) ($data['vat_id'] ?? '')),
            'contact_note' => trim((string) ($data['contact_note'] ?? '')),
            'address1_extra' => trim((string) ($data['address1_extra'] ?? '')),
            'address1_street' => trim((string) ($data['address1_street'] ?? '')),
            'address1_postal' => trim((string) ($data['address1_postal'] ?? '')),
            'address1_city' => trim((string) ($data['address1_city'] ?? '')),
            'address1_country' => trim((string) ($data['address1_country'] ?? 'DE')) ?: 'DE',
            'address2_extra' => trim((string) ($data['address2_extra'] ?? '')),
            'address2_street' => trim((string) ($data['address2_street'] ?? '')),
            'address2_postal' => trim((string) ($data['address2_postal'] ?? '')),
            'address2_city' => trim((string) ($data['address2_city'] ?? '')),
            'address2_country' => trim((string) ($data['address2_country'] ?? 'DE')) ?: 'DE',
            'website' => trim((string) ($data['website'] ?? '')),
            'contact_role' => self::normalizeContactRole(trim((string) ($data['contact_role'] ?? 'kunde'))),
            'social_linkedin' => trim((string) ($data['social_linkedin'] ?? '')),
            'social_xing' => trim((string) ($data['social_xing'] ?? '')),
            'social_facebook' => trim((string) ($data['social_facebook'] ?? '')),
            'social_instagram' => trim((string) ($data['social_instagram'] ?? '')),
            'social_x' => trim((string) ($data['social_x'] ?? '')),
            'social_youtube' => trim((string) ($data['social_youtube'] ?? '')),
            'social_tiktok' => trim((string) ($data['social_tiktok'] ?? '')),
            'social_github' => trim((string) ($data['social_github'] ?? '')),
            'bank_accounts' => json_encode(self::parseBankAccountsFromPost($data), JSON_UNESCAPED_UNICODE),
        ];

        $role = $fields['contact_role'];
        $hasEmployee = CrmRole::hasEmployeeProfile($role);
        $existingContact = $id ? self::findById($id) : null;
        $existingFiles = $existingContact ? $existingContact->employeeFiles : ContactFileStorage::emptyFiles();

        self::ensureContactNumbers($fields);

        if ($hasEmployee) {
            $employeeData = EmployeeData::fromPost($data);
            if ($existingContact !== null) {
                $employeeData = EmployeeData::mergeSystemMeta($employeeData, $existingContact->employeeData);
            }
            $fields['employee_data'] = json_encode($employeeData, JSON_UNESCAPED_UNICODE);
            $fields['employee_files'] = ContactFileStorage::encodeFiles($existingFiles);
        } else {
            if ($existingContact !== null) {
                ContactFileStorage::deleteAll($existingContact->employeeFiles);
            }
            $fields['employee_data'] = null;
            $fields['employee_files'] = null;
        }

        $pdo = Database::pdo();

        if ($id) {
            $sql = 'UPDATE dg_contacts SET login=:login, salutation=:salutation, first_name=:first_name, last_name=:last_name,
                display_name=:display_name, company_name=:company_name, email=:email, email_2=:email_2,
                phone_1=:phone_1, phone_2=:phone_2, customer_number=:customer_number, supplier_number=:supplier_number,
                tax_number=:tax_number, vat_id=:vat_id, contact_note=:contact_note,
                address1_extra=:address1_extra, address1_street=:address1_street, address1_postal=:address1_postal,
                address1_city=:address1_city, address1_country=:address1_country,
                address2_extra=:address2_extra, address2_street=:address2_street, address2_postal=:address2_postal,
                address2_city=:address2_city, address2_country=:address2_country,
                website=:website, contact_role=:contact_role,
                social_linkedin=:social_linkedin, social_xing=:social_xing, social_facebook=:social_facebook,
                social_instagram=:social_instagram, social_x=:social_x, social_youtube=:social_youtube,
                social_tiktok=:social_tiktok, social_github=:social_github, bank_accounts=:bank_accounts,
                employee_data=:employee_data, employee_files=:employee_files
                WHERE id=:id';
            $fields['id'] = $id;
            $pdo->prepare($sql)->execute($fields);
            $contactId = $id;
        } else {
            $sql = 'INSERT INTO dg_contacts (login, salutation, first_name, last_name, display_name, company_name,
            email, email_2, phone_1, phone_2, customer_number, supplier_number, tax_number, vat_id, contact_note,
            address1_extra, address1_street, address1_postal, address1_city, address1_country,
            address2_extra, address2_street, address2_postal, address2_city, address2_country,
            website, contact_role, social_linkedin, social_xing, social_facebook, social_instagram,
            social_x, social_youtube, social_tiktok, social_github, bank_accounts, employee_data, employee_files) VALUES (
            :login, :salutation, :first_name, :last_name, :display_name, :company_name,
            :email, :email_2, :phone_1, :phone_2, :customer_number, :supplier_number, :tax_number, :vat_id, :contact_note,
            :address1_extra, :address1_street, :address1_postal, :address1_city, :address1_country,
            :address2_extra, :address2_street, :address2_postal, :address2_city, :address2_country,
            :website, :contact_role, :social_linkedin, :social_xing, :social_facebook, :social_instagram,
            :social_x, :social_youtube, :social_tiktok, :social_github, :bank_accounts, :employee_data, :employee_files)';
            $pdo->prepare($sql)->execute($fields);
            $contactId = (int) $pdo->lastInsertId();
        }

        if ($hasEmployee && self::hasEmployeeUploads($uploads)) {
            $merged = ContactFileStorage::processUploads($contactId, $uploads, $existingFiles);
            $stmt = $pdo->prepare('UPDATE dg_contacts SET employee_files = :employee_files WHERE id = :id');
            $stmt->execute([
                'employee_files' => ContactFileStorage::encodeFiles($merged),
                'id' => $contactId,
            ]);
        }

        $savedContact = self::findById($contactId);
        if ($savedContact !== null) {
            if ($savedContact->isCompany()) {
                $pdo->prepare('DELETE FROM dg_contact_company_links WHERE person_contact_id = :id')
                    ->execute(['id' => $contactId]);
                ContactCompanyLinkRepository::syncEmployeesForCompany($contactId, $data);
            } else {
                $pdo->prepare('DELETE FROM dg_contact_company_links WHERE company_contact_id = :id')
                    ->execute(['id' => $contactId]);
                ContactCompanyLinkRepository::syncEmployerForPerson($contactId, $data);
            }
        }

        return $contactId;
    }

    /** @param array<string, string> $employeeData */
    public static function updateEmployeeData(int $id, array $employeeData): void
    {
        $pdo = Database::pdo();
        $stmt = $pdo->prepare('UPDATE dg_contacts SET employee_data = :employee_data WHERE id = :id');
        $stmt->execute([
            'employee_data' => json_encode(EmployeeData::sanitize($employeeData), JSON_UNESCAPED_UNICODE),
            'id' => $id,
        ]);
    }

    public static function isEmailInUse(string $email, ?int $excludeContactId = null): bool
    {
        $email = strtolower(trim($email));
        if ($email === '' || !Database::isConfigured()) {
            return false;
        }

        $sql = 'SELECT id FROM dg_contacts
                WHERE (LOWER(TRIM(email)) = :email OR LOWER(TRIM(email_2)) = :email2)';
        $params = ['email' => $email, 'email2' => $email];
        if ($excludeContactId !== null && $excludeContactId > 0) {
            $sql .= ' AND id <> :exclude_id';
            $params['exclude_id'] = $excludeContactId;
        }
        $sql .= ' LIMIT 1';

        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute($params);

        return (bool) $stmt->fetchColumn();
    }

    public static function setPrimaryEmailIfEmpty(int $id, string $email): void
    {
        $email = trim($email);
        if ($id <= 0 || $email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            return;
        }
        $pdo = Database::pdo();
        $stmt = $pdo->prepare(
            'UPDATE dg_contacts SET email = :email WHERE id = :id AND (email IS NULL OR TRIM(email) = \'\')'
        );
        $stmt->execute(['email' => $email, 'id' => $id]);
    }

    /** @return array<string, string> */
    public static function stammForSocialDraft(Contact $contact): array
    {
        return [
            'first_name' => $contact->firstName,
            'last_name' => $contact->lastName,
            'company_name' => $contact->companyName,
            'display_name' => $contact->displayName,
            'address1_street' => $contact->address1Street,
            'address1_postal' => $contact->address1Postal,
            'address1_city' => $contact->address1City,
            'address1_country' => $contact->address1Country,
            'tax_number' => $contact->taxNumber,
            'vat_id' => $contact->vatId,
        ];
    }

    /**
     * @param array<string, mixed> $post
     * @param array<string, mixed> $uploads
     * @return array{ok: bool, missing: list<string>, warnings: list<string>, contact_id: int}
     */
    public static function prepareSocialSecurityRegistrationDraft(array $post, int $contactId, array $uploads = []): array
    {
        if ($contactId <= 0) {
            throw new InvalidArgumentException('Kontakt muss zuerst gespeichert werden.');
        }

        $savedId = self::save($post, $contactId, $uploads);
        $contact = self::findById($savedId);
        if ($contact === null || !CrmRole::hasEmployeeProfile($contact->contactRole)) {
            throw new RuntimeException('Mitarbeiterprofil nicht gefunden.');
        }

        $result = SocialSecurityRegistrationDraft::build(
            $contact->employeeData,
            self::stammForSocialDraft($contact)
        );
        $employeeData = SocialSecurityRegistrationDraft::applyDraftResult($contact->employeeData, $result);
        self::updateEmployeeData($savedId, $employeeData);

        return [
            'ok' => $result['ok'],
            'missing' => $result['missing'],
            'warnings' => $result['warnings'],
            'contact_id' => $savedId,
        ];
    }

    /** @return list<array<string, string>> */
    public static function defaultBankAccounts(): array
    {
        return [BankAccountTypes::emptyAccount('giro')];
    }

    /** @return array<string, string> */
    public static function bankTypes(): array
    {
        return BankAccountTypes::labels();
    }

    /** @param array<string, mixed> $data */
    public static function parseBankAccountsFromPost(array $data): array
    {
        $raw = $data['bank_accounts'] ?? [];
        if (!is_array($raw)) {
            return [];
        }

        $accounts = [];
        foreach ($raw as $account) {
            if (!is_array($account)) {
                continue;
            }
            $row = BankAccountTypes::sanitizeRow($account);
            if (BankAccountTypes::isEmpty($row)) {
                continue;
            }
            $accounts[] = $row;
        }

        return $accounts;
    }

    /** @param array<string, mixed> $uploads */
    private static function hasEmployeeUploads(array $uploads): bool
    {
        foreach (array_keys(EmployeeData::documentTypes() + EmployeeData::disabilityDocumentTypes()) as $type) {
            if (isset($uploads[$type]['error']) && (int) $uploads[$type]['error'] !== UPLOAD_ERR_NO_FILE) {
                return true;
            }
        }

        foreach (array_keys(EmployeeData::multiDocumentTypes()) as $type) {
            $errors = $uploads[$type]['error'] ?? null;
            if (!is_array($errors)) {
                continue;
            }
            foreach ($errors as $error) {
                if ((int) $error !== UPLOAD_ERR_NO_FILE) {
                    return true;
                }
            }
        }

        return false;
    }

    /** @return array<string, string> */
    public static function socialFormKeys(): array
    {
        return [
            'social_linkedin' => 'LinkedIn',
            'social_xing' => 'Xing',
            'social_facebook' => 'Facebook',
            'social_instagram' => 'Instagram',
            'social_x' => 'X (Twitter)',
            'social_youtube' => 'YouTube',
            'social_tiktok' => 'TikTok',
            'social_github' => 'GitHub',
        ];
    }

    private static function normalizeContactRole(string $role): string
    {
        return CrmRole::normalize($role);
    }

    /** @param array<string, mixed> $fields */
    private static function ensureContactNumbers(array &$fields): void
    {
        if (!Database::isConfigured()) {
            return;
        }

        $role = CrmRole::normalize((string) ($fields['contact_role'] ?? ''));
        $isCustomer = in_array($role, ['dg_kunde', 'kunde'], true);
        $isSupplier = $role === 'lieferant';

        if ($isCustomer && trim((string) ($fields['customer_number'] ?? '')) === '') {
            try {
                $fields['customer_number'] = NumberRangeSettings::allocateNext('customer', true)['number'];
            } catch (Throwable) {
                // Feld bleibt leer
            }
        }

        if ($isSupplier && trim((string) ($fields['supplier_number'] ?? '')) === '') {
            try {
                $fields['supplier_number'] = NumberRangeSettings::allocateNext('supplier', true)['number'];
            } catch (Throwable) {
                // Feld bleibt leer
            }
        }
    }

    public static function isStaffContactRole(string $role): bool
    {
        $role = self::normalizeContactRole($role);

        return in_array($role, ['dg_eigenmitarbeiter', 'administrator', 'mitarbeiter'], true);
    }

    /**
     * Mitarbeiter-Kontakte für Kalender-Zuordnung (noch nicht anderem Kalender-Mitarbeiter zugeordnet).
     *
     * @return list<array{id: int, label: string}>
     */
    public static function calendarLinkableContacts(int $forEmployeeId = 0): array
    {
        if (!Database::isConfigured()) {
            return [];
        }

        $sql = "SELECT c.id, c.display_name, c.first_name, c.last_name, c.company_name, c.salutation, c.login
                FROM dg_contacts c
                WHERE c.contact_role IN ('dg_eigenmitarbeiter', 'administrator', 'mitarbeiter')
                AND c.id NOT IN (
                    SELECT contact_id FROM dg_calendar_employees
                    WHERE contact_id > 0" . ($forEmployeeId > 0 ? ' AND id <> :employee_id' : '') . "
                )
                ORDER BY c.display_name ASC, c.last_name ASC, c.first_name ASC";
        $stmt = Database::pdo()->prepare($sql);
        if ($forEmployeeId > 0) {
            $stmt->execute(['employee_id' => $forEmployeeId]);
        } else {
            $stmt->execute();
        }

        $options = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $contact = self::map($row);
            $options[] = [
                'id' => $contact->id,
                'label' => $contact->listLabel(),
            ];
        }

        return $options;
    }

    /** Mitarbeiter-Kontakt zu CRM-Benutzer (E-Mail, sonst Login). */
    public static function findStaffContactIdForUser(User $user): ?int
    {
        if (!Database::isConfigured()) {
            return null;
        }

        $roles = "'dg_eigenmitarbeiter', 'administrator', 'mitarbeiter'";
        $email = strtolower(trim($user->email));
        if ($email !== '') {
            $stmt = Database::pdo()->prepare(
                "SELECT id FROM dg_contacts
                 WHERE contact_role IN ({$roles})
                 AND (LOWER(TRIM(email)) = :email OR LOWER(TRIM(email_2)) = :email2)
                 ORDER BY id ASC LIMIT 1"
            );
            $stmt->execute(['email' => $email, 'email2' => $email]);
            $id = $stmt->fetchColumn();
            if ($id !== false) {
                return (int) $id;
            }
        }

        $login = trim($user->username);
        if ($login !== '') {
            $stmt = Database::pdo()->prepare(
                "SELECT id FROM dg_contacts
                 WHERE contact_role IN ({$roles})
                 AND login = :login
                 ORDER BY id ASC LIMIT 1"
            );
            $stmt->execute(['login' => $login]);
            $id = $stmt->fetchColumn();
            if ($id !== false) {
                return (int) $id;
            }
        }

        return null;
    }

    /** @return array<string, string> */
    public static function emptyForm(): array
    {
        return [
            'login' => '', 'salutation' => '', 'first_name' => '', 'last_name' => '', 'display_name' => '',
            'company_name' => '', 'email' => '', 'email_2' => '', 'phone_1' => '', 'phone_2' => '',
            'customer_number' => '', 'supplier_number' => '', 'tax_number' => '', 'vat_id' => '', 'contact_note' => '',
            'address1_extra' => '', 'address1_street' => '', 'address1_postal' => '', 'address1_city' => '', 'address1_country' => 'DE',
            'address2_extra' => '', 'address2_street' => '', 'address2_postal' => '', 'address2_city' => '', 'address2_country' => 'DE',
            'website' => '', 'contact_role' => 'dg_kunde',
            'social_linkedin' => '', 'social_xing' => '', 'social_facebook' => '', 'social_instagram' => '',
            'social_x' => '', 'social_youtube' => '', 'social_tiktok' => '', 'social_github' => '',
        ];
    }

    /** @return array<string, string> */
    public static function toForm(Contact $c): array
    {
        $form = [
            'login' => $c->login, 'salutation' => $c->salutation, 'first_name' => $c->firstName,
            'last_name' => $c->lastName, 'display_name' => $c->displayName, 'company_name' => $c->companyName,
            'email' => $c->email, 'email_2' => $c->email2, 'phone_1' => $c->phone1, 'phone_2' => $c->phone2,
            'customer_number' => $c->customerNumber, 'supplier_number' => $c->supplierNumber,
            'tax_number' => $c->taxNumber, 'vat_id' => $c->vatId, 'contact_note' => $c->contactNote,
            'address1_extra' => $c->address1Extra, 'address1_street' => $c->address1Street,
            'address1_postal' => $c->address1Postal, 'address1_city' => $c->address1City, 'address1_country' => $c->address1Country,
            'address2_extra' => $c->address2Extra, 'address2_street' => $c->address2Street,
            'address2_postal' => $c->address2Postal, 'address2_city' => $c->address2City, 'address2_country' => $c->address2Country,
            'website' => $c->website, 'contact_role' => CrmRole::normalize($c->contactRole),
        ];
        foreach ($c->social as $key => $value) {
            $form['social_' . $key] = $value;
        }

        return $form;
    }

    /** @param array<string, mixed> $row */
    private static function mapBankAccounts(array $row): array
    {
        if (empty($row['bank_accounts'])) {
            return [];
        }
        $decoded = json_decode((string) $row['bank_accounts'], true);

        return is_array($decoded) ? $decoded : [];
    }

    /** @param array<string, mixed> $row */
    private static function mapEmployeeData(array $row): array
    {
        if (empty($row['employee_data'])) {
            return EmployeeData::empty();
        }
        $decoded = json_decode((string) $row['employee_data'], true);

        return is_array($decoded) ? EmployeeData::sanitize($decoded) : EmployeeData::empty();
    }

    /** @param array<string, mixed> $row */
    private static function mapEmployeeFiles(array $row): array
    {
        if (empty($row['employee_files'])) {
            return ContactFileStorage::emptyFiles();
        }
        $decoded = json_decode((string) $row['employee_files'], true);

        return ContactFileStorage::normalizeFiles($decoded);
    }

    /** @param array<string, mixed> $row */
    private static function mapSocial(array $row): array
    {
        return [
            'linkedin' => (string) ($row['social_linkedin'] ?? ''),
            'xing' => (string) ($row['social_xing'] ?? ''),
            'facebook' => (string) ($row['social_facebook'] ?? ''),
            'instagram' => (string) ($row['social_instagram'] ?? ''),
            'x' => (string) ($row['social_x'] ?? ''),
            'youtube' => (string) ($row['social_youtube'] ?? ''),
            'tiktok' => (string) ($row['social_tiktok'] ?? ''),
            'github' => (string) ($row['social_github'] ?? ''),
        ];
    }

    /** @param array<string, mixed> $row */
    private static function map(array $row): Contact
    {
        $persons = [];
        if (!empty($row['contact_persons'])) {
            $decoded = json_decode((string) $row['contact_persons'], true);
            if (is_array($decoded)) {
                $persons = $decoded;
            }
        }

        return new Contact(
            (int) $row['id'],
            (string) $row['login'],
            (string) $row['salutation'],
            (string) $row['first_name'],
            (string) $row['last_name'],
            (string) $row['display_name'],
            (string) $row['company_name'],
            (string) $row['email'],
            (string) ($row['email_2'] ?? ''),
            (string) ($row['phone_1'] ?? ''),
            (string) ($row['phone_2'] ?? ''),
            (string) ($row['customer_number'] ?? ''),
            (string) ($row['supplier_number'] ?? ''),
            (string) ($row['tax_number'] ?? ''),
            (string) ($row['vat_id'] ?? ''),
            (string) ($row['contact_note'] ?? ''),
            (string) ($row['address1_extra'] ?? ''),
            (string) ($row['address1_street'] ?? ''),
            (string) ($row['address1_postal'] ?? ''),
            (string) ($row['address1_city'] ?? ''),
            (string) ($row['address1_country'] ?? 'DE'),
            (string) ($row['address2_extra'] ?? ''),
            (string) ($row['address2_street'] ?? ''),
            (string) ($row['address2_postal'] ?? ''),
            (string) ($row['address2_city'] ?? ''),
            (string) ($row['address2_country'] ?? 'DE'),
            (string) ($row['website'] ?? ''),
            CrmRole::normalize((string) ($row['contact_role'] ?? 'dg_kunde')),
            self::mapSocial($row),
            self::mapBankAccounts($row),
            self::mapEmployeeData($row),
            self::mapEmployeeFiles($row),
            $persons,
        );
    }

    public static function delete(int $id): void
    {
        $contact = self::findById($id);
        if ($contact === null) {
            throw new InvalidArgumentException('Kontakt nicht gefunden.');
        }

        ContactFileStorage::deleteAll($contact->employeeFiles);
        ContactCompanyLinkRepository::deleteForContact($id);
        Database::pdo()->prepare('DELETE FROM dg_contacts WHERE id = :id')->execute(['id' => $id]);
    }
}
