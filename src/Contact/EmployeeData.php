<?php
declare(strict_types=1);

/**
 * Employee Data.
 */
final class EmployeeData
{
    /**
     * empty.
     *
     * @return array<string, string>
     */
        public static function empty(): array
    {
        $data = [];
        foreach (self::fieldKeys() as $key) {
            $data[$key] = '';
        }
        foreach (self::systemManagedMetaKeys() as $key) {
            $data[$key] = $key === 'social_registration_status'
                ? SocialSecurityRegistrationDraft::STATUS_NONE
                : '';
        }
        $data['retention_until'] = '';

        return $data;
    }

    /**
     * systemManagedMetaKeys.
     *
     * @return list<string>
     */
        public static function systemManagedMetaKeys(): array
    {
        return [
            'social_registration_status',
            'social_registration_draft_at',
            'social_registration_draft_json',
        ];
    }

    /**
     * fieldKeys.
     *
     * @return list<string>
     */
        public static function fieldKeys(): array
    {
        return array_keys(self::fields());
    }

    /**
     * @return array<string, array{label: string, type: string, section: string, required?: bool}>
     */
    public static function fields(): array
    {
        return [
            'birth_date' => ['label' => 'Geburtsdatum', 'type' => 'date', 'section' => 'personal', 'required' => true],
            'gender' => ['label' => 'Geschlecht', 'type' => 'select', 'section' => 'personal'],
            'marital_status' => ['label' => 'Familienstand', 'type' => 'text', 'section' => 'personal'],
            'spouse_name' => ['label' => 'Ehepartner/in', 'type' => 'text', 'section' => 'personal'],
            'social_security_status' => ['label' => 'Status SV-Nummer', 'type' => 'select', 'section' => 'social', 'required' => true],
            'social_filing_office' => ['label' => 'Meldestelle (Einzug)', 'type' => 'select', 'section' => 'social', 'required' => true],
            'social_security_number' => ['label' => 'Sozialversicherungsnummer', 'type' => 'text', 'section' => 'social'],
            'health_insurance' => ['label' => 'Krankenkasse / Krankenversicherung', 'type' => 'text', 'section' => 'health'],
            'health_insurance_number' => ['label' => 'Versichertennummer', 'type' => 'text', 'section' => 'health'],
            'health_insurance_website' => ['label' => 'Krankenkasse Website', 'type' => 'url', 'section' => 'health'],
            'health_insurance_appointment_url' => ['label' => 'Krankenkasse Terminvereinbarung (URL)', 'type' => 'url', 'section' => 'health'],
            'health_status' => ['label' => 'Gesundheitszustand', 'type' => 'textarea', 'section' => 'health'],
            'treatment_needs' => ['label' => 'Behandlungserfordernisse', 'type' => 'textarea', 'section' => 'health'],
            'disability_degree' => ['label' => 'Grad der Behinderung (GdB)', 'type' => 'text', 'section' => 'disability'],
            'disability_supplementary_codes' => ['label' => 'Zusatzbuchstaben', 'type' => 'text', 'section' => 'disability'],
            'disabilities' => ['label' => 'Chronische Krankheiten / Arbeitsunfall / weitere Angaben', 'type' => 'textarea', 'section' => 'disability'],
            'employment_relationship' => ['label' => 'Beschäftigungsverhältnis', 'type' => 'text', 'section' => 'employment', 'required' => true],
            'employer_site' => ['label' => 'Beschäftigungspflichtige Stelle', 'type' => 'text', 'section' => 'employment'],
            'work_location' => ['label' => 'Arbeitsort', 'type' => 'text', 'section' => 'employment'],
            'job_type' => ['label' => 'Art der Tätigkeit', 'type' => 'text', 'section' => 'employment'],
            'entry_date' => ['label' => 'Eintritt', 'type' => 'date', 'section' => 'employment'],
            'exit_date' => ['label' => 'Austritt', 'type' => 'date', 'section' => 'employment'],
            'contract_start' => ['label' => 'Vertragsbeginn', 'type' => 'date', 'section' => 'contract'],
            'contract_end' => ['label' => 'Vertragsende (befristet)', 'type' => 'date', 'section' => 'contract'],
            'working_hours' => ['label' => 'Arbeitszeit (Freitext)', 'type' => 'text', 'section' => 'contract'],
            'daily_work_minutes' => ['label' => 'Soll-Arbeitszeit pro Tag (Minuten)', 'type' => 'number', 'section' => 'contract'],
            'employment_type' => ['label' => 'Beschäftigungsart (Zeiterfassung)', 'type' => 'select', 'section' => 'contract'],
            'overtime_allowed' => ['label' => 'Überstunden erlaubt', 'type' => 'checkbox', 'section' => 'contract'],
            'salary' => ['label' => 'Lohn / Gehalt (Freitext)', 'type' => 'text', 'section' => 'contract'],
            // Lohn L2b — Abrechnungsstammdaten (Beträge/Lohnarten = L3)
            'tax_id' => ['label' => 'Steuerliche Identifikationsnummer', 'type' => 'text', 'section' => 'payroll'],
            'tax_class' => ['label' => 'Steuerklasse', 'type' => 'select', 'section' => 'payroll'],
            'child_allowances' => ['label' => 'Kinderfreibeträge (Zähler)', 'type' => 'number', 'section' => 'payroll'],
            'religion_code' => ['label' => 'Konfession / Kirchensteuer', 'type' => 'select', 'section' => 'payroll'],
            'health_insurance_company_number' => ['label' => 'Betriebsnummer Krankenkasse', 'type' => 'text', 'section' => 'payroll'],
            'contribution_group_key' => ['label' => 'Beitragsgruppenschlüssel', 'type' => 'text', 'section' => 'payroll'],
            'person_group_key' => ['label' => 'Personengruppenschlüssel', 'type' => 'text', 'section' => 'payroll'],
            'payroll_employment_type' => ['label' => 'Beschäftigungsart (Lohn)', 'type' => 'select', 'section' => 'payroll'],
            'annual_earnings_estimate_eur' => ['label' => 'Jahresentgelt-Schätzer Midi (€)', 'type' => 'number', 'section' => 'payroll'],
            'controlling_managing_director' => ['label' => 'Beherrschender Geschäftsführer', 'type' => 'checkbox', 'section' => 'payroll'],
            'controlling_md_rationale' => ['label' => 'Begründung GF / SV-Prüfung', 'type' => 'textarea', 'section' => 'payroll'],
            'sv_exempt' => ['label' => 'SV-frei (nur mit GF-Flag)', 'type' => 'checkbox', 'section' => 'payroll'],
            'payroll_payout_bank_index' => ['label' => 'SEPA-Auszahlungskonto (Index)', 'type' => 'number', 'section' => 'payroll'],
            'payroll_notes' => ['label' => 'Interne Lohn-Hinweise', 'type' => 'textarea', 'section' => 'payroll'],
            'subsidy_amount' => ['label' => 'Beihilfebetrag', 'type' => 'text', 'section' => 'subsidy'],
            'subsidy_carrier' => ['label' => 'Beihilfeträger', 'type' => 'text', 'section' => 'subsidy'],
            'qualifications' => ['label' => 'Qualifikationen (Ausbildung, Erfahrung, Zertifizierungen)', 'type' => 'textarea', 'section' => 'qualification'],
            'performance_notes' => ['label' => 'Leistungen (Aufgaben, Kommunikation)', 'type' => 'textarea', 'section' => 'assessment'],
            'behavior_notes' => ['label' => 'Verhalten (Pünktlichkeit, Teamarbeit)', 'type' => 'textarea', 'section' => 'assessment'],
            'driver_license_classes' => ['label' => 'Führerscheinklassen', 'type' => 'text', 'section' => 'license'],
            'driver_license_valid_until' => ['label' => 'Führerschein gültig bis', 'type' => 'date', 'section' => 'license'],
        ];
    }

    /**
     * sectionLabels.
     *
     * @return array<string, string>
     */
        public static function sectionLabels(): array
    {
        return [
            'personal' => 'Persönliche Angaben',
            'social' => 'Sozialversicherung',
            'health' => 'Gesundheitsdaten',
            'disability' => 'Behinderung',
            'employment' => 'Beschäftigungsdaten',
            'contract' => 'Arbeitsvertragsdaten',
            'payroll' => 'Lohnabrechnung',
            'subsidy' => 'Beihilfedaten',
            'qualification' => 'Qualifikationsdaten',
            'assessment' => 'Beurteilung',
            'license' => 'Führerschein',
            'documents' => 'Dokumente',
            'retention' => 'Speicherdauer',
        ];
    }

    /**
     * @return list<string>
     */
    public static function payrollFieldKeys(): array
    {
        $keys = [];
        foreach (self::fields() as $key => $meta) {
            if (($meta['section'] ?? '') === 'payroll') {
                $keys[] = $key;
            }
        }

        return $keys;
    }

    /**
     * socialSecurityStatusOptions.
     *
     * @return array<string, string>
     */
        public static function socialSecurityStatusOptions(): array
    {
        return [
            'pending' => 'Noch nicht vorhanden',
            'requested' => 'Beantragt / Anmeldung läuft',
            'received' => 'Vorhanden',
        ];
    }

    /**
     * Meldestelle für Anmeldung und SV-Nummer — nicht die Krankenkasse als Name, sondern der zuständige Weg.
     *
     * @return array<string, string>
     */
    public static function socialFilingOfficeOptions(): array
    {
        return [
            '' => '— bitte wählen —',
            'kk_employee' => 'Krankenkasse des Mitarbeiters (sv-pflichtige Beschäftigung)',
            'kbs_minijob' => 'DRV Knappschaft-Bahn-See — Minijob-Zentrale (geringfügig entlohnt)',
            'kbs_sector' => 'DRV Knappschaft-Bahn-See (Bergbau, Bahn, See)',
            'drv_regional' => 'Deutsche Rentenversicherung — Regionalträger (Sonderfall)',
        ];
    }

    /**
     * filingOfficeHint
     * @param string $office
     * @return string
     */
    public static function filingOfficeHint(string $office): string
    {
        return match ($office) {
            'kk_employee' => 'Regelfall für Vollzeit/Teilzeit: Anmeldung (DEÜV) über die Krankenkasse des Mitarbeiters. Die Krankenkasse meldet an die Rentenversicherung — die SV-Nummer wird vergeben oder ist bereits bekannt.',
            'kbs_minijob' => 'Geringfügige Beschäftigung (Minijob): Anmeldung über die Minijob-Zentrale der Knappschaft-Bahn-See (oft mit Pauschalabgabe durch den Arbeitgeber).',
            'kbs_sector' => 'Nur bei Versicherten in Bergbau, Bahn oder See-Krankenversicherung der Knappschaft.',
            'drv_regional' => 'Sonderfälle ohne laufende Krankenkasse des Mitarbeiters — Rücksprache mit Lohnbüro/DRV.',
            default => 'Wählen Sie die Meldestelle passend zur Beschäftigungsart. Die Krankenkasse (Name) erfassen Sie unter Gesundheitsdaten.',
        };
    }

    /**
     * Schritte zur Beantragung der SV-Nummer, wenn sie noch fehlt (je Meldestelle).
     *
     * @return array<string, list<string>>
     */
    public static function socialSecurityApplicationStepsByOffice(): array
    {
        return [
            'kk_employee' => [
                'Stammdaten vollständig erfassen (Name, Geburtsdatum, Adresse).',
                'Krankenkasse des Mitarbeiters unter Gesundheitsdaten eintragen.',
                'Anmeldung zur Sozialversicherung (DEÜV-Meldung) an die Krankenkasse senden — über Lohnbüro, DATEV oder Ihr SV-Meldeverfahren.',
                'Krankenkasse meldet an die Deutsche Rentenversicherung; die SV-Nummer wird neu vergeben oder eine bestehende zugeordnet.',
                'SV-Nummer aus Rückmeldung der Krankenkasse oder vom Mitarbeiter übernehmen, Status auf „Vorhanden“ setzen.',
            ],
            'kbs_minijob' => [
                'Minijob beim Arbeitgeber anmelden (geringfügige Beschäftigung).',
                'Meldung über die Minijob-Zentrale der Knappschaft-Bahn-See (DEÜV) — oft Pauschalabgabe durch den Arbeitgeber.',
                'SV-Nummer wird vergeben oder zugeordnet; nach Erhalt hier eintragen und Status auf „Vorhanden“ setzen.',
            ],
            'kbs_sector' => [
                'Zuständigkeit Knappschaft-Bahn-See prüfen (Bergbau, Bahn oder See).',
                'Anmeldung über die zuständige Meldestelle der Knappschaft durchführen.',
                'SV-Nummer nach Rückmeldung eintragen, Status auf „Vorhanden“ setzen.',
            ],
            'drv_regional' => [
                'Sonderfall mit Lohnbüro oder DRV-Regionalträger klären.',
                'Anmeldung über den vereinbarten Weg (ohne laufende Krankenkasse des Mitarbeiters).',
                'SV-Nummer nach Erteilung eintragen, Status auf „Vorhanden“ setzen.',
            ],
        ];
    }

        /**
     * socialSecurityApplicationSteps
     * @param string $office
     * @param string $status Statuswert
     * @return list<string>
     */
    public static function socialSecurityApplicationSteps(string $office, string $status): array
    {
        if ($status === 'received') {
            return [];
        }

        $steps = self::socialSecurityApplicationStepsByOffice()[$office] ?? [];
        if ($status === 'requested') {
            array_unshift(
                $steps,
                'Anmeldung läuft bereits — SV-Nummer abwarten und nach Erhalt unten eintragen.'
            );
        } elseif ($status === 'pending' || $status === '') {
            array_unshift(
                $steps,
                'SV-Nummer fehlt noch — Mitarbeiter beim Arbeitgeber anmelden (siehe Schritte unten).'
            );
        }

        return $steps;
    }

        /**
     * selectOptions
     * @param string $fieldKey
     * @return array<string, string>
     */
    public static function selectOptions(string $fieldKey): array
    {
        return match ($fieldKey) {
            'gender' => self::genderOptions(),
            'social_security_status' => self::socialSecurityStatusOptions(),
            'social_filing_office' => self::socialFilingOfficeOptions(),
            'employment_type' => self::employmentTypeOptions(),
            'tax_class' => self::taxClassOptions(),
            'religion_code' => self::religionCodeOptions(),
            'payroll_employment_type' => self::payrollEmploymentTypeOptions(),
            default => [],
        };
    }

    /**
     * @return array<string, string>
     */
    public static function employmentTypeOptions(): array
    {
        return [
            '' => '—',
            'regular' => 'Vollzeit / Teilzeit (sv-pflichtig)',
            'minijob' => 'Minijob (geringfügig)',
            'intern' => 'Praktikant / Intern',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function taxClassOptions(): array
    {
        return [
            '' => '—',
            '1' => 'I',
            '2' => 'II',
            '3' => 'III',
            '4' => 'IV',
            '5' => 'V',
            '6' => 'VI',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function religionCodeOptions(): array
    {
        return [
            '' => '—',
            'none' => 'Keine Kirchensteuer / konfessionslos',
            'ek' => 'Evangelisch',
            'rk' => 'Römisch-katholisch',
            'other' => 'Andere / sonstige KiSt-pflichtig',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function payrollEmploymentTypeOptions(): array
    {
        return [
            '' => '—',
            'fulltime' => 'Vollzeit',
            'parttime' => 'Teilzeit',
            'minijob' => 'Minijob (geringfügig)',
            'midijob' => 'Midijob (Übergangsbereich)',
            'managing_director' => 'Geschäftsführer',
        ];
    }

    public static function isMinijob(array $data): bool
    {
        $payroll = trim((string) ($data['payroll_employment_type'] ?? ''));
        if ($payroll === 'minijob') {
            return true;
        }
        $type = trim((string) ($data['employment_type'] ?? ''));

        return $type === 'minijob' || trim((string) ($data['social_filing_office'] ?? '')) === 'kbs_minijob';
    }

    public static function overtimeAllowed(array $data): bool
    {
        return ($data['overtime_allowed'] ?? '') === '1';
    }

    /**
     * Persönliches Tagessoll (ohne Kalender). Z2b: kein stilles 8-h-Fallback —
     * vollständige Prio inkl. Kalender → TimeScheduleService::scheduledMinutesFor.
     */
    public static function dailyTargetMinutes(array $data): int
    {
        return TimeScheduleService::personalTargetMinutes($data);
    }

    /**
     * genderOptions.
     *
     * @return array<string, string>
     */
        public static function genderOptions(): array
    {
        return [
            '' => '—',
            'm' => 'Männlich',
            'w' => 'Weiblich',
            'd' => 'Divers',
            'x' => 'Keine Angabe',
        ];
    }

    /**
     * documentTypes.
     *
     * @return array<string, string>
     */
        public static function documentTypes(): array
    {
        return [
            'driver_license' => 'Führerschein',
            'id_card' => 'Ausweis',
            'health_insurance_card' => 'Krankenkassenkarte',
            'bank_card' => 'Bankkarte',
            'employment_contract' => 'Arbeitsvertrag',
        ];
    }

    /**
     * disabilityDocumentTypes.
     *
     * @return array<string, string>
     */
        public static function disabilityDocumentTypes(): array
    {
        return [
            'disability_id_card' => 'Behindertenausweis',
        ];
    }

    /**
     * multiDocumentTypes.
     *
     * @return array<string, string>
     */
        public static function multiDocumentTypes(): array
    {
        return [
            'medical_certificates' => 'Ärztliche Atteste',
            'payroll_slip' => 'Lohnabrechnung (PDF)',
        ];
    }

    /**
     * allDocumentTypes.
     *
     * @return array<string, string>
     */
        public static function allDocumentTypes(): array
    {
        return self::documentTypes() + self::disabilityDocumentTypes() + self::multiDocumentTypes();
    }

    /**
     * Zusatzbuchstaben laut Schwerbehindertenausweis (Vorschläge).
     *
     * @return array<string, string>
     */
    public static function disabilitySupplementaryOptions(): array
    {
        return [
            'G' => 'gehbehindert',
            'aG' => 'außergewöhnlich gehbehindert',
            'H' => 'hilflos',
            'Bl' => 'blind',
            'Gl' => 'gehörlos',
            'TBl' => 'taubblind',
            'RF' => 'Rundfunkgebührenbefreiung',
            'B' => 'Begleitperson erforderlich',
        ];
    }

    /**
     * formatSupplementaryCodes
     * @param string $raw Rohdaten
     * @return string
     */
    public static function formatSupplementaryCodes(string $raw): string
    {
        $codes = self::parseSupplementaryCodes($raw);
        if ($codes === []) {
            return '';
        }

        $options = self::disabilitySupplementaryOptions();
        $parts = [];
        foreach ($codes as $code) {
            $label = $options[$code] ?? '';
            $parts[] = $label !== '' ? $code . ' (' . $label . ')' : $code;
        }

        return implode(', ', $parts);
    }

        /**
     * parseSupplementaryCodes
     * @param string $raw Rohdaten
     * @return list<string>
     */
    public static function parseSupplementaryCodes(string $raw): array
    {
        $raw = trim($raw);
        if ($raw === '') {
            return [];
        }

        $parts = preg_split('/[\s,;]+/', $raw) ?: [];
        $known = array_keys(self::disabilitySupplementaryOptions());
        $out = [];
        foreach ($parts as $part) {
            $part = trim($part);
            if ($part === '') {
                continue;
            }
            foreach ($known as $code) {
                if (strcasecmp($part, $code) === 0) {
                    $out[] = $code;
                    break;
                }
            }
        }

        return array_values(array_unique($out));
    }

        /**
     * fromPost
     * @param array $data
     * @return array
     */
    public static function fromPost(array $data): array
    {
        $raw = $data['employee'] ?? [];
        if (!is_array($raw)) {
            return self::empty();
        }

        return self::sanitize($raw);
    }

        /**
     * Bereinigt und validiert den Eingabewert
     * @param array $raw Rohdaten
     * @return array
     */
    public static function sanitize(array $raw): array
    {
        $out = self::empty();
        foreach (self::fields() as $key => $meta) {
            if (($meta['type'] ?? '') === 'checkbox') {
                $out[$key] = !empty($raw[$key]) && (string) $raw[$key] !== '0' ? '1' : '';
                continue;
            }
            $value = trim((string) ($raw[$key] ?? ''));
            $out[$key] = $value;
        }

        // Legacy: früheres Freitextfeld „Sozialversicherungsträger“
        $legacyCarrier = trim((string) ($raw['social_insurance_carrier'] ?? ''));
        if ($legacyCarrier !== '' && $out['social_filing_office'] === '') {
            $out['social_filing_office'] = 'drv_regional';
        }

        if (!isset(self::socialSecurityStatusOptions()[$out['social_security_status']])) {
            $out['social_security_status'] = $out['social_security_number'] !== '' ? 'received' : 'pending';
        }
        if ($out['social_filing_office'] !== '' && !isset(self::socialFilingOfficeOptions()[$out['social_filing_office']])) {
            $out['social_filing_office'] = '';
        }
        if ($out['employment_type'] !== '' && !isset(self::employmentTypeOptions()[$out['employment_type']])) {
            $out['employment_type'] = '';
        }
        if ($out['social_security_status'] === 'received' && $out['social_security_number'] === '') {
            $out['social_security_status'] = 'requested';
        }

        $degree = preg_replace('/\D/', '', $out['disability_degree']);
        if ($degree === '') {
            $out['disability_degree'] = '';
        } else {
            $out['disability_degree'] = (string) min(100, max(0, (int) $degree));
        }

        $codes = self::parseSupplementaryCodes($out['disability_supplementary_codes']);
        $out['disability_supplementary_codes'] = $codes !== [] ? implode(', ', $codes) : '';

        $out = self::sanitizePayrollFields($out);

        $out['retention_until'] = self::computeRetentionUntil($out['exit_date'] ?? '');

        foreach (self::systemManagedMetaKeys() as $key) {
            if (array_key_exists($key, $raw)) {
                $out[$key] = trim((string) $raw[$key]);
            }
        }
        if (
            $out['social_registration_status'] === ''
            && !array_key_exists('social_registration_status', $raw)
        ) {
            $out['social_registration_status'] = SocialSecurityRegistrationDraft::STATUS_NONE;
        }
        if (!isset(SocialSecurityRegistrationDraft::draftStatusOptions()[$out['social_registration_status']])) {
            $out['social_registration_status'] = SocialSecurityRegistrationDraft::STATUS_NONE;
        }

        return $out;
    }

    /**
     * Lohn L2b: Plausibilität ohne Behörden-API; Speichern bleibt erlaubt.
     *
     * @param array<string, string> $out
     * @return array<string, string>
     */
    private static function sanitizePayrollFields(array $out): array
    {
        $taxId = preg_replace('/\D+/', '', (string) ($out['tax_id'] ?? '')) ?? '';
        $out['tax_id'] = $taxId;

        $taxClass = trim((string) ($out['tax_class'] ?? ''));
        $out['tax_class'] = isset(self::taxClassOptions()[$taxClass]) ? $taxClass : '';

        $out['child_allowances'] = self::normalizeChildAllowances((string) ($out['child_allowances'] ?? ''));

        $religion = trim((string) ($out['religion_code'] ?? ''));
        $out['religion_code'] = isset(self::religionCodeOptions()[$religion]) ? $religion : '';

        $kkBn = preg_replace('/\D+/', '', (string) ($out['health_insurance_company_number'] ?? '')) ?? '';
        $out['health_insurance_company_number'] = $kkBn;

        $cg = preg_replace('/[^0-9]/', '', (string) ($out['contribution_group_key'] ?? '')) ?? '';
        $out['contribution_group_key'] = mb_substr($cg, 0, 8);

        $pg = preg_replace('/[^0-9]/', '', (string) ($out['person_group_key'] ?? '')) ?? '';
        $out['person_group_key'] = mb_substr($pg, 0, 3);

        $pet = trim((string) ($out['payroll_employment_type'] ?? ''));
        $out['payroll_employment_type'] = isset(self::payrollEmploymentTypeOptions()[$pet]) ? $pet : '';

        $annual = str_replace(',', '.', trim((string) ($out['annual_earnings_estimate_eur'] ?? '')));
        if ($annual === '' || !is_numeric($annual)) {
            $out['annual_earnings_estimate_eur'] = '';
        } else {
            $out['annual_earnings_estimate_eur'] = rtrim(rtrim(number_format(max(0.0, (float) $annual), 2, '.', ''), '0'), '.');
        }

        $isMd = ($out['controlling_managing_director'] ?? '') === '1';
        $isGfType = ($out['payroll_employment_type'] ?? '') === 'managing_director';
        if (!$isGfType) {
            $out['controlling_managing_director'] = '';
            $isMd = false;
        }
        if (!$isMd) {
            $out['sv_exempt'] = '';
        }
        $out['controlling_md_rationale'] = mb_substr(trim((string) ($out['controlling_md_rationale'] ?? '')), 0, 2000);
        $out['payroll_notes'] = mb_substr(trim((string) ($out['payroll_notes'] ?? '')), 0, 2000);

        $bankIdx = (int) ($out['payroll_payout_bank_index'] ?? 0);
        $out['payroll_payout_bank_index'] = (string) max(0, $bankIdx);

        // Weich: employment_type nur setzen wenn leer oder bereits kompatibel — kein stilles Überschreiben (L2a).
        self::softSyncEmploymentTypeFromPayroll($out);

        return $out;
    }

    /**
     * @param array<string, string> $out
     */
    private static function softSyncEmploymentTypeFromPayroll(array &$out): void
    {
        $pet = (string) ($out['payroll_employment_type'] ?? '');
        if ($pet === '') {
            return;
        }
        $want = $pet === 'minijob' ? 'minijob' : 'regular';
        $cur = trim((string) ($out['employment_type'] ?? ''));
        if ($cur === '' || $cur === $want) {
            $out['employment_type'] = $want;
        }
    }

    private static function normalizeChildAllowances(string $raw): string
    {
        $raw = str_replace(',', '.', trim($raw));
        if ($raw === '' || !is_numeric($raw)) {
            return '';
        }
        $v = (float) $raw;
        if ($v < 0) {
            $v = 0.0;
        }
        if ($v > 99) {
            $v = 99.0;
        }
        // Schritt 0,5
        $v = round($v * 2) / 2;

        return fmod($v, 1.0) === 0.0 ? (string) (int) $v : number_format($v, 1, '.', '');
    }

    /**
     * Bewahrt Payroll-Keys aus $existing, wenn Speichern der Sektion nicht erlaubt ist (L2a Gate / L2c).
     *
     * @param array<string, string> $incoming sanitize()-Ergebnis
     * @param array<string, string> $existing bisher gespeichert
     * @return array<string, string>
     */
    public static function preservePayrollIfDenied(array $incoming, array $existing, bool $mayEditPayroll): array
    {
        if ($mayEditPayroll) {
            return $incoming;
        }
        foreach (self::payrollFieldKeys() as $key) {
            $incoming[$key] = (string) ($existing[$key] ?? '');
        }

        return $incoming;
    }

    /**
     * Warnungen für UI / späteren Monatslauf (blockiert Speichern nicht).
     *
     * @param array<string, string> $data
     * @return list<string>
     */
    public static function collectPayrollWarnings(array $data): array
    {
        $warnings = [];
        $pet = trim((string) ($data['payroll_employment_type'] ?? ''));
        $taxId = trim((string) ($data['tax_id'] ?? ''));
        if ($taxId !== '' && strlen($taxId) !== 11) {
            $warnings[] = 'Steuer-ID sollte 11 Ziffern haben (aktuell: ' . strlen($taxId) . ').';
        } elseif ($taxId === '' && $pet !== '') {
            $warnings[] = 'Steuer-ID fehlt — Monatslauf später blockiert.';
        }

        $kkBn = trim((string) ($data['health_insurance_company_number'] ?? ''));
        if ($kkBn !== '' && strlen($kkBn) !== 8) {
            $warnings[] = 'Betriebsnummer Krankenkasse sollte 8 Ziffern haben.';
        }

        if (($data['controlling_managing_director'] ?? '') === '1') {
            $rationale = trim((string) ($data['controlling_md_rationale'] ?? ''));
            if (mb_strlen($rationale) < 10) {
                $warnings[] = 'Begründung beherrschender GF zu kurz (mind. 10 Zeichen, Prüfung dokumentieren).';
            }
        }

        if ($pet === 'midijob' && trim((string) ($data['annual_earnings_estimate_eur'] ?? '')) === '') {
            $warnings[] = 'Midi-Job: Jahresentgelt-Schätzer fehlt.';
        }

        if ($pet === 'managing_director' && ($data['controlling_managing_director'] ?? '') !== '1') {
            $warnings[] = 'GF ohne Flag „beherrschend“ — SV-Status manuell prüfen.';
        }

        foreach (self::missingPayrollFieldsForRun($data) as $label) {
            $warnings[] = 'Für Monatslauf fehlt noch: ' . $label;
        }

        return array_values(array_unique($warnings));
    }

    /**
     * Pflichtlücken laut L2a-Matrix (für L5-Block; Speichern ok).
     *
     * @param array<string, string> $data
     * @return list<string> lesbare Labels
     */
    public static function missingPayrollFieldsForRun(array $data): array
    {
        $pet = trim((string) ($data['payroll_employment_type'] ?? ''));
        if ($pet === '') {
            return ['Beschäftigungsart (Lohn)'];
        }

        $missing = [];
        $need = static function (string $key, string $label) use ($data, &$missing): void {
            if (trim((string) ($data[$key] ?? '')) === '') {
                $missing[] = $label;
            }
        };

        $need('tax_id', 'Steuer-ID');
        $need('tax_class', 'Steuerklasse');
        // 0 ist gültig — nur komplett leer zählt als Lücke
        if (trim((string) ($data['child_allowances'] ?? '')) === '') {
            $missing[] = 'Kinderfreibeträge (0 eintragen wenn keine)';
        }
        $need('religion_code', 'Konfession / KiSt');
        $need('entry_date', 'Eintritt');

        $svRequired = in_array($pet, ['fulltime', 'parttime', 'midijob'], true)
            || ($pet === 'managing_director' && ($data['sv_exempt'] ?? '') !== '1');
        if ($svRequired) {
            $need('social_security_number', 'SV-Nummer');
            $need('health_insurance', 'Krankenkasse');
            $need('contribution_group_key', 'Beitragsgruppenschlüssel');
            $need('person_group_key', 'Personengruppenschlüssel');
        }
        if ($pet === 'minijob') {
            $need('person_group_key', 'Personengruppenschlüssel');
        }
        if ($pet === 'midijob') {
            $need('annual_earnings_estimate_eur', 'Jahresentgelt-Schätzer');
        }
        if ($pet === 'managing_director' && ($data['controlling_managing_director'] ?? '') === '1') {
            if (mb_strlen(trim((string) ($data['controlling_md_rationale'] ?? ''))) < 10) {
                $missing[] = 'GF-Begründung';
            }
        }

        return array_values(array_unique($missing));
    }

    /**
     * @param array<string, string> $current
     * @param array<string, string> $existing
     * @return array<string, string>
     */
    public static function mergeSystemMeta(array $current, array $existing): array
    {
        foreach (self::systemManagedMetaKeys() as $key) {
            $current[$key] = trim((string) ($existing[$key] ?? $current[$key] ?? ''));
        }

        return $current;
    }

    /**
     * computeRetentionUntil
     * @param string $exitDate
     * @return string
     */
    public static function computeRetentionUntil(string $exitDate): string
    {
        $exitDate = trim($exitDate);
        if ($exitDate === '') {
            return '';
        }
        try {
            return (new DateTimeImmutable($exitDate))->modify('+10 years')->format('Y-m-d');
        } catch (Throwable) {
            return '';
        }
    }

        /**
     * retentionUntil
     * @param array $data
     * @return ?string
     */
    public static function retentionUntil(array $data): ?string
    {
        $stored = trim($data['retention_until'] ?? '');
        if ($stored !== '') {
            try {
                return (new DateTimeImmutable($stored))->format('d.m.Y');
            } catch (Throwable) {
                // fall through
            }
        }

        $exit = trim($data['exit_date'] ?? '');
        if ($exit === '') {
            return null;
        }
        try {
            return (new DateTimeImmutable($exit))->modify('+10 years')->format('d.m.Y');
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @param array<string, string> $data
     * @return array{status: string, label: string, days: int|null}
     */
    public static function retentionStatus(array $data): array
    {
        $untilRaw = trim($data['retention_until'] ?? '');
        if ($untilRaw === '') {
            $untilRaw = self::computeRetentionUntil($data['exit_date'] ?? '');
        }
        if ($untilRaw === '') {
            return [
                'status' => 'open',
                'label' => 'Austritt noch nicht erfasst — Löschfrist offen',
                'days' => null,
            ];
        }

        try {
            $until = new DateTimeImmutable($untilRaw);
            $today = new DateTimeImmutable('today');
            $diffDays = (int) $today->diff($until)->format('%r%a');

            if ($diffDays < 0) {
                return [
                    'status' => 'expired',
                    'label' => 'Löschfrist abgelaufen — Daten sollten gelöscht oder anonymisiert werden',
                    'days' => $diffDays,
                ];
            }
            if ($diffDays <= 90) {
                return [
                    'status' => 'due_soon',
                    'label' => 'Löschfrist in ' . $diffDays . ' Tag(en)',
                    'days' => $diffDays,
                ];
            }

            return [
                'status' => 'active',
                'label' => 'Aufbewahrung bis ' . $until->format('d.m.Y'),
                'days' => $diffDays,
            ];
        } catch (Throwable) {
            return [
                'status' => 'open',
                'label' => 'Löschfrist konnte nicht berechnet werden',
                'days' => null,
            ];
        }
    }

    /**
     * @param array<string, string> $data
     * @return list<array{label: string, value: string}>
     */
    public static function detailFields(array $data): array
    {
        $fields = [];
        foreach (self::fields() as $key => $meta) {
            $value = trim($data[$key] ?? '');
            if ($value === '') {
                continue;
            }
            if ($meta['type'] === 'select') {
                $options = self::selectOptions($key);
                if ($options !== []) {
                    $value = $options[$value] ?? $value;
                }
            }
            if ($key === 'disability_degree' && $value !== '') {
                $value .= ' %';
            }
            if ($key === 'disability_supplementary_codes' && $value !== '') {
                $value = self::formatSupplementaryCodes($value);
            }
            $fields[] = ['label' => $meta['label'], 'value' => $value];
        }

        $retention = self::retentionUntil($data);
        if ($retention !== null) {
            $fields[] = [
                'label' => 'Löschfrist (10 Jahre nach Austritt)',
                'value' => $retention,
            ];
        }

        return $fields;
    }

    /**
     * storedMetaKeys.
     *
     * @return list<string>
     */
        public static function storedMetaKeys(): array
    {
        return array_merge(['retention_until'], self::systemManagedMetaKeys());
    }
}
