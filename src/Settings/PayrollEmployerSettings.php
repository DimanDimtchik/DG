<?php
declare(strict_types=1);

/**
 * Lohn L1b: Arbeitgeber-Lohnfelder unter company_extended.lohn.
 * Keine UI (L1c). Keine Baulohn-/ITSG-Logik — nur Flag/Stammdaten.
 */
final class PayrollEmployerSettings
{
    public const AUDIT_ACTION = 'payroll_employer_lohn';

    /**
     * ISO-3166-2 DE Länderkürzel (Allowlist L1a).
     *
     * @return array<string, string> code => Kurzname
     */
    public static function bundeslandLabels(): array
    {
        return [
            'DE-BW' => 'Baden-Württemberg',
            'DE-BY' => 'Bayern',
            'DE-BE' => 'Berlin',
            'DE-BB' => 'Brandenburg',
            'DE-HB' => 'Bremen',
            'DE-HH' => 'Hamburg',
            'DE-HE' => 'Hessen',
            'DE-MV' => 'Mecklenburg-Vorpommern',
            'DE-NI' => 'Niedersachsen',
            'DE-NW' => 'Nordrhein-Westfalen',
            'DE-RP' => 'Rheinland-Pfalz',
            'DE-SL' => 'Saarland',
            'DE-SN' => 'Sachsen',
            'DE-ST' => 'Sachsen-Anhalt',
            'DE-SH' => 'Schleswig-Holstein',
            'DE-TH' => 'Thüringen',
        ];
    }

    /**
     * @return array{
     *   bundesland: string,
     *   umlage_u1: bool,
     *   umlage_u2: bool,
     *   umlage_u3: bool,
     *   u1_erstattungssatz_key: string,
     *   payroll_bank_account_index: int,
     *   baulohn_enabled: bool,
     *   notes: string
     * }
     */
    public static function lohnDefaults(): array
    {
        return [
            'bundesland' => '',
            'umlage_u1' => false,
            'umlage_u2' => false,
            'umlage_u3' => false,
            'u1_erstattungssatz_key' => '',
            'payroll_bank_account_index' => 0,
            'baulohn_enabled' => false,
            'notes' => '',
        ];
    }

    /**
     * @param array<string, mixed> $raw
     * @return array{
     *   bundesland: string,
     *   umlage_u1: bool,
     *   umlage_u2: bool,
     *   umlage_u3: bool,
     *   u1_erstattungssatz_key: string,
     *   payroll_bank_account_index: int,
     *   baulohn_enabled: bool,
     *   notes: string
     * }
     */
    public static function normalizeLohn(array $raw): array
    {
        $defaults = self::lohnDefaults();
        $bundesland = strtoupper(trim((string) ($raw['bundesland'] ?? '')));
        if ($bundesland !== '' && !isset(self::bundeslandLabels()[$bundesland])) {
            $bundesland = '';
        }

        $u1Key = trim((string) ($raw['u1_erstattungssatz_key'] ?? ''));
        $u1Key = mb_substr(preg_replace('/\s+/u', ' ', $u1Key) ?? '', 0, 64);

        $notes = trim((string) ($raw['notes'] ?? ''));
        $notes = mb_substr($notes, 0, 2000);

        return [
            'bundesland' => $bundesland,
            'umlage_u1' => self::toBool($raw['umlage_u1'] ?? $defaults['umlage_u1']),
            'umlage_u2' => self::toBool($raw['umlage_u2'] ?? $defaults['umlage_u2']),
            'umlage_u3' => self::toBool($raw['umlage_u3'] ?? $defaults['umlage_u3']),
            'u1_erstattungssatz_key' => $u1Key,
            'payroll_bank_account_index' => max(0, (int) ($raw['payroll_bank_account_index'] ?? 0)),
            'baulohn_enabled' => self::toBool($raw['baulohn_enabled'] ?? false),
            'notes' => $notes,
        ];
    }

    /**
     * Formular-/API-Input → normalisierte Lohn-Felder (gleiche Regeln wie normalize).
     *
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public static function sanitizeLohn(array $input): array
    {
        return self::normalizeLohn($input);
    }

    /**
     * Nur der Nested-Block company_extended.lohn.
     *
     * @return array<string, mixed>
     */
    public static function lohnConfig(): array
    {
        $ext = CompanyExtendedSettings::config();

        return self::normalizeLohn(is_array($ext['lohn'] ?? null) ? $ext['lohn'] : []);
    }

    /**
     * Lesende Gesamtansicht A-Felder (Firma) + B-Felder (lohn) für spätere Läufe.
     * Schreibt nichts; Baulohn-Flag ohne Rechenkern.
     *
     * @return array<string, mixed>
     */
    public static function employerSnapshot(): array
    {
        $basic = CompanySettings::config();
        $ext = CompanyExtendedSettings::config();
        $lohn = self::normalizeLohn(is_array($ext['lohn'] ?? null) ? $ext['lohn'] : []);

        $legal = trim((string) ($ext['legal_name'] ?? ''));
        $display = trim((string) ($basic['name'] ?? ''));
        $employerName = $legal !== '' ? $legal : $display;

        $est = trim((string) ($ext['tax_numbers']['est'] ?? ''));
        if ($est === '') {
            $est = trim((string) ($basic['tax_number'] ?? ''));
        }

        $finanzamt = self::resolveFinanzamtSummary($ext);
        $bankIndex = (int) $lohn['payroll_bank_account_index'];
        $banks = is_array($ext['bank_accounts'] ?? null) ? $ext['bank_accounts'] : [];
        $bank = isset($banks[$bankIndex]) && is_array($banks[$bankIndex])
            ? $banks[$bankIndex]
            : ($banks[0] ?? null);

        return [
            'employer_name' => $employerName,
            'legal_name' => $legal,
            'display_name' => $display,
            'address' => [
                'street' => (string) ($basic['street'] ?? ''),
                'postal' => (string) ($basic['postal'] ?? ''),
                'city' => (string) ($basic['city'] ?? ''),
                'country' => (string) (($basic['country'] ?? '') !== '' ? $basic['country'] : 'DE'),
            ],
            'tax_number_est' => $est,
            'finanzamt' => $finanzamt,
            'betriebsnummer' => trim((string) ($ext['employment_agency']['betriebsnummer'] ?? '')),
            'bg_data' => is_array($ext['bg_data'] ?? null) ? $ext['bg_data'] : [],
            'payroll_bank' => is_array($bank) ? $bank : null,
            'lohn' => $lohn,
        ];
    }

    /**
     * Speichert nur company_extended.lohn; übrige Firmendaten unverändert.
     * Audit: Feld-Diff wer/wann über AuditLog (action payroll_employer_lohn).
     *
     * @param array<string, mixed> $input
     * @return list<array{field: string, old: mixed, new: mixed}>
     */
    public static function saveLohn(array $input, ?int $userId = null): array
    {
        $stored = SettingsStore::get(CompanyExtendedSettings::STORE_KEY, CompanyExtendedSettings::defaults());
        if (!is_array($stored)) {
            $stored = CompanyExtendedSettings::defaults();
        }

        $before = self::normalizeLohn(is_array($stored['lohn'] ?? null) ? $stored['lohn'] : []);
        $after = self::sanitizeLohn($input);
        $changes = self::diffLohn($before, $after);
        if ($changes === []) {
            return [];
        }

        $stored['lohn'] = $after;
        SettingsStore::set(CompanyExtendedSettings::STORE_KEY, $stored);

        $details = json_encode([
            'changes' => $changes,
            'lohn' => $after,
        ], JSON_UNESCAPED_UNICODE);
        if (class_exists('AuditLog')) {
            AuditLog::record(self::AUDIT_ACTION, $userId, $details !== false ? $details : null);
        }

        // Zusätzlich Firmenhistorie anstoßen (Fingerprint kann lohn-only ignorieren — AuditLog ist führend).
        if (class_exists('CompanyMasterHistoryRepository')) {
            CompanyMasterHistoryRepository::recordFromCurrentSettings(
                $userId,
                null,
                'Änderung Lohn-AG-Stamm (company_extended.lohn)'
            );
        }

        return $changes;
    }

    /**
     * @param array<string, mixed> $before
     * @param array<string, mixed> $after
     * @return list<array{field: string, old: mixed, new: mixed}>
     */
    public static function diffLohn(array $before, array $after): array
    {
        $before = self::normalizeLohn($before);
        $after = self::normalizeLohn($after);
        $out = [];
        foreach (array_keys(self::lohnDefaults()) as $field) {
            $old = $before[$field] ?? null;
            $new = $after[$field] ?? null;
            if ($old !== $new) {
                $out[] = ['field' => $field, 'old' => $old, 'new' => $new];
            }
        }

        return $out;
    }

    /**
     * @param array<string, mixed> $ext
     * @return array{bufo_nr: string, name: string}
     */
    private static function resolveFinanzamtSummary(array $ext): array
    {
        $resolved = is_array($ext['finanzamt_resolved'] ?? null) ? $ext['finanzamt_resolved'] : [];
        $office = is_array($resolved['office'] ?? null) ? $resolved['office'] : [];
        $name = trim((string) ($office['name'] ?? ''));
        $bufo = trim((string) ($resolved['bufo_nr'] ?? $office['bufo_nr'] ?? ''));
        if ($name !== '' || $bufo !== '') {
            return ['bufo_nr' => $bufo, 'name' => $name];
        }

        $list = is_array($ext['finanzaemter'] ?? null) ? $ext['finanzaemter'] : [];
        foreach ($list as $row) {
            if (!is_array($row)) {
                continue;
            }
            $n = trim((string) ($row['name'] ?? ''));
            $b = trim((string) ($row['bufo_nr'] ?? ''));
            if ($n !== '' || $b !== '') {
                return ['bufo_nr' => $b, 'name' => $n];
            }
        }

        return ['bufo_nr' => '', 'name' => ''];
    }

    private static function toBool(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }
        if (is_int($value) || is_float($value)) {
            return ((int) $value) === 1;
        }
        $s = strtolower(trim((string) $value));

        return in_array($s, ['1', 'true', 'yes', 'on', 'ja'], true);
    }
}
