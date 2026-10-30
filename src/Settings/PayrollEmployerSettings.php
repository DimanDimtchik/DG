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
            'payroll_fibu_live' => false,
            'payroll_sepa_live' => false,
            'payroll_lsta_live' => false,
            'fibu_accounts' => [],
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
            'payroll_fibu_live' => self::toBool($raw['payroll_fibu_live'] ?? false),
            'payroll_sepa_live' => self::toBool($raw['payroll_sepa_live'] ?? false),
            'payroll_lsta_live' => self::toBool($raw['payroll_lsta_live'] ?? false),
            'fibu_accounts' => self::normalizeFibuAccounts($raw['fibu_accounts'] ?? []),
            'notes' => $notes,
        ];
    }

    /**
     * Konten-Overrides für L7 (Rollen → Kontonummer, nur Ziffern).
     *
     * @param mixed $raw
     * @return array<string, string>
     */
    public static function normalizeFibuAccounts(mixed $raw): array
    {
        if (!is_array($raw)) {
            return [];
        }
        $roles = [
            'expense_wages',
            'expense_social',
            'liability_net',
            'liability_tax',
            'liability_sv',
        ];
        $out = [];
        foreach ($roles as $role) {
            $num = preg_replace('/\D/', '', (string) ($raw[$role] ?? '')) ?? '';
            $num = mb_substr($num, 0, 16);
            if ($num !== '') {
                $out[$role] = $num;
            }
        }

        return $out;
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
     * Formular-Daten für Settings-Tab L1c.
     *
     * @return array{
     *   lohn: array<string, mixed>,
     *   snapshot: array<string, mixed>,
     *   warnings: list<string>,
     *   bundesland_labels: array<string, string>,
     *   bank_options: list<array{index: int, label: string}>
     * }
     */
    public static function forForm(): array
    {
        $snapshot = self::employerSnapshot();
        $ext = CompanyExtendedSettings::config();
        $banks = is_array($ext['bank_accounts'] ?? null) ? $ext['bank_accounts'] : [];
        $bankOptions = [];
        foreach ($banks as $i => $row) {
            if (!is_array($row)) {
                continue;
            }
            $iban = trim((string) ($row['iban'] ?? ''));
            $label = trim((string) ($row['label'] ?? ''));
            $holder = trim((string) ($row['account_holder'] ?? ''));
            $parts = array_filter([$label !== '' ? $label : null, $holder !== '' ? $holder : null, $iban !== '' ? $iban : 'ohne IBAN']);
            $bankOptions[] = [
                'index' => (int) $i,
                'label' => implode(' · ', $parts),
            ];
        }
        if ($bankOptions === []) {
            $bankOptions[] = ['index' => 0, 'label' => 'Kein Bankkonto hinterlegt (Firmendaten)'];
        }

        return [
            'lohn' => self::lohnConfig(),
            'snapshot' => $snapshot,
            'warnings' => self::collectWarnings($snapshot),
            'bundesland_labels' => self::bundeslandLabels(),
            'bank_options' => $bankOptions,
        ];
    }

    /**
     * Speichert Lohn-Felder aus Settings-POST (nur Nested lohn.* / flache Keys).
     *
     * @param array<string, mixed> $post
     * @return list<array{field: string, old: mixed, new: mixed}>
     */
    public static function saveFromPost(array $post, ?int $userId = null): array
    {
        $raw = is_array($post['lohn'] ?? null) ? $post['lohn'] : $post;

        return self::saveLohn($raw, $userId);
    }

    /**
     * Plausibilitäts-Hinweise (Speichern der Lohn-Felder bleibt erlaubt).
     *
     * @param array<string, mixed> $snapshot
     * @return list<string>
     */
    public static function collectWarnings(array $snapshot): array
    {
        $warnings = [];
        $bnRaw = trim((string) ($snapshot['betriebsnummer'] ?? ''));
        $bnDigits = preg_replace('/\D+/', '', $bnRaw) ?? '';
        if ($bnDigits === '') {
            $warnings[] = 'Betriebsnummer fehlt (Firmendaten → Agentur für Arbeit). Monatslauf später blockiert.';
        } elseif (strlen($bnDigits) !== 8) {
            $warnings[] = 'Betriebsnummer sollte 8 Ziffern haben (aktuell: ' . strlen($bnDigits) . '). Speichern der Lohn-Felder ist erlaubt.';
        }

        if (trim((string) ($snapshot['tax_number_est'] ?? '')) === '') {
            $warnings[] = 'Steuernummer (ESt/LSt) fehlt unter Firmendaten.';
        }

        $fa = is_array($snapshot['finanzamt'] ?? null) ? $snapshot['finanzamt'] : [];
        if (trim((string) ($fa['name'] ?? '')) === '' && trim((string) ($fa['bufo_nr'] ?? '')) === '') {
            $warnings[] = 'Kein Finanzamt hinterlegt (Firmendaten).';
        }

        $lohn = is_array($snapshot['lohn'] ?? null) ? $snapshot['lohn'] : [];
        if (trim((string) ($lohn['bundesland'] ?? '')) === '') {
            $warnings[] = 'Bundesland für Lohn noch nicht gesetzt (KiSt/LSt-Bezug).';
        }

        $bank = $snapshot['payroll_bank'] ?? null;
        $iban = is_array($bank) ? trim((string) ($bank['iban'] ?? '')) : '';
        if ($iban === '') {
            $warnings[] = 'SEPA-Absenderkonto ohne IBAN — Auszahlung später blockiert.';
        }

        if (trim((string) ($snapshot['employer_name'] ?? '')) === '') {
            $warnings[] = 'Firmenname fehlt.';
        }

        return $warnings;
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
