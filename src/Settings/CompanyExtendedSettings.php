<?php
declare(strict_types=1);

/** Erweiterte Firmendaten: BG, Kammern, Finanzämter, Mitgliedschaften usw. */
final class CompanyExtendedSettings
{
    public const STORE_KEY = 'company_extended';

    /**
     * Liefert die Standardwerte.
     * @return array<string, mixed>
     */
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
                'website' => '',
                'appointment_url' => '',
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
                'website' => '',
                'appointment_url' => '',
            ],
            'finanzaemter' => [],
            'finanzamt_resolved' => [],
            'professional_chambers' => [],
            'trade_associations' => [],
            'memberships' => [],
            'addresses' => [],
            'owners' => [],
            'bank_accounts' => [],
            // Steuerliche Sonderfälle (0 % MwSt / USt-Sonderregelungen) — Firmenstatus, nicht Belegdarstellung
            'tax_special_cases' => [
                'kleinunternehmer' => [
                    'enabled' => false,
                    'valid_from' => '',
                    'valid_to' => '',
                    'ended_early_at' => '',
                    'hint_text' => 'Gemäß § 19 Abs. 1 UStG wird keine Umsatzsteuer berechnet und ausgewiesen (Kleinunternehmerregelung).',
                ],
                'photovoltaik' => [
                    'enabled' => false,
                    'hint_text' => 'Die Lieferung und Installation der Photovoltaikanlage ist umsatzsteuerbefreit nach § 12 Abs. 3 UStG i. V. m. § 3g UStG.',
                ],
                'reverse_charge_13b' => [
                    'enabled' => false,
                ],
                'tax_free_4' => [
                    'enabled' => false,
                ],
            ],
        ];
    }

    /**
     * Methode institution defaults.
     * @param bool $withMemberNo
     * @return array<string, mixed>
     */
    private static function institutionDefaults(bool $withMemberNo = true): array
    {
        if ($withMemberNo) {
            return [
                'name' => '',
                'member_no' => '',
                'contact' => '',
                'phone' => '',
                'email' => '',
                'website' => '',
                'appointment_url' => '',
            ];
        }

        return [
            'name' => '',
            'contact' => '',
            'phone' => '',
            'email' => '',
            'website' => '',
            'appointment_url' => '',
        ];
    }

    /**
     * Liefert die aktuelle Konfiguration.
     * @return array<string, mixed>
     */
    public static function config(): array
    {
        $stored = SettingsStore::get(self::STORE_KEY, self::defaults());
        $cfg = self::normalize(is_array($stored) ? $stored : []);
        $cfg['tax_special_cases'] = self::mergeLegacyKleinunternehmer($cfg['tax_special_cases']);

        return $cfg;
    }

    /**
     * Methode for form.
     * @return array<string, mixed>
     */
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
                'website' => (string) ($office['website'] ?? ''),
                'appointment_url' => (string) ($office['appointment_url'] ?? ''),
                'opening_hours' => (string) ($office['opening_hours_text'] ?? $office['opening_hours'] ?? ''),
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

    /**
     * Methode owner user options.
     * @return array<string, mixed>
     */
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

    /**
     * Methode active employee count.
     * @return int
     */
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

    /**
     * Methode display employee count.
     * @param array $cfg
     * @return int
     */
    public static function displayEmployeeCount(array $cfg): int
    {
        if (($cfg['employee_count_mode'] ?? 'auto') === 'manual') {
            return max(0, (int) ($cfg['employee_count_manual'] ?? 0));
        }

        return self::activeEmployeeCount();
    }

    /**
     * Methode seed addresses from basic.
     * @param array $basic
     * @return array<string, mixed>
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

    /**
     * Speichert Formulardaten.
     * @param array $input
     * @return void
     */
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
     * Führt aus: sync primary address to basic.
     * @param mixed $basic
     * @param array $addresses
     * @return void
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

    /**
     * Normalisiert den Eingabewert.
     * @param array $raw
     * @return array<string, mixed>
     */
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
        $cfg['tax_special_cases'] = self::normalizeTaxSpecialCases(
            is_array($cfg['tax_special_cases'] ?? null) ? $cfg['tax_special_cases'] : []
        );

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

    /**
     * Führt aus: sanitize.
     * @param array $input
     * @return array<string, mixed>
     */
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

        $cfg['tax_special_cases'] = self::sanitizeTaxSpecialCasesFromPost($input);

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

    /**
     * Führt aus: sanitize bg data.
     * @param array $raw
     * @return array<string, mixed>
     */
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
            'website' => self::url($raw['website'] ?? ''),
            'appointment_url' => self::url($raw['appointment_url'] ?? ''),
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

    /**
     * Führt aus: sanitize institutions.
     * @param array $raw
     * @return array<string, mixed>
     */
    private static function sanitizeInstitutions(array $raw): array
    {
        $defaults = self::defaults()['institutions'];
        $clean = [];

        foreach ($defaults as $instKey => $fields) {
            $row = is_array($raw[$instKey] ?? null) ? $raw[$instKey] : [];
            $clean[$instKey] = [];
            foreach ($fields as $field => $default) {
                $value = $row[$field] ?? '';
                $clean[$instKey][$field] = match ($field) {
                    'email' => self::email($value),
                    'website', 'appointment_url' => self::url($value),
                    default => self::str($value),
                };
            }
        }

        return $clean;
    }

    /**
     * Führt aus: sanitize employment agency.
     * @param array $raw
     * @return array<string, mixed>
     */
    private static function sanitizeEmploymentAgency(array $raw): array
    {
        return [
            'name' => self::str($raw['name'] ?? 'Agentur für Arbeit') ?: 'Agentur für Arbeit',
            'betriebsnummer' => self::str($raw['betriebsnummer'] ?? ''),
            'contact' => self::str($raw['contact'] ?? ''),
            'phone' => self::str($raw['phone'] ?? ''),
            'email' => self::email($raw['email'] ?? ''),
            'website' => self::url($raw['website'] ?? ''),
            'appointment_url' => self::url($raw['appointment_url'] ?? ''),
        ];
    }

    /**
     * Führt aus: normalize finanzaemter.
     * @param array $rows
     * @return array<string, mixed>
     */
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
                'email' => self::str($row['email'] ?? ''),
                'website' => self::str($row['website'] ?? ''),
                'appointment_url' => self::url($row['appointment_url'] ?? ''),
                'opening_hours' => self::str($row['opening_hours'] ?? ''),
                'is_primary' => !empty($row['is_primary']) ? '1' : '',
                'notes' => self::str($row['notes'] ?? ''),
            ];
            $channels = FinanzamtRegistry::normalize_contact_channels($item['email'], $item['website']);
            $item['email'] = $channels['email'];
            $item['website'] = $channels['website'];
            if ($item['email'] !== '' && !filter_var($item['email'], FILTER_VALIDATE_EMAIL)) {
                // Keine harte Ablehnung — URL-Reste landen oben in website.
                $item['email'] = '';
            }
            if (self::rowIsEmpty($item, ['is_primary'])) {
                continue;
            }
            $clean[] = $item;
        }

        return $clean;
    }

    /**
     * Führt aus: normalize org rows.
     * @param array $rows
     * @return array<string, mixed>
     */
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
                'website' => self::url($row['website'] ?? ''),
                'appointment_url' => self::url($row['appointment_url'] ?? ''),
            ];
            if (self::rowIsEmpty($item)) {
                continue;
            }
            $clean[] = $item;
        }

        return $clean;
    }

    /**
     * Führt aus: normalize memberships.
     * @param array $rows
     * @return array<string, mixed>
     */
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
                'website' => self::url($row['website'] ?? ''),
                'appointment_url' => self::url($row['appointment_url'] ?? ''),
                'notes' => self::str($row['notes'] ?? ''),
            ];
            if (self::rowIsEmpty($item, ['obligation'])) {
                continue;
            }
            $clean[] = $item;
        }

        return $clean;
    }

    /**
     * Führt aus: resolved from lookup.
     * @param array $resolved
     * @param string $taxNumber
     * @return array<string, mixed>
     */
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

    /**
     * Methode finanzamt list has bufo.
     * @param array $rows
     * @param string $bufoNr
     * @return bool
     */
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
     * Methode prepend finanzamt from lookup.
     * @param array $rows
     * @param array $resolved
     * @return array<string, mixed>
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
            'website' => (string) ($office['website'] ?? ''),
            'appointment_url' => (string) ($office['appointment_url'] ?? ''),
            'opening_hours' => (string) ($office['opening_hours_text'] ?? $office['opening_hours'] ?? ''),
            'is_primary' => '1',
            'notes' => '',
        ];

        return array_merge([$row], $rows);
    }

    /**
     * Methode empty finanzamt row.
     * @return array<string, mixed>
     */
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
            'website' => '',
            'appointment_url' => '',
            'opening_hours' => '',
            'is_primary' => '',
            'notes' => '',
        ];
    }

    /**
     * Methode empty org row.
     * @param bool $withMemberNo
     * @return array<string, mixed>
     */
    public static function emptyOrgRow(bool $withMemberNo = true): array
    {
        $row = [
            'name' => '',
            'contact' => '',
            'phone' => '',
            'email' => '',
            'website' => '',
            'appointment_url' => '',
        ];
        if ($withMemberNo) {
            $row['member_no'] = '';
        }

        return $row;
    }

    /**
     * Methode empty membership row.
     * @return array<string, mixed>
     */
    public static function emptyMembershipRow(): array
    {
        return [
            'name' => '',
            'obligation' => 'voluntary',
            'member_no' => '',
            'contact' => '',
            'phone' => '',
            'email' => '',
            'website' => '',
            'appointment_url' => '',
            'notes' => '',
        ];
    }

    /**
     * Methode empty address row.
     * @return array<string, mixed>
     */
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

    /**
     * Methode empty owner row.
     * @return array<string, mixed>
     */
    public static function emptyOwnerRow(): array
    {
        return [
            'name' => '',
            'share_percent' => '',
            'user_id' => '0',
        ];
    }

    /**
     * Führt aus: normalize addresses.
     * @param array $rows
     * @return array<string, mixed>
     */
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

    /**
     * Führt aus: normalize owners.
     * @param array $rows
     * @return array<string, mixed>
     */
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

    /**
     * Führt aus: normalize bank accounts.
     * @param array $rows
     * @return array<string, mixed>
     */
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

    /**
     * Methode row is empty.
     * @param array $row
     * @param array $ignore
     * @return bool
     */
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

    /**
     * Methode str.
     * @param mixed $value
     * @return string
     */
    private static function str(mixed $value): string
    {
        return trim((string) $value);
    }

    /**
     * Methode email.
     * @param mixed $value
     * @return string
     */
    private static function email(mixed $value): string
    {
        $email = trim((string) $value);

        return filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : $email;
    }

    /**
     * Normalisiert Website-/Termin-URLs (https://-Prefix wenn nötig).
     */
    private static function url(mixed $value): string
    {
        $url = trim((string) $value);
        if ($url === '') {
            return '';
        }
        if (!preg_match('#^https?://#i', $url) && preg_match('#^[a-z0-9.-]+\.[a-z]{2,}(/|$)#i', $url)) {
            $url = 'https://' . $url;
        }

        return $url;
    }

    /**
     * @param array<string, mixed> $raw
     * @return array<string, mixed>
     */
    private static function normalizeTaxSpecialCases(array $raw): array
    {
        $defaults = self::defaults()['tax_special_cases'];
        $kuIn = is_array($raw['kleinunternehmer'] ?? null) ? $raw['kleinunternehmer'] : [];
        $pvIn = is_array($raw['photovoltaik'] ?? null) ? $raw['photovoltaik'] : [];
        $rcIn = is_array($raw['reverse_charge_13b'] ?? null) ? $raw['reverse_charge_13b'] : [];
        $tfIn = is_array($raw['tax_free_4'] ?? null) ? $raw['tax_free_4'] : [];

        $kuHint = self::str($kuIn['hint_text'] ?? $defaults['kleinunternehmer']['hint_text']);
        if ($kuHint === '') {
            $kuHint = (string) $defaults['kleinunternehmer']['hint_text'];
        }
        $pvHint = self::str($pvIn['hint_text'] ?? $defaults['photovoltaik']['hint_text']);
        if ($pvHint === '') {
            $pvHint = (string) $defaults['photovoltaik']['hint_text'];
        }

        return [
            'kleinunternehmer' => [
                'enabled' => !empty($kuIn['enabled']),
                'valid_from' => self::sanitizeDateYmd((string) ($kuIn['valid_from'] ?? '')),
                'valid_to' => self::sanitizeDateYmd((string) ($kuIn['valid_to'] ?? '')),
                'ended_early_at' => self::sanitizeDateYmd((string) ($kuIn['ended_early_at'] ?? '')),
                'hint_text' => $kuHint,
            ],
            'photovoltaik' => [
                'enabled' => !empty($pvIn['enabled']),
                'hint_text' => $pvHint,
            ],
            'reverse_charge_13b' => [
                'enabled' => !empty($rcIn['enabled']),
            ],
            'tax_free_4' => [
                'enabled' => !empty($tfIn['enabled']),
            ],
        ];
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    private static function sanitizeTaxSpecialCasesFromPost(array $input): array
    {
        $posted = is_array($input['tax_special_cases'] ?? null) ? $input['tax_special_cases'] : [];

        return self::normalizeTaxSpecialCases($posted);
    }

    /**
     * Übernimmt §-19-Daten aus der früheren Belegdarstellung, falls Firmendaten noch leer sind.
     *
     * @param array<string, mixed> $taxSpecial
     * @return array<string, mixed>
     */
    private static function mergeLegacyKleinunternehmer(array $taxSpecial): array
    {
        $ku = is_array($taxSpecial['kleinunternehmer'] ?? null) ? $taxSpecial['kleinunternehmer'] : [];
        $hasOwn = !empty($ku['enabled'])
            || trim((string) ($ku['valid_from'] ?? '')) !== ''
            || trim((string) ($ku['valid_to'] ?? '')) !== ''
            || trim((string) ($ku['ended_early_at'] ?? '')) !== '';
        if ($hasOwn) {
            return $taxSpecial;
        }

        $legacyStore = SettingsStore::get('document_presentation', []);
        if (!is_array($legacyStore)) {
            return $taxSpecial;
        }
        $legacyKu = is_array($legacyStore['kleinunternehmer'] ?? null) ? $legacyStore['kleinunternehmer'] : [];
        if ($legacyKu === []) {
            return $taxSpecial;
        }

        $merged = self::normalizeTaxSpecialCases(array_merge($taxSpecial, [
            'kleinunternehmer' => $legacyKu,
        ]));

        return $merged;
    }

    private static function sanitizeDateYmd(string $value): string
    {
        $value = trim($value);
        if ($value === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return '';
        }

        return $value;
    }

    /**
     * @return array<string, mixed>
     */
    public static function taxSpecialCases(): array
    {
        return self::config()['tax_special_cases'];
    }

    /**
     * @return array{enabled: bool, valid_from: string, valid_to: string, ended_early_at: string, hint_text: string}
     */
    public static function kleinunternehmerConfig(): array
    {
        $ku = self::taxSpecialCases()['kleinunternehmer'] ?? [];

        return [
            'enabled' => !empty($ku['enabled']),
            'valid_from' => (string) ($ku['valid_from'] ?? ''),
            'valid_to' => (string) ($ku['valid_to'] ?? ''),
            'ended_early_at' => (string) ($ku['ended_early_at'] ?? ''),
            'hint_text' => (string) ($ku['hint_text'] ?? ''),
        ];
    }

    public static function isKleinunternehmerActiveOn(?string $voucherDateYmd): bool
    {
        $cfg = self::kleinunternehmerConfig();
        if (empty($cfg['enabled'])) {
            return false;
        }
        $day = trim((string) $voucherDateYmd);
        if ($day === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $day)) {
            $day = date('Y-m-d');
        }
        $from = (string) ($cfg['valid_from'] ?? '');
        $to = (string) ($cfg['valid_to'] ?? '');
        $ended = (string) ($cfg['ended_early_at'] ?? '');
        if ($from !== '' && $day < $from) {
            return false;
        }
        if ($ended !== '' && $day >= $ended) {
            return false;
        }
        if ($to !== '' && $day > $to) {
            return false;
        }

        return true;
    }

    public static function kleinunternehmerHintForDate(?string $voucherDateYmd): string
    {
        if (!self::isKleinunternehmerActiveOn($voucherDateYmd)) {
            return '';
        }

        return trim((string) self::kleinunternehmerConfig()['hint_text']);
    }
}
