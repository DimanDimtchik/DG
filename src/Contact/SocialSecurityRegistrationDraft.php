<?php
declare(strict_types=1);

/**
 * Entwurf für SV-Anmeldung (DEÜV) — vorbereiten, noch nicht absenden.
 */
final class SocialSecurityRegistrationDraft
{
    public const STATUS_NONE = 'none';
    public const STATUS_DRAFT = 'draft';
    public const STATUS_READY = 'ready';

    /**
     * draftStatusOptions.
     *
     * @return array<string, string>
     */
        public static function draftStatusOptions(): array
    {
        return [
            self::STATUS_NONE => 'Noch nicht vorbereitet',
            self::STATUS_DRAFT => 'Entwurf unvollständig',
            self::STATUS_READY => 'Entwurf bereit (nicht versendet)',
        ];
    }

    /**
     * @param array<string, string> $employeeData
     * @param array<string, string> $contactStamm login, first_name, last_name, etc.
     * @return array{ok: bool, missing: list<string>, warnings: list<string>, payload: array<string, mixed>}
     */
    public static function build(array $employeeData, array $contactStamm): array
    {
        $missing = [];
        $warnings = [];

        $firstName = trim($contactStamm['first_name'] ?? '');
        $lastName = trim($contactStamm['last_name'] ?? '');
        if ($firstName === '' && $lastName === '') {
            $missing[] = 'Vor- und Nachname (Stamm)';
        }
        if (trim($employeeData['birth_date'] ?? '') === '') {
            $missing[] = 'Geburtsdatum';
        }
        if (trim($employeeData['social_filing_office'] ?? '') === '') {
            $missing[] = 'Meldestelle (Einzug)';
        }
        if (trim($employeeData['entry_date'] ?? '') === '' && trim($employeeData['contract_start'] ?? '') === '') {
            $missing[] = 'Eintritt oder Vertragsbeginn';
        }
        if (trim($employeeData['employment_relationship'] ?? '') === '') {
            $missing[] = 'Beschäftigungsverhältnis';
        }

        $office = trim($employeeData['social_filing_office'] ?? '');
        if ($office === 'kk_employee' && trim($employeeData['health_insurance'] ?? '') === '') {
            $missing[] = 'Krankenkasse (für Meldung an KK)';
        }

        $street = trim($contactStamm['address1_street'] ?? '');
        $city = trim($contactStamm['address1_city'] ?? '');
        $postal = trim($contactStamm['address1_postal'] ?? '');
        if ($street === '' || $city === '' || $postal === '') {
            $warnings[] = 'Adresse unvollständig — für DEÜV in der Regel erforderlich';
        }
        if (trim($contactStamm['tax_number'] ?? '') === '' && trim($contactStamm['vat_id'] ?? '') === '') {
            $warnings[] = 'Steuernummer / USt-IdNr. des Arbeitgebers nicht im Kontakt-Stamm';
        }

        $svStatus = trim($employeeData['social_security_status'] ?? 'pending');
        if ($svStatus === 'received' && trim($employeeData['social_security_number'] ?? '') !== '') {
            $warnings[] = 'SV-Nummer ist bereits vorhanden — Anmeldung nur bei fehlender Nummer nötig';
        }

        $entryDate = trim($employeeData['entry_date'] ?? '') ?: trim($employeeData['contract_start'] ?? '');
        $gender = self::mapGender($employeeData['gender'] ?? '');

        $payload = [
            'type' => 'DEUEV_ANMELDUNG_ENTWURF',
            'version' => 1,
            'generated_at' => date('c'),
            'submission' => [
                'mode' => 'draft_only',
                'sent' => false,
                'note' => 'Nur Vorbereitung im CRM — keine Übermittlung an Krankenkasse/Knappschaft.',
            ],
            'meldestelle' => [
                'code' => $office,
                'label' => EmployeeData::socialFilingOfficeOptions()[$office] ?? $office,
            ],
            'arbeitnehmer' => [
                'vorname' => $firstName,
                'nachname' => $lastName,
                'geburtsdatum' => trim($employeeData['birth_date'] ?? ''),
                'geschlecht' => $gender,
                'strasse' => $street,
                'plz' => $postal,
                'ort' => $city,
                'land' => trim($contactStamm['address1_country'] ?? 'DE') ?: 'DE',
            ],
            'beschaeftigung' => [
                'verhaeltnis' => trim($employeeData['employment_relationship'] ?? ''),
                'eintritt' => $entryDate,
                'arbeitsort' => trim($employeeData['work_location'] ?? ''),
                'taetigkeit' => trim($employeeData['job_type'] ?? ''),
                'arbeitszeit' => trim($employeeData['working_hours'] ?? ''),
            ],
            'versicherung' => [
                'krankenkasse' => trim($employeeData['health_insurance'] ?? ''),
                'versichertennummer' => trim($employeeData['health_insurance_number'] ?? ''),
                'sv_nummer_bekannt' => trim($employeeData['social_security_number'] ?? ''),
                'sv_status' => $svStatus,
            ],
            'arbeitgeber' => [
                'firma' => trim($contactStamm['company_name'] ?? '') ?: trim($contactStamm['display_name'] ?? ''),
                'steuernummer' => trim($contactStamm['tax_number'] ?? ''),
                'ust_id' => trim($contactStamm['vat_id'] ?? ''),
            ],
        ];

        return [
            'ok' => $missing === [],
            'missing' => $missing,
            'warnings' => $warnings,
            'payload' => $payload,
        ];
    }

    /**
     * @param array<string, string> $employeeData
     * @param array<string, mixed> $result from build()
     * @return array<string, string>
     */
    public static function applyDraftResult(array $employeeData, array $result): array
    {
        $employeeData['social_registration_draft_at'] = date('c');
        $employeeData['social_registration_draft_json'] = json_encode(
            $result['payload'],
            JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT
        );
        if ($result['ok']) {
            $employeeData['social_registration_status'] = self::STATUS_READY;
            if (($employeeData['social_security_status'] ?? '') === 'pending') {
                $employeeData['social_security_status'] = 'requested';
            }
        } else {
            $employeeData['social_registration_status'] = self::STATUS_DRAFT;
        }

        return $employeeData;
    }

    /**
     * draftStatusLabel
     * @param string $status Statuswert
     * @return string
     */
    public static function draftStatusLabel(string $status): string
    {
        return self::draftStatusOptions()[$status] ?? $status;
    }

    /**
     * mapGender
     * @param string $code
     * @return string
     */
    private static function mapGender(string $code): string
    {
        return match ($code) {
            'm' => 'männlich',
            'w' => 'weiblich',
            'd' => 'divers',
            default => '',
        };
    }
}
