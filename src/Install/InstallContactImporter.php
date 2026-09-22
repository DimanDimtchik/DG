<?php
declare(strict_types=1);

/**
 * CSV/Excel-Import für Kontakte (Installation + laufendes CRM).
 * Abgleich bestehender Kontakte: E-Mail, Login, Kunden-/Lieferantennummer.
 */
final class InstallContactImporter
{
    private const BATCH_SIZE = 40;

    /**
     * @param array{
     *   default_role?: string,
     *   force_role?: string|null,
     *   create_calendar?: bool,
     *   calendar_area_id?: int,
     *   on_duplicate?: 'skip'|'update_empty'|'update_all',
     *   seen?: array{email: array<string, true>, login: array<string, true>}
     * } $options
     * @return array{
     *   done: bool,
     *   progress: int,
     *   imported: int,
     *   updated: int,
     *   skipped: int,
     *   duplicates: int,
     *   errors: list<string>,
     *   message: string,
     *   next_offset?: int,
     *   seen?: array{email: array<string, true>, login: array<string, true>}
     * }
     */
    public static function importBatch(
        string $path,
        int $offset,
        string $source = 'other',
        array $options = []
    ): array {
        $rows = InstallCsvHelper::readRows($path);
        if (count($rows) < 2) {
            return [
                'done' => true,
                'progress' => 100,
                'imported' => 0,
                'updated' => 0,
                'skipped' => 0,
                'duplicates' => 0,
                'errors' => ['Die Datei enthält keine Datenzeilen.'],
                'message' => 'Keine Datenzeilen gefunden.',
                'seen' => $options['seen'] ?? ['email' => [], 'login' => []],
            ];
        }

        $defaultRole = trim((string) ($options['default_role'] ?? 'kunde'));
        if ($defaultRole === '') {
            $defaultRole = 'kunde';
        }
        $forceRole = isset($options['force_role']) && is_string($options['force_role'])
            ? trim($options['force_role'])
            : null;
        if ($forceRole === '') {
            $forceRole = null;
        }
        $createCalendar = !empty($options['create_calendar']);
        $calendarAreaId = max(0, (int) ($options['calendar_area_id'] ?? 0));
        $onDuplicate = (string) ($options['on_duplicate'] ?? 'skip');
        if (!in_array($onDuplicate, ['skip', 'update_empty', 'update_all'], true)) {
            $onDuplicate = 'skip';
        }
        $seen = is_array($options['seen'] ?? null) ? $options['seen'] : ['email' => [], 'login' => []];
        if (!isset($seen['email']) || !is_array($seen['email'])) {
            $seen['email'] = [];
        }
        if (!isset($seen['login']) || !is_array($seen['login'])) {
            $seen['login'] = [];
        }

        $map = InstallCsvHelper::mapColumns($rows[0], InstallCsvHelper::mergeAliases([
            'salutation' => ['anrede', 'salutation'],
            'first_name' => ['vorname', 'first_name'],
            'last_name' => ['nachname', 'last_name'],
            'company_name' => ['firma', 'firmenname', 'company', 'company_name'],
            'display_name' => ['anzeigename', 'display_name', 'name', 'mitarbeiter', 'employee', 'full_name'],
            'email' => ['email', 'e_mail', 'mail'],
            'phone_1' => ['telefon', 'phone', 'phone_1', 'tel'],
            'customer_number' => ['kundennummer', 'customer_number', 'kdnr'],
            'supplier_number' => ['lieferantennummer', 'supplier_number', 'liefnr'],
            'tax_number' => ['steuernummer', 'tax_number'],
            'vat_id' => ['ust_idnr', 'ust_id', 'vat_id', 'ustid'],
            'street' => ['strasse', 'straße', 'street', 'address1_street'],
            'postal' => ['plz', 'postal', 'postleitzahl'],
            'city' => ['ort', 'stadt', 'city'],
            'country' => ['land', 'country'],
            'contact_role' => ['rolle', 'contact_role', 'typ'],
            'login' => ['login', 'benutzername', 'username', 'personalnummer'],
        ], InstallImportSourcePresets::contactAliases($source)));

        $dataRows = count($rows) - 1;
        $imported = 0;
        $updated = 0;
        $skipped = 0;
        $duplicates = 0;
        $errors = [];
        $processed = 0;
        $start = max(1, $offset + 1);

        for ($i = $start, $count = count($rows); $i < $count && $processed < self::BATCH_SIZE; $i++) {
            $line = $rows[$i];
            if (InstallCsvHelper::isEmptyRow($line)) {
                continue;
            }

            $raw = InstallCsvHelper::rowFromMap($map, $line);
            try {
                $salutation = $raw['salutation'] ?? '';
                $companyName = $raw['company_name'] ?? '';
                if ($salutation === '' && $companyName !== '') {
                    $salutation = 'Firma';
                }

                $displayName = $raw['display_name'] ?? '';
                $firstName = $raw['first_name'] ?? '';
                $lastName = $raw['last_name'] ?? '';
                if ($displayName === '') {
                    $displayName = $salutation === 'Firma' && $companyName !== ''
                        ? $companyName
                        : trim($firstName . ' ' . $lastName);
                }
                if ($displayName === '') {
                    throw new InvalidArgumentException('Name fehlt.');
                }
                if ($firstName === '' && $lastName === '' && $salutation !== 'Firma') {
                    $parts = preg_split('/\s+/', $displayName, 2) ?: [];
                    $firstName = trim((string) ($parts[0] ?? $displayName));
                    $lastName = trim((string) ($parts[1] ?? ''));
                }

                $email = trim((string) ($raw['email'] ?? ''));
                $loginFromFile = trim((string) ($raw['login'] ?? ''));
                $customerNumber = trim((string) ($raw['customer_number'] ?? ''));
                $supplierNumber = trim((string) ($raw['supplier_number'] ?? ''));

                $emailKey = $email !== '' ? strtolower($email) : '';
                if ($emailKey !== '' && isset($seen['email'][$emailKey])) {
                    $duplicates++;
                    $skipped++;
                    $errors[] = 'Zeile ' . ($i + 1) . ': Doppelte E-Mail in der Datei — übersprungen.';
                    $processed++;
                    $offset = $i;
                    continue;
                }

                $existing = self::findExistingContact($email, $loginFromFile, $customerNumber, $supplierNumber);
                $matchHint = $existing !== null ? self::matchHint($existing, $email, $loginFromFile, $customerNumber, $supplierNumber) : '';

                $roleRaw = $forceRole ?? ($raw['contact_role'] ?? '');
                if ($roleRaw === '') {
                    $roleRaw = $defaultRole;
                }
                $role = self::normalizeRoleInput($roleRaw);

                $payload = [
                    'salutation' => $salutation,
                    'first_name' => $firstName,
                    'last_name' => $lastName,
                    'display_name' => $displayName,
                    'company_name' => $companyName,
                    'email' => $email,
                    'phone_1' => $raw['phone_1'] ?? '',
                    'customer_number' => $customerNumber,
                    'supplier_number' => $supplierNumber,
                    'tax_number' => $raw['tax_number'] ?? '',
                    'vat_id' => $raw['vat_id'] ?? '',
                    'address1_street' => $raw['street'] ?? '',
                    'address1_postal' => $raw['postal'] ?? '',
                    'address1_city' => $raw['city'] ?? '',
                    'address1_country' => ($raw['country'] ?? '') !== '' ? $raw['country'] : 'DE',
                    'contact_role' => $role,
                ];

                if ($existing !== null) {
                    if ($onDuplicate === 'skip') {
                        $duplicates++;
                        $skipped++;
                        $errors[] = 'Zeile ' . ($i + 1) . ': bereits vorhanden (' . $matchHint . ') — übersprungen.';
                        self::markSeen($seen, $emailKey, $existing->login);
                        $processed++;
                        $offset = $i;
                        continue;
                    }

                    $form = ContactRepository::toForm($existing);
                    $merged = self::mergeIncoming($form, $payload, $onDuplicate === 'update_all');
                    $merged['login'] = $existing->login;
                    if ($forceRole !== null) {
                        $merged['contact_role'] = $role;
                    } elseif ($onDuplicate === 'update_all' || trim((string) ($form['contact_role'] ?? '')) === '') {
                        $merged['contact_role'] = $role;
                    }
                    $contactId = ContactRepository::save($merged, $existing->id);
                    $updated++;
                    self::markSeen($seen, $emailKey, $existing->login);
                } else {
                    $loginBase = $loginFromFile !== '' ? $loginFromFile : ($email !== '' ? $email : $displayName);
                    $login = InstallCsvHelper::uniqueLogin(
                        $loginBase,
                        static fn (string $candidate): bool => ContactRepository::loginExists($candidate)
                            || isset($seen['login'][strtolower($candidate)])
                    );
                    $payload['login'] = $login;
                    $contactId = ContactRepository::save($payload);
                    $imported++;
                    self::markSeen($seen, $emailKey, $login);
                }

                if ($createCalendar && ContactRepository::isStaffContactRole($role)) {
                    self::ensureCalendarStaff($contactId, $displayName, $calendarAreaId);
                }
            } catch (Throwable $e) {
                $errors[] = 'Zeile ' . ($i + 1) . ': ' . $e->getMessage();
                $skipped++;
            }

            $processed++;
            $offset = $i;
        }

        $done = $offset >= $count - 1;
        $progress = $dataRows > 0 ? (int) min(100, round(($offset / $dataRows) * 100)) : 100;

        $parts = [];
        if ($imported > 0) {
            $parts[] = $imported . ' neu';
        }
        if ($updated > 0) {
            $parts[] = $updated . ' aktualisiert';
        }
        if ($duplicates > 0) {
            $parts[] = $duplicates . ' Duplikate';
        }

        return [
            'done' => $done,
            'progress' => $progress,
            'imported' => $imported,
            'updated' => $updated,
            'skipped' => $skipped,
            'duplicates' => $duplicates,
            'errors' => $errors,
            'message' => $done
                ? ('Kontakte: ' . ($parts !== [] ? implode(', ', $parts) : 'keine Änderungen') . '.')
                : sprintf('Kontakte werden importiert … (%d%%)', $progress),
            'next_offset' => $offset,
            'seen' => $seen,
        ];
    }

    public static function templateCsv(): string
    {
        return InstallCsvHelper::templateCsv(
            ['Anrede', 'Vorname', 'Nachname', 'Firma', 'E-Mail', 'Telefon', 'Kundennummer', 'Straße', 'PLZ', 'Ort', 'Rolle'],
            [
                'Anrede' => 'Frau',
                'Vorname' => 'Anna',
                'Nachname' => 'Beispiel',
                'Firma' => '',
                'E-Mail' => 'anna@beispiel.de',
                'Telefon' => '+49 221 123456',
                'Kundennummer' => 'K-10001',
                'Straße' => 'Musterweg 1',
                'PLZ' => '50667',
                'Ort' => 'Köln',
                'Rolle' => 'mitarbeiter',
            ]
        );
    }

    public static function normalizeRoleInput(string $raw): string
    {
        $v = mb_strtolower(trim($raw), 'UTF-8');
        $v = str_replace([' ', '-'], ['_', '_'], $v);

        return match ($v) {
            'mitarbeiter', 'eigenmitarbeiter', 'dg_eigenmitarbeiter', 'employee', 'staff' => 'dg_eigenmitarbeiter',
            'administrator', 'admin' => 'administrator',
            'lieferant', 'supplier' => 'lieferant',
            'kunde', 'dg_kunde', 'customer', 'client' => 'dg_kunde',
            default => $raw !== '' ? $raw : 'dg_kunde',
        };
    }

    private static function findExistingContact(
        string $email,
        string $login,
        string $customerNumber,
        string $supplierNumber
    ): ?Contact {
        if ($email !== '') {
            $byEmail = ContactRepository::findByEmailMatch($email);
            if ($byEmail !== null) {
                return $byEmail;
            }
        }
        if ($login !== '') {
            $byLogin = ContactRepository::findByLogin($login);
            if ($byLogin !== null) {
                return $byLogin;
            }
        }
        if ($customerNumber !== '') {
            $byCust = ContactRepository::findByCustomerNumber($customerNumber);
            if ($byCust !== null) {
                return $byCust;
            }
        }
        if ($supplierNumber !== '') {
            return ContactRepository::findBySupplierNumber($supplierNumber);
        }

        return null;
    }

    private static function matchHint(
        Contact $existing,
        string $email,
        string $login,
        string $customerNumber,
        string $supplierNumber
    ): string {
        $bits = ['#' . $existing->id];
        if ($email !== '' && (
            strtolower(trim($existing->email)) === strtolower($email)
            || strtolower(trim($existing->email2)) === strtolower($email)
        )) {
            $bits[] = 'E-Mail';
        }
        if ($login !== '' && $existing->login === $login) {
            $bits[] = 'Login';
        }
        if ($customerNumber !== '' && trim($existing->customerNumber) === $customerNumber) {
            $bits[] = 'KdNr';
        }
        if ($supplierNumber !== '' && trim($existing->supplierNumber) === $supplierNumber) {
            $bits[] = 'LiefNr';
        }

        return implode(', ', $bits);
    }

    /**
     * @param array<string, mixed> $existing
     * @param array<string, string> $incoming
     * @return array<string, mixed>
     */
    private static function mergeIncoming(array $existing, array $incoming, bool $overwrite): array
    {
        foreach ($incoming as $key => $value) {
            if ($key === 'login') {
                continue;
            }
            $new = trim((string) $value);
            if ($new === '') {
                continue;
            }
            $old = trim((string) ($existing[$key] ?? ''));
            if ($overwrite || $old === '') {
                $existing[$key] = $new;
            }
        }

        return $existing;
    }

    /**
     * @param array{email: array<string, true>, login: array<string, true>} $seen
     */
    private static function markSeen(array &$seen, string $emailKey, string $login): void
    {
        if ($emailKey !== '') {
            $seen['email'][$emailKey] = true;
        }
        $loginKey = strtolower(trim($login));
        if ($loginKey !== '') {
            $seen['login'][$loginKey] = true;
        }
    }

    private static function ensureCalendarStaff(int $contactId, string $name, int $areaId): void
    {
        if ($contactId < 1 || !class_exists('CalendarStaffRepository')) {
            return;
        }
        foreach (CalendarStaffRepository::getEmployees(false) as $emp) {
            if ((int) ($emp['contact_id'] ?? 0) === $contactId) {
                return;
            }
        }
        if ($areaId < 1) {
            $areas = CalendarStaffRepository::getAreas(true);
            $areaId = $areas !== [] ? (int) $areas[0]['id'] : 0;
            if ($areaId < 1) {
                CalendarStaffRepository::saveArea(['name' => 'Standard', 'sort_order' => 0, 'is_active' => 1]);
                $areas = CalendarStaffRepository::getAreas(true);
                $areaId = $areas !== [] ? (int) $areas[0]['id'] : 0;
            }
        }
        if ($areaId < 1) {
            return;
        }
        CalendarStaffRepository::saveEmployee([
            'name' => $name,
            'contact_id' => $contactId,
            'user_id' => 0,
            'supervisor_id' => 0,
            'sort_order' => 0,
            'is_active' => 1,
            'area_ids' => [$areaId],
        ]);
    }
}
