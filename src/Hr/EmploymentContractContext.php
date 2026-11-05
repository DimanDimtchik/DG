<?php
declare(strict_types=1);

/**
 * Platzhalter und NachwG-Pflichtfeldprüfung für Arbeitsvertrags-PDFs.
 */
final class EmploymentContractContext
{
    public const TYPE_MINIJOB = 'minijob';
    public const TYPE_PARTTIME = 'parttime';
    public const TYPE_FULLTIME = 'fulltime';

    /**
     * @return array<string, string>
     */
    public static function typeLabels(): array
    {
        return [
            self::TYPE_MINIJOB => 'Minijob (geringfügig)',
            self::TYPE_PARTTIME => 'Teilzeit',
            self::TYPE_FULLTIME => 'Vollzeit',
        ];
    }

    /**
     * @param array<string, mixed> $options contract_type?, as_of?
     * @return array{
     *   placeholders: array<string, string>,
     *   missing: list<array{key: string, label: string}>,
     *   contract_type: string,
     *   is_minor: bool,
     *   rates: array{min_wage: float, geringfuegig_month: float, from: string, known: bool}
     * }
     */
    public static function build(Contact $contact, array $options = []): array
    {
        $ed = is_array($contact->employeeData) ? $contact->employeeData : [];
        $company = CompanySettings::config();
        $asOf = trim((string) ($options['as_of'] ?? ''));
        $start = trim((string) ($ed['contract_start'] ?? ''));
        if ($start === '') {
            $start = trim((string) ($ed['entry_date'] ?? ''));
        }
        $rates = EmploymentContractRates::forDate($asOf !== '' ? $asOf : ($start !== '' ? $start : null));

        $employeeName = trim($contact->firstName . ' ' . $contact->lastName);
        if ($employeeName === '') {
            $employeeName = trim($contact->displayName);
        }

        $employeeAddress = self::joinNonEmpty([
            trim($contact->address1Street),
            trim(trim($contact->address1Postal) . ' ' . trim($contact->address1City)),
            trim($contact->address1Country) !== '' && strtoupper(trim($contact->address1Country)) !== 'DE'
                ? trim($contact->address1Country)
                : '',
        ], ', ');

        $employerName = CompanySettings::displayName();
        $employerAddress = self::joinNonEmpty([
            trim((string) ($company['street'] ?? '')),
            trim(trim((string) ($company['postal'] ?? '')) . ' ' . trim((string) ($company['city'] ?? ''))),
        ], ', ');

        $vacation = trim((string) ($ed['vacation_days_annual'] ?? ''));
        if ($vacation === '' && $contact->id > 0 && class_exists('TimeVacationEntitlementRepository')) {
            $year = (int) date('Y', strtotime($start !== '' ? $start : 'now') ?: time());
            $row = TimeVacationEntitlementRepository::find($contact->id, $year);
            if ($row !== null && (float) ($row['days_entitled'] ?? 0) > 0) {
                $vacation = rtrim(rtrim(number_format((float) $row['days_entitled'], 1, ',', ''), '0'), ',') . ' Tage';
            }
        }

        $type = self::resolveType($ed, $options);
        $birth = trim((string) ($ed['birth_date'] ?? ''));
        $isMinor = self::isMinor($birth, $start !== '' ? $start : date('Y-m-d'));

        $placeholders = [
            'employer_name' => $employerName,
            'employer_address' => $employerAddress,
            'employer_email' => trim((string) ($company['email'] ?? '')),
            'employer_phone' => trim((string) ($company['phone'] ?? '')),
            'employee_name' => $employeeName,
            'employee_address' => $employeeAddress,
            'employee_birth_date' => self::formatDateDe($birth),
            'legal_guardian_name' => trim((string) ($ed['legal_guardian_name'] ?? '')),
            'legal_guardian_address' => trim((string) ($ed['legal_guardian_address'] ?? '')),
            'contract_start' => self::formatDateDe($start),
            'contract_end' => self::formatDateDe(trim((string) ($ed['contract_end'] ?? ''))),
            'probation_period' => trim((string) ($ed['probation_period'] ?? '')),
            'job_type' => trim((string) ($ed['job_type'] ?? '')),
            'employment_relationship' => trim((string) ($ed['employment_relationship'] ?? '')),
            'work_location' => trim((string) ($ed['work_location'] ?? '')),
            'employer_site' => trim((string) ($ed['employer_site'] ?? '')),
            'working_hours' => trim((string) ($ed['working_hours'] ?? '')),
            'daily_work_minutes' => trim((string) ($ed['daily_work_minutes'] ?? '')),
            'salary' => trim((string) ($ed['salary'] ?? '')),
            'vacation_days' => $vacation,
            'training_video_url' => trim((string) ($ed['training_video_url'] ?? '')),
            'food_hygiene_duty' => !empty($ed['food_hygiene_duty']) ? '1' : '',
            'contract_type' => $type,
            'contract_type_label' => self::typeLabels()[$type] ?? $type,
            'min_wage' => $rates['known'] ? EmploymentContractRates::formatEuro($rates['min_wage']) . '/St.' : 'amtlicher Mindestlohn',
            'geringfuegig_month' => $rates['known']
                ? EmploymentContractRates::formatEuro($rates['geringfuegig_month']) . '/Monat'
                : 'amtliche Geringfügigkeitsgrenze',
            'rates_from' => self::formatDateDe($rates['from']),
            'today' => self::formatDateDe(date('Y-m-d')),
        ];

        if ($placeholders['job_type'] === '' && $placeholders['employment_relationship'] !== '') {
            $placeholders['job_type'] = $placeholders['employment_relationship'];
        }

        $missing = self::missingRequired($placeholders, $isMinor);

        return [
            'placeholders' => $placeholders,
            'missing' => $missing,
            'contract_type' => $type,
            'is_minor' => $isMinor,
            'rates' => $rates,
        ];
    }

    /**
     * @param array<string, string> $ed
     * @param array<string, mixed> $options
     */
    public static function resolveType(array $ed, array $options = []): string
    {
        $forced = trim((string) ($options['contract_type'] ?? ''));
        if (isset(self::typeLabels()[$forced])) {
            return $forced;
        }
        $payroll = trim((string) ($ed['payroll_employment_type'] ?? ''));
        if ($payroll === 'minijob' || EmployeeData::isMinijob($ed)) {
            return self::TYPE_MINIJOB;
        }
        if ($payroll === 'parttime') {
            return self::TYPE_PARTTIME;
        }
        if ($payroll === 'fulltime' || $payroll === 'managing_director') {
            return self::TYPE_FULLTIME;
        }
        $ze = trim((string) ($ed['employment_type'] ?? ''));
        if ($ze === 'minijob') {
            return self::TYPE_MINIJOB;
        }

        return self::TYPE_FULLTIME;
    }

    public static function isMinor(string $birthDate, string $asOf): bool
    {
        $birthDate = trim($birthDate);
        // PHP date_create('') liefert „jetzt“ — ohne echtes Geburtsdatum nie als minderjährig werten.
        if ($birthDate === '' || !preg_match('/^\d{4}-\d{2}-\d{2}/', $birthDate)) {
            return false;
        }
        $birth = date_create($birthDate);
        $ref = date_create(trim($asOf) !== '' ? trim($asOf) : 'today');
        if (!$birth instanceof DateTimeInterface || !$ref instanceof DateTimeInterface) {
            return false;
        }
        $age = (int) $birth->diff($ref)->y;

        return $age >= 0 && $age < 18;
    }

    /**
     * @param array<string, string> $p
     * @return list<array{key: string, label: string}>
     */
    public static function missingRequired(array $p, bool $isMinor): array
    {
        $checks = [
            'employer_name' => 'Firmenname (Einstellungen → Firma)',
            'employer_address' => 'Firmenanschrift (Einstellungen → Firma)',
            'employee_name' => 'Name der Arbeitnehmerin / des Arbeitnehmers',
            'employee_address' => 'Anschrift der Arbeitnehmerin / des Arbeitnehmers',
            'contract_start' => 'Vertragsbeginn oder Eintritt',
            'job_type' => 'Art der Tätigkeit',
            'work_location' => 'Arbeitsort',
            'working_hours' => 'Arbeitszeit',
            'salary' => 'Lohn / Gehalt',
            'vacation_days' => 'Urlaubstage (Mitarbeiterfeld oder Urlaubskonto)',
        ];
        $missing = [];
        foreach ($checks as $key => $label) {
            if (trim((string) ($p[$key] ?? '')) === '') {
                $missing[] = ['key' => $key, 'label' => $label];
            }
        }
        if ($isMinor) {
            if (trim((string) ($p['legal_guardian_name'] ?? '')) === '') {
                $missing[] = ['key' => 'legal_guardian_name', 'label' => 'Gesetzliche Vertretung (Name)'];
            }
            if (trim((string) ($p['legal_guardian_address'] ?? '')) === '') {
                $missing[] = ['key' => 'legal_guardian_address', 'label' => 'Gesetzliche Vertretung (Anschrift)'];
            }
            if (trim((string) ($p['employee_birth_date'] ?? '')) === '') {
                $missing[] = ['key' => 'employee_birth_date', 'label' => 'Geburtsdatum'];
            }
        }

        return $missing;
    }

    public static function formatDateDe(string $ymd): string
    {
        $ymd = trim($ymd);
        if ($ymd === '') {
            return '';
        }
        $dt = date_create($ymd);

        return $dt instanceof DateTimeInterface ? $dt->format('d.m.Y') : $ymd;
    }

    /**
     * @param list<string> $parts
     */
    private static function joinNonEmpty(array $parts, string $sep): string
    {
        $out = [];
        foreach ($parts as $part) {
            $part = trim($part);
            if ($part !== '') {
                $out[] = $part;
            }
        }

        return implode($sep, $out);
    }
}
