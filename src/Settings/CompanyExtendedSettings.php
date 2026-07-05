<?php
declare(strict_types=1);

/** Erweiterte Firmendaten: BG, Kammern, Finanzämter, Mitgliedschaften usw. */
final class CompanyExtendedSettings
{
    public const STORE_KEY = 'company_extended';

    /** @return array<string, mixed> */
    public static function defaults(): array
    {
        return [
            'legal_name' => '',
            'company_type' => '',
            'employee_count_mode' => 'auto',
            'employee_count_manual' => 0,
            'industry' => '',
            'tax_numbers' => [
                'est' => '',
                'ust' => '',
                'gst' => '',
                'kst' => '',
                'steuer_id' => '',
                'wirtschafts_id' => '',
            ],
            'trade_register' => [
                'court' => '',
                'number' => '',
            ],
            'bg_data' => [
                'carrier_key' => '',
                'company_number' => '',
                'member_no' => '',
                'recipient_name' => '',
                'street' => '',
                'postal_code' => '',
                'city' => '',
                'phone' => '',
                'email' => '',
            ],
            'institutions' => [
                'ihk' => self::institutionDefaults(),
                'hwk' => self::institutionDefaults(),
                'union' => self::institutionDefaults(false),
                'works_council' => self::institutionDefaults(false),
            ],
            'employment_agency' => [
                'name' => 'Agentur für Arbeit',
                'betriebsnummer' => '',
                'contact' => '',
                'phone' => '',
                'email' => '',
            ],
            'finanzaemter' => [],
            'finanzamt_resolved' => [],
            'professional_chambers' => [],
            'trade_associations' => [],
            'memberships' => [],
            'addresses' => [],
            'owners' => [],
            'bank_accounts' => [],
        ];
    }

    /** @return array<string, string> */
    private static function institutionDefaults(bool $withMemberNo = true): array
    {
        if ($withMemberNo) {
            return ['name' => '', 'member_no' => '', 'contact' => '', 'phone' => '', 'email' => ''];
        }

        return ['name' => '', 'contact' => '', 'phone' => '', 'email' => ''];
    }

    /** @return array<string, mixed> */
    public static function config(): array
    {
        $stored = SettingsStore::get(self::STORE_KEY, self::defaults());

        return self::normalize(is_array($stored) ? $stored : []);
    }

    /** @return array<string, mixed> */
    public static function forForm(): array
    {
        $cfg = self::config();
        $basic = CompanySettings::config();

        if (trim((string) ($cfg['tax_numbers']['est'] ?? '')) === '' && trim($basic['tax_number']) !== '') {
            $cfg['tax_numbers']['est'] = $basic['tax_number'];
        }
        if (trim((string) ($cfg['tax_numbers']['ust'] ?? '')) === '' && trim($basic['vat_id']) !== '') {
            $cfg['tax_numbers']['ust'] = $basic['vat_id'];
        }

        if (empty($cfg['finanzaemter']) && !empty($cfg['finanzamt_resolved']['office']['name'])) {
            $office = $cfg['finanzamt_resolved']['office'];
            $cfg['finanzaemter'] = [[
                'bufo_nr' => (string) ($cfg['finanzamt_resolved']['bufo_nr'] ?? $office['bufo_nr'] ?? ''),
                'name' => (string) ($office['name'] ?? ''),
                'street' => (string) ($office['street'] ?? ''),
                'postal_code' => (string) ($office['postal_code'] ?? ''),
                'city' => (string) ($office['city'] ?? ''),
                'phone' => (string) ($office['phone'] ?? ''),
                'email' => (string) ($office['email'] ?? ''),
                'opening_hours' => (string) ($office['opening_hours'] ?? ''),
                'is_primary' => '1',
                'notes' => '',
            ]];
        }

        if (empty($cfg['finanzaemter'])) {
            $cfg['finanzaemter'] = [self::emptyFinanzamtRow()];
        }
        if (empty($cfg['professional_chambers'])) {
            $cfg['professional_chambers'] = [self::emptyOrgRow(true)];
        }
        if (empty($cfg['trade_associations'])) {
            $cfg['trade_associations'] = [self::emptyOrgRow(true)];
        }
        if (empty($cfg['memberships'])) {
            $cfg['memberships'] = [self::emptyMembershipRow()];
        }
        if (empty($cfg['addresses'])) {
            $cfg['addresses'] = self::seedAddressesFromBasic($basic);
        }
        if (empty($cfg['owners'])) {
            $cfg['owners'] = [self::emptyOwnerRow()];
        }
        if (empty($cfg['bank_accounts'])) {
            $cfg['bank_accounts'] = [BankAccountTypes::emptyAccount()];
        }

        return $cfg;
    }

    /** @return list<array{id: int, label: string}> */
    public static function ownerUserOptions(): array
    {
        $options = [];
        foreach (UserRepository::all() as $user) {
            if (!$user->hasRole('dg_eigenmitarbeiter') && !$user->hasRole('administrator')) {
                continue;
            }
            $label = $user->displayName !== '' ? $user->displayName : $user->username;
            if (!$user->employeeActive) {
                $label .= ' (inaktiv)';
            }
            $options[] = ['id' => $user->id, 'label' => $label];
        }

        return $options;
    }

    public static function activeEmployeeCount(): int
    {
        $count = 0;
        foreach (UserRepository::all() as $user) {
            if ($user->employeeActive && ($user->hasRole('dg_eigenmitarbeiter') || $user->hasRole('administrator'))) {
                $count++;
            }
        }

        return $count;
    }

    public static function displayEmployeeCount(array $cfg): int
    {
        if (($cfg['employee_count_mode'] ?? 'auto') === 'manual') {
            return max(0, (int) ($cfg['employee_count_manual'] ?? 0));
        }

        return self::activeEmployeeCount();
    }

    /**
     * @param array<string, string> $basic
     * @return list<array<string, string>>
     */
    private static function seedAddressesFromBasic(array $basic): array
    {
        if (trim($basic['street'] ?? '') === '' && trim($basic['postal'] ?? '') === '' && trim($basic['city'] ?? '') === '') {
            return [self::emptyAddressRow()];
        }

        return [[
            'type' => 'hauptsitz',
            'label' => '',
            'street' => (string) ($basic['street'] ?? ''),
            'postal_code' => (string) ($basic['postal'] ?? ''),
            'city' => (string) ($basic['city'] ?? ''),
            'country' => (string) (($basic['country'] ?? '') ?: 'DE'),
        ]];
    }

    /** @param array<string, mixed> $input */
    public static function saveFromPost(array $input): void
    {
        $clean = self::sanitize($input);
        SettingsStore::set(self::STORE_KEY, $clean);

        $basic = CompanySettings::config();
        $basic['tax_number'] = (string) ($clean['tax_numbers']['est'] ?? $basic['tax_number']);
        $basic['vat_id'] = (string) ($clean['tax_numbers']['ust'] ?? $basic['vat_id']);
        self::syncPrimaryAddressToBasic($basic, $clean['addresses'] ?? []);
        SettingsStore::set(CompanySettings::STORE_KEY, $basic);
    }

    /**
     * @param array<string, string> $basic
     * @param list<array<string, string>> $addresses
     */
    private static function syncPrimaryAddressToBasic(array &$basic, array $addresses): void
    {
        foreach ($addresses as $address) {
            if (($address['type'] ?? '') !== 'hauptsitz') {
                continue;
            }
            if (trim((string) ($address['street'] ?? '')) === '' && trim((string) ($address['postal_code'] ?? '')) === '') {
                continue;
            }
            $basic['street'] = (string) ($address['street'] ?? '');
            $basic['postal'] = (string) ($address['postal_code'] ?? '');
            $basic['city'] = (string) ($address['city'] ?? '');
            $basic['country'] = (string) (($address['country'] ?? '') ?: 'DE');

            return;
        }
    }

    /** @param array<string, mixed> $raw */
    private static function normalize(array $raw): array
    {
        $defaults = self::defaults();
        $cfg = array_replace_recursive($defaults, $raw);

        foreach ($defaults['institutions'] as $key => $fields) {
            $cfg['institutions'][$key] = array_replace($fields, is_array($cfg['institutions'][$key] ?? null) ? $cfg['institutions'][$key] : []);
        }

        $cfg['bg_data'] = array_replace($defaults['bg_data'], is_array($cfg['bg_data'] ?? null) ? $cfg['bg_data'] : []);
        $cfg['employment_agency'] = array_replace($defaults['employment_agency'], is_array($cfg['employment_agency'] ?? null) ? $cfg['employment_agency'] : []);
        $cfg['tax_numbers'] = array_replace($defaults['tax_numbers'], is_array($cfg['tax_numbers'] ?? null) ? $cfg['tax_numbers'] : []);
        $cfg['trade_register'] = array_replace($defaults['trade_register'], is_array($cfg['trade_register'] ?? null) ? $cfg['trade_register'] : []);

        $types = array_keys(CompanyTypes::labels());
        $cfg['company_type'] = in_array((string) ($cfg['company_type'] ?? ''), $types, true) ? (string) $cfg['company_type'] : '';
        $cfg['employee_count_mode'] = ($cfg['employee_count_mode'] ?? '') === 'manual' ? 'manual' : 'auto';
        $cfg['employee_count_manual'] = max(0, (int) ($cfg['employee_count_manual'] ?? 0));

        $cfg['finanzaemter'] = self::normalizeFinanzaemter(is_array($cfg['finanzaemter'] ?? null) ? $cfg['finanzaemter'] : []);
        $cfg['professional_chambers'] = self::normalizeOrgRows(is_array($cfg['professional_chambers'] ?? null) ? $cfg['professional_chambers'] : []);
        $cfg['trade_associations'] = self::normalizeOrgRows(is_array($cfg['trade_associations'] ?? null) ? $cfg['trade_associations'] : []);
        $cfg['memberships'] = self::normalizeMemberships(is_array($cfg['memberships'] ?? null) ? $cfg['memberships'] : []);
        $cfg['addresses'] = self::normalizeAddresses(is_array($cfg['addresses'] ?? null) ? $cfg['addresses'] : []);
        $cfg['owners'] = self::normalizeOwners(is_array($cfg['owners'] ?? null) ? $cfg['owners'] : []);
        $cfg['bank_accounts'] = self::normalizeBankAccounts(is_array($cfg['bank_accounts'] ?? null) ? $cfg['bank_accounts'] : []);

        if (empty($cfg['bg_data']['company_number']) && !empty($cfg['bg_data']['member_no'])) {
            $cfg['bg_data']['company_number'] = $cfg['bg_data']['member_no'];
        }

        return $cfg;
    }

    /** @param array<string, mixed> $input */
    private static function sanitize(array $input): array
    {
        $cfg = self::normalize([]);

        $cfg['legal_name'] = self::str($input['legal_name'] ?? '');
        $cfg['company_type'] = self::str($input['company_type'] ?? '');
        $cfg['employee_count_mode'] = ($input['employee_count_mode'] ?? '') === 'manual' ? 'manual' : 'auto';
        $cfg['employee_count_manual'] = max(0, (int) ($input['employee_count_manual'] ?? 0));
        $cfg['industry'] = self::str($input['industry'] ?? '');

        foreach (array_keys($cfg['tax_numbers']) as $key) {
            $cfg['tax_numbers'][$key] = self::str($input['tax_numbers'][$key] ?? '');
        }

        $cfg['trade_register']['court'] = self::str($input['trade_register']['court'] ?? '');
        $cfg['trade_register']['number'] = self::str($input['trade_register']['number'] ?? '');

        $cfg['bg_data'] = self::sanitizeBgData(is_array($input['bg_data'] ?? null) ? $input['bg_data'] : []);
        $cfg['institutions'] = self::sanitizeInstitutions(is_array($input['institutions'] ?? null) ? $input['institutions'] : []);
        $cfg['employment_agency'] = self::sanitizeEmploymentAgency(is_array($input['employment_agency'] ?? null) ? $input['employment_agency'] : []);

        $cfg['finanzaemter'] = self::normalizeFinanzaemter(is_array($input['finanzaemter'] ?? null) ? $input['finanzaemter'] : []);
        $cfg['professional_chambers'] = self::normalizeOrgRows(is_array($input['professional_chambers'] ?? null) ? $input['professional_chambers'] : []);
        $cfg['trade_associations'] = self::normalizeOrgRows(is_array($input['trade_associations'] ?? null) ? $input['trade_associations'] : []);
        $cfg['memberships'] = self::normalizeMemberships(is_array($input['memberships'] ?? null) ? $input['memberships'] : []);
        $cfg['addresses'] = self::normalizeAddresses(is_array($input['addresses'] ?? null) ? $input['addresses'] : []);
        $cfg['owners'] = self::normalizeOwners(is_array($input['owners'] ?? null) ? $input['owners'] : []);
        $cfg['bank_accounts'] = self::normalizeBankAccounts(is_array($input['bank_accounts'] ?? null) ? $input['bank_accounts'] : []);

        $est = trim((string) ($cfg['tax_numbers']['est'] ?? ''));
        $plz = trim(CompanySettings::config()['postal'] ?? '');
        $city = trim(CompanySettings::config()['city'] ?? '');
        if ($plz === '' && !empty($cfg['addresses'][0]['postal_code'])) {
            $plz = (string) $cfg['addresses'][0]['postal_code'];
            $city = (string) ($cfg['addresses'][0]['city'] ?? $city);
        }

        if ($est !== '') {
            $resolved = TaxOffice::resolve($est, ['reporting_period' => 'quarterly']);
            $cfg['finanzamt_resolved'] = self::resolvedFromLookup($resolved, $est);
            if (!self::finanzamtListHasBufo($cfg['finanzaemter'], (string) ($resolved['bufo_nr'] ?? ''))) {
                $cfg['finanzaemter'] = self::prependFinanzamtFromLookup($cfg['finanzaemter'], $resolved);
            }
        } elseif ($plz !== '') {
            $resolved = TaxOffice::resolve_by_location($plz, $city);
            if (!empty($resolved['found'])) {
                $cfg['finanzamt_resolved'] = self::resolvedFromLookup($resolved, '');
            }
        } else {
            $cfg['finanzamt_resolved'] = [];
        }

        return $cfg;
    }

    /** @param array<string, mixed> $raw */
    private static function sanitizeBgData(array $raw): array
    {
        $carrierKeys = array_keys(UvCarriers::all());
        $carrierKey = preg_replace('/[^a-z0-9_]/', '', (string) ($raw['carrier_key'] ?? '')) ?? '';
        if (!in_array($carrierKey, $carrierKeys, true)) {
            $carrierKey = '';
        }

        $companyNumber = self::str($raw['company_number'] ?? $raw['member_no'] ?? '');
        $clean = [
            'carrier_key' => $carrierKey,
            'company_number' => $companyNumber,
            'member_no' => $companyNumber,
            'recipient_name' => self::str($raw['recipient_name'] ?? ''),
            'street' => self::str($raw['street'] ?? ''),
            'postal_code' => self::str($raw['postal_code'] ?? ''),
            'city' => self::str($raw['city'] ?? ''),
            'phone' => self::str($raw['phone'] ?? ''),
            'email' => self::email($raw['email'] ?? ''),
        ];

        if ($carrierKey !== '') {
            $carrier = UvCarriers::get($carrierKey);
            if ($carrier !== null) {
                if ($clean['recipient_name'] === '') {
                    $clean['recipient_name'] = (string) $carrier['name'];
                }
                if ($clean['street'] === '') {
                    $clean['street'] = (string) $carrier['street'];
                }
                if ($clean['postal_code'] === '') {
                    $clean['postal_code'] = (string) $carrier['zip'];
                }
                if ($clean['city'] === '') {
                    $clean['city'] = (string) $carrier['city'];
                }
            }
        }

        return $clean;
    }

    /** @param array<string, mixed> $raw */
    private static function sanitizeInstitutions(array $raw): array
    {
        $defaults = self::defaults()['institutions'];
        $clean = [];

        foreach ($defaults as $instKey => $fields) {
            $row = is_array($raw[$instKey] ?? null) ? $raw[$instKey] : [];
            $clean[$instKey] = [];
            foreach ($fields as $field => $default) {
                $value = $row[$field] ?? '';
                $clean[$instKey][$field] = $field === 'email' ? self::email($value) : self::str($value);
            }
        }

        return $clean;
    }

    /** @param array<string, mixed> $raw */
    private static function sanitizeEmploymentAgency(array $raw): array
    {
        return [
            'name' => self::str($raw['name'] ?? 'Agentur für Arbeit') ?: 'Agentur für Arbeit',
            'betriebsnummer' => self::str($raw['betriebsnummer'] ?? ''),
            'contact' => self::str($raw['contact'] ?? ''),
            'phone' => self::str($raw['phone'] ?? ''),
            'email' => self::email($raw['email'] ?? ''),
        ];
    }

    /** @param list<array<string, mixed>> $rows */
    private static function normalizeFinanzaemter(array $rows): array
    {
        $clean = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $item = [
                'bufo_nr' => self::str($row['bufo_nr'] ?? ''),
                'name' => self::str($row['name'] ?? ''),
                'street' => self::str($row['street'] ?? ''),
                'postal_code' => self::str($row['postal_code'] ?? ''),
                'city' => self::str($row['city'] ?? ''),
                'phone' => self::str($row['phone'] ?? ''),
                'email' => self::email($row['email'] ?? ''),
                'opening_hours' => self::str($row['opening_hours'] ?? ''),
                'is_primary' => !empty($row['is_primary']) ? '1' : '',
                'notes' => self::str($row['notes'] ?? ''),
            ];
            if (self::rowIsEmpty($item, ['is_primary'])) {
                continue;
            }
            $clean[] = $item;
        }

        return $clean;
    }

    /** @param list<array<string, mixed>> $rows */
    private static function normalizeOrgRows(array $rows): array
    {
        $clean = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $item = [
                'name' => self::str($row['name'] ?? ''),
                'member_no' => self::str($row['member_no'] ?? ''),
                'contact' => self::str($row['contact'] ?? ''),
                'phone' => self::str($row['phone'] ?? ''),
                'email' => self::email($row['email'] ?? ''),
            ];
            if (self::rowIsEmpty($item)) {
                continue;
            }
            $clean[] = $item;
        }

        return $clean;
    }

    /** @param list<array<string, mixed>> $rows */
    private static function normalizeMemberships(array $rows): array
    {
        $clean = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $obligation = (string) ($row['obligation'] ?? 'voluntary');
            if (!in_array($obligation, ['mandatory', 'voluntary'], true)) {
                $obligation = 'voluntary';
            }
            $item = [
                'name' => self::str($row['name'] ?? ''),
                'obligation' => $obligation,
                'member_no' => self::str($row['member_no'] ?? ''),
                'contact' => self::str($row['contact'] ?? ''),
                'phone' => self::str($row['phone'] ?? ''),
                'email' => self::email($row['email'] ?? ''),
                'notes' => self::str($row['notes'] ?? ''),
            ];
            if (self::rowIsEmpty($item, ['obligation'])) {
                continue;
            }
            $clean[] = $item;
        }

        return $clean;
    }

    /** @param array<string, mixed> $resolved */
    private static function resolvedFromLookup(array $resolved, string $taxNumber): array
    {
        $office = is_array($resolved['office'] ?? null) ? $resolved['office'] : [];

        return [
            'tax_number' => $taxNumber,
            'bufo_nr' => (string) ($resolved['bufo_nr'] ?? ''),
            'elster_number' => (string) ($resolved['elster_number'] ?? ''),
            'office' => $office,
            'deadlines' => is_array($resolved['deadlines'] ?? null) ? $resolved['deadlines'] : [],
            'found' => !empty($resolved['found']),
            'error' => (string) ($resolved['error'] ?? ''),
            'resolved_at' => gmdate('c'),
        ];
    }

    /** @param list<array<string, string>> $rows */
    private static function finanzamtListHasBufo(array $rows, string $bufoNr): bool
    {
        if ($bufoNr === '') {
            return false;
        }
        foreach ($rows as $row) {
            if (($row['bufo_nr'] ?? '') === $bufoNr) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param list<array<string, string>> $rows
     * @param array<string, mixed> $resolved
     * @return list<array<string, string>>
     */
    private static function prependFinanzamtFromLookup(array $rows, array $resolved): array
    {
        $office = is_array($resolved['office'] ?? null) ? $resolved['office'] : [];
        if (empty($office['name'])) {
            return $rows;
        }

        $row = [
            'bufo_nr' => (string) ($resolved['bufo_nr'] ?? $office['bufo_nr'] ?? ''),
            'name' => (string) ($office['name'] ?? ''),
            'street' => (string) ($office['street'] ?? ''),
            'postal_code' => (string) ($office['postal_code'] ?? ''),
            'city' => (string) ($office['city'] ?? ''),
            'phone' => (string) ($office['phone'] ?? ''),
            'email' => (string) ($office['email'] ?? ''),
            'opening_hours' => (string) ($office['opening_hours'] ?? ''),
            'is_primary' => '1',
            'notes' => '',
        ];

        return array_merge([$row], $rows);
    }

    /** @return array<string, string> */
    public static function emptyFinanzamtRow(): array
    {
        return [
            'bufo_nr' => '',
            'name' => '',
            'street' => '',
            'postal_code' => '',
            'city' => '',
            'phone' => '',
            'email' => '',
            'opening_hours' => '',
            'is_primary' => '',
            'notes' => '',
        ];
    }

    /** @return array<string, string> */
    public static function emptyOrgRow(bool $withMemberNo = true): array
    {
        $row = ['name' => '', 'contact' => '', 'phone' => '', 'email' => ''];
        if ($withMemberNo) {
            $row['member_no'] = '';
        }

        return $row;
    }

    /** @return array<string, string> */
    public static function emptyMembershipRow(): array
    {
        return [
            'name' => '',
            'obligation' => 'voluntary',
            'member_no' => '',
            'contact' => '',
            'phone' => '',
            'email' => '',
            'notes' => '',
        ];
    }

    /** @return array<string, string> */
    public static function emptyAddressRow(): array
    {
        return [
            'type' => 'hauptsitz',
            'label' => '',
            'street' => '',
            'postal_code' => '',
            'city' => '',
            'country' => 'DE',
        ];
    }

    /** @return array{name: string, share_percent: string, user_id: string} */
    public static function emptyOwnerRow(): array
    {
        return [
            'name' => '',
            'share_percent' => '',
            'user_id' => '0',
        ];
    }

    /** @param list<array<string, mixed>> $rows */
    private static function normalizeAddresses(array $rows): array
    {
        $types = array_keys(CompanyAddressTypes::labels());
        $clean = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $type = self::str($row['type'] ?? 'hauptsitz');
            $item = [
                'type' => in_array($type, $types, true) ? $type : 'sonstiges',
                'label' => self::str($row['label'] ?? ''),
                'street' => self::str($row['street'] ?? ''),
                'postal_code' => self::str($row['postal_code'] ?? ''),
                'city' => self::str($row['city'] ?? ''),
                'country' => self::str($row['country'] ?? 'DE') ?: 'DE',
            ];
            if (self::rowIsEmpty($item, ['type', 'country'])) {
                continue;
            }
            $clean[] = $item;
        }

        return $clean;
    }

    /** @param list<array<string, mixed>> $rows */
    private static function normalizeOwners(array $rows): array
    {
        $clean = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $shareRaw = str_replace(',', '.', self::str($row['share_percent'] ?? ''));
            $share = is_numeric($shareRaw) ? (float) $shareRaw : 0.0;
            $share = max(0.0, min(100.0, $share));
            $name = self::str($row['name'] ?? '');
            $userId = max(0, (int) ($row['user_id'] ?? 0));
            if ($name === '' && $share <= 0 && $userId === 0) {
                continue;
            }
            $clean[] = [
                'name' => $name,
                'share_percent' => $share > 0 ? rtrim(rtrim(number_format($share, 2, '.', ''), '0'), '.') : '',
                'user_id' => (string) $userId,
            ];
        }

        return $clean;
    }

    /** @param list<array<string, mixed>> $rows */
    private static function normalizeBankAccounts(array $rows): array
    {
        $clean = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $item = BankAccountTypes::sanitizeRow($row);
            if (BankAccountTypes::isEmpty($item)) {
                continue;
            }
            $clean[] = $item;
        }

        return $clean;
    }

    /** @param array<string, string> $row @param list<string> $ignore */
    private static function rowIsEmpty(array $row, array $ignore = []): bool
    {
        foreach ($row as $key => $value) {
            if (in_array($key, $ignore, true)) {
                continue;
            }
            if (trim((string) $value) !== '') {
                return false;
            }
        }

        return true;
    }

    private static function str(mixed $value): string
    {
        return trim((string) $value);
    }

    private static function email(mixed $value): string
    {
        $email = trim((string) $value);

        return filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : $email;
    }
}
