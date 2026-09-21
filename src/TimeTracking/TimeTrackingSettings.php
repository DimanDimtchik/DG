<?php
declare(strict_types=1);

/** Einstellungen Zeiterfassung (Pausenregeln, Auto-Pause, ArbZG, Rückstellungen Z5b). */
final class TimeTrackingSettings
{
    public const STORE_KEY = 'time_tracking';

    /**
     * @return array<string, mixed>
     */
    public static function defaults(): array
    {
        return [
            'auto_break_enabled' => true,
            'force_break_before_clock_out' => true,
            'auto_close_open_days' => true,
            'break_after_6h_minutes' => 30,
            'break_after_9h_minutes' => 45,
            'break_threshold_6h_minutes' => 360,
            'break_threshold_9h_minutes' => 540,
            'overtime_compensation_months' => 6,
            'arbzg_max_weekly_hours' => 48,
            'overtime_reminder_enabled' => true,
            'overtime_reminder_email' => true,
            // Z5b Rückstellungen (Konten = Vorschlag — mit Steuerberater prüfen)
            'provision_daily_cost' => 0.0,
            'provision_cost_method' => 'workdays_260',
            'provision_social_factor' => 1.0,
            'provision_ot_enabled' => false,
            'provision_account_vacation_expense' => '6140',
            'provision_account_vacation_liability' => '0970',
            'provision_account_ot_expense' => '6140',
            'provision_account_ot_liability' => '0970',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function forForm(): array
    {
        $stored = SettingsStore::get(self::STORE_KEY, self::defaults());
        $defaults = self::defaults();
        $method = (string) ($stored['provision_cost_method'] ?? $defaults['provision_cost_method']);
        if (!in_array($method, ['workdays_260', 'calendar_365'], true)) {
            $method = 'workdays_260';
        }

        return [
            'auto_break_enabled' => !empty($stored['auto_break_enabled'] ?? $defaults['auto_break_enabled']),
            'force_break_before_clock_out' => !empty($stored['force_break_before_clock_out'] ?? $defaults['force_break_before_clock_out']),
            'auto_close_open_days' => !empty($stored['auto_close_open_days'] ?? $defaults['auto_close_open_days']),
            'break_after_6h_minutes' => max(0, (int) ($stored['break_after_6h_minutes'] ?? $defaults['break_after_6h_minutes'])),
            'break_after_9h_minutes' => max(0, (int) ($stored['break_after_9h_minutes'] ?? $defaults['break_after_9h_minutes'])),
            'break_threshold_6h_minutes' => max(60, (int) ($stored['break_threshold_6h_minutes'] ?? $defaults['break_threshold_6h_minutes'])),
            'break_threshold_9h_minutes' => max(60, (int) ($stored['break_threshold_9h_minutes'] ?? $defaults['break_threshold_9h_minutes'])),
            'overtime_compensation_months' => max(1, (int) ($stored['overtime_compensation_months'] ?? $defaults['overtime_compensation_months'])),
            'arbzg_max_weekly_hours' => max(1, min(168, (int) ($stored['arbzg_max_weekly_hours'] ?? $defaults['arbzg_max_weekly_hours']))),
            'overtime_reminder_enabled' => !empty($stored['overtime_reminder_enabled'] ?? $defaults['overtime_reminder_enabled']),
            'overtime_reminder_email' => !empty($stored['overtime_reminder_email'] ?? $defaults['overtime_reminder_email']),
            'provision_daily_cost' => self::normalizeMoney($stored['provision_daily_cost'] ?? $defaults['provision_daily_cost']),
            'provision_cost_method' => $method,
            'provision_social_factor' => self::normalizeFactor($stored['provision_social_factor'] ?? $defaults['provision_social_factor']),
            'provision_ot_enabled' => !empty($stored['provision_ot_enabled'] ?? $defaults['provision_ot_enabled']),
            'provision_account_vacation_expense' => self::normalizeAccount(
                (string) ($stored['provision_account_vacation_expense'] ?? $defaults['provision_account_vacation_expense'])
            ),
            'provision_account_vacation_liability' => self::normalizeAccount(
                (string) ($stored['provision_account_vacation_liability'] ?? $defaults['provision_account_vacation_liability'])
            ),
            'provision_account_ot_expense' => self::normalizeAccount(
                (string) ($stored['provision_account_ot_expense'] ?? $defaults['provision_account_ot_expense'])
            ),
            'provision_account_ot_liability' => self::normalizeAccount(
                (string) ($stored['provision_account_ot_liability'] ?? $defaults['provision_account_ot_liability'])
            ),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function config(): array
    {
        return self::forForm();
    }

    /**
     * @param array<string, mixed> $input
     */
    public static function saveFromPost(array $input): void
    {
        $method = (string) ($input['provision_cost_method'] ?? 'workdays_260');
        if (!in_array($method, ['workdays_260', 'calendar_365'], true)) {
            $method = 'workdays_260';
        }

        SettingsStore::set(self::STORE_KEY, [
            'auto_break_enabled' => !empty($input['auto_break_enabled']),
            'force_break_before_clock_out' => !empty($input['force_break_before_clock_out']),
            'auto_close_open_days' => !empty($input['auto_close_open_days']),
            'break_after_6h_minutes' => max(0, (int) ($input['break_after_6h_minutes'] ?? 30)),
            'break_after_9h_minutes' => max(0, (int) ($input['break_after_9h_minutes'] ?? 45)),
            'break_threshold_6h_minutes' => max(60, (int) ($input['break_threshold_6h_minutes'] ?? 360)),
            'break_threshold_9h_minutes' => max(60, (int) ($input['break_threshold_9h_minutes'] ?? 540)),
            'overtime_compensation_months' => max(1, (int) ($input['overtime_compensation_months'] ?? 6)),
            'arbzg_max_weekly_hours' => max(1, min(168, (int) ($input['arbzg_max_weekly_hours'] ?? 48))),
            'overtime_reminder_enabled' => !empty($input['overtime_reminder_enabled']),
            'overtime_reminder_email' => !empty($input['overtime_reminder_email']),
            'provision_daily_cost' => self::normalizeMoney($input['provision_daily_cost'] ?? 0),
            'provision_cost_method' => $method,
            'provision_social_factor' => self::normalizeFactor($input['provision_social_factor'] ?? 1),
            'provision_ot_enabled' => !empty($input['provision_ot_enabled']),
            'provision_account_vacation_expense' => self::normalizeAccount(
                (string) ($input['provision_account_vacation_expense'] ?? '6140')
            ),
            'provision_account_vacation_liability' => self::normalizeAccount(
                (string) ($input['provision_account_vacation_liability'] ?? '0970')
            ),
            'provision_account_ot_expense' => self::normalizeAccount(
                (string) ($input['provision_account_ot_expense'] ?? '6140')
            ),
            'provision_account_ot_liability' => self::normalizeAccount(
                (string) ($input['provision_account_ot_liability'] ?? '0970')
            ),
        ]);
    }

    private static function normalizeMoney(mixed $raw): float
    {
        if (is_string($raw)) {
            $raw = str_replace(',', '.', trim($raw));
        }
        $v = round((float) $raw, 2);

        return max(0.0, min(99999.99, $v));
    }

    private static function normalizeFactor(mixed $raw): float
    {
        if (is_string($raw)) {
            $raw = str_replace(',', '.', trim($raw));
        }
        $v = round((float) $raw, 4);

        return max(0.5, min(3.0, $v > 0 ? $v : 1.0));
    }

    private static function normalizeAccount(string $raw): string
    {
        $raw = trim($raw);
        if ($raw === '') {
            return '';
        }
        if (function_exists('mb_substr')) {
            return mb_substr($raw, 0, 20);
        }

        return substr($raw, 0, 20);
    }
}
