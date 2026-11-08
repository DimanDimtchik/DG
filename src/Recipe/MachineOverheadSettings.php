<?php
declare(strict_types=1);

/**
 * Gemeinkosten-Aufschlag Maschinenstunde — SettingsStore `machine_overhead` (E1a).
 * Nicht buchungswirksam.
 */
final class MachineOverheadSettings
{
    public const STORE_KEY = 'machine_overhead';

    public const PERIOD_FISCAL_YEAR = 'fiscal_year';
    public const PERIOD_DATE_RANGE = 'date_range';

    /**
     * @return array{
     *   enabled: bool,
     *   period_mode: string,
     *   fiscal_year: int,
     *   date_from: string,
     *   date_to: string,
     *   account_numbers: list<string>,
     *   manual_machine_hours: ?float,
     *   label: string
     * }
     */
    public static function defaults(): array
    {
        return [
            'enabled' => false,
            'period_mode' => self::PERIOD_FISCAL_YEAR,
            'fiscal_year' => (int) date('Y'),
            'date_from' => '',
            'date_to' => '',
            'account_numbers' => [],
            'manual_machine_hours' => null,
            'label' => '',
        ];
    }

    /**
     * @return array{
     *   enabled: bool,
     *   period_mode: string,
     *   fiscal_year: int,
     *   date_from: string,
     *   date_to: string,
     *   account_numbers: list<string>,
     *   manual_machine_hours: ?float,
     *   label: string
     * }
     */
    public static function get(): array
    {
        $defaults = self::defaults();
        if (!Database::isConfigured()) {
            return $defaults;
        }
        $stored = SettingsStore::get(self::STORE_KEY, $defaults);

        return self::normalize($stored);
    }

    /**
     * @param array<string, mixed> $raw
     * @return array{
     *   enabled: bool,
     *   period_mode: string,
     *   fiscal_year: int,
     *   date_from: string,
     *   date_to: string,
     *   account_numbers: list<string>,
     *   manual_machine_hours: ?float,
     *   label: string
     * }
     */
    public static function normalize(array $raw): array
    {
        $defaults = self::defaults();
        $mode = trim((string) ($raw['period_mode'] ?? $defaults['period_mode']));
        if ($mode !== self::PERIOD_DATE_RANGE) {
            $mode = self::PERIOD_FISCAL_YEAR;
        }
        $year = (int) ($raw['fiscal_year'] ?? $defaults['fiscal_year']);
        if ($year < 2000 || $year > 2100) {
            $year = (int) date('Y');
        }
        $from = trim((string) ($raw['date_from'] ?? ''));
        $to = trim((string) ($raw['date_to'] ?? ''));
        if ($from !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) {
            $from = '';
        }
        if ($to !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {
            $to = '';
        }

        $accounts = [];
        $rawAccounts = $raw['account_numbers'] ?? [];
        if (is_string($rawAccounts)) {
            $rawAccounts = preg_split('/[\s,;]+/', $rawAccounts) ?: [];
        }
        if (is_array($rawAccounts)) {
            foreach ($rawAccounts as $acc) {
                $n = preg_replace('/\D+/', '', (string) $acc) ?? '';
                if ($n !== '' && !in_array($n, $accounts, true)) {
                    $accounts[] = $n;
                }
            }
        }

        $manual = $raw['manual_machine_hours'] ?? null;
        $manualHours = null;
        if ($manual !== null && $manual !== '') {
            if (is_int($manual) || is_float($manual)) {
                $manualHours = max(0.0, (float) $manual);
            } else {
                $s = trim(str_replace([' ', ','], ['', '.'], (string) $manual));
                if (is_numeric($s)) {
                    $manualHours = max(0.0, (float) $s);
                }
            }
            if ($manualHours !== null && $manualHours < 0.0005) {
                $manualHours = null;
            }
        }

        return [
            'enabled' => !empty($raw['enabled']),
            'period_mode' => $mode,
            'fiscal_year' => $year,
            'date_from' => $from,
            'date_to' => $to,
            'account_numbers' => $accounts,
            'manual_machine_hours' => $manualHours,
            'label' => mb_substr(trim((string) ($raw['label'] ?? '')), 0, 191),
        ];
    }

    /**
     * @param array<string, mixed> $post
     */
    public static function saveFromPost(array $post): void
    {
        $accountsRaw = (string) ($post['account_numbers'] ?? '');
        $cfg = self::normalize([
            'enabled' => !empty($post['enabled']),
            'period_mode' => (string) ($post['period_mode'] ?? self::PERIOD_FISCAL_YEAR),
            'fiscal_year' => (int) ($post['fiscal_year'] ?? date('Y')),
            'date_from' => (string) ($post['date_from'] ?? ''),
            'date_to' => (string) ($post['date_to'] ?? ''),
            'account_numbers' => $accountsRaw,
            'manual_machine_hours' => $post['manual_machine_hours'] ?? null,
            'label' => (string) ($post['label'] ?? ''),
        ]);
        if ($cfg['period_mode'] === self::PERIOD_DATE_RANGE) {
            if ($cfg['date_from'] === '' || $cfg['date_to'] === '') {
                throw new InvalidArgumentException('Zeitraum: Von- und Bis-Datum erforderlich.');
            }
            if ($cfg['date_from'] > $cfg['date_to']) {
                throw new InvalidArgumentException('Zeitraum: Von-Datum darf nicht nach Bis-Datum liegen.');
            }
            $cfg['fiscal_year'] = (int) substr($cfg['date_from'], 0, 4);
        }
        SettingsStore::set(self::STORE_KEY, $cfg);
    }
}
