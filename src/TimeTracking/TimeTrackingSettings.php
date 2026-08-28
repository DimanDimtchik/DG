<?php
declare(strict_types=1);

/** Einstellungen Zeiterfassung (Pausenregeln, Auto-Pause). */
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
            'overtime_reminder_after_months' => 5,
            'overtime_reminder_enabled' => true,
            'overtime_reminder_email' => true,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function forForm(): array
    {
        $stored = SettingsStore::get(self::STORE_KEY, self::defaults());
        $defaults = self::defaults();

        $compensationMonths = max(1, (int) ($stored['overtime_compensation_months'] ?? $defaults['overtime_compensation_months']));
        $reminderAfterMonths = max(1, (int) ($stored['overtime_reminder_after_months'] ?? $defaults['overtime_reminder_after_months']));
        if ($reminderAfterMonths >= $compensationMonths) {
            $reminderAfterMonths = max(1, $compensationMonths - 1);
        }

        return [
            'auto_break_enabled' => !empty($stored['auto_break_enabled'] ?? $defaults['auto_break_enabled']),
            'force_break_before_clock_out' => !empty($stored['force_break_before_clock_out'] ?? $defaults['force_break_before_clock_out']),
            'auto_close_open_days' => !empty($stored['auto_close_open_days'] ?? $defaults['auto_close_open_days']),
            'break_after_6h_minutes' => max(0, (int) ($stored['break_after_6h_minutes'] ?? $defaults['break_after_6h_minutes'])),
            'break_after_9h_minutes' => max(0, (int) ($stored['break_after_9h_minutes'] ?? $defaults['break_after_9h_minutes'])),
            'break_threshold_6h_minutes' => max(60, (int) ($stored['break_threshold_6h_minutes'] ?? $defaults['break_threshold_6h_minutes'])),
            'break_threshold_9h_minutes' => max(60, (int) ($stored['break_threshold_9h_minutes'] ?? $defaults['break_threshold_9h_minutes'])),
            'overtime_compensation_months' => $compensationMonths,
            'overtime_reminder_after_months' => $reminderAfterMonths,
            'overtime_reminder_enabled' => !empty($stored['overtime_reminder_enabled'] ?? $defaults['overtime_reminder_enabled']),
            'overtime_reminder_email' => !empty($stored['overtime_reminder_email'] ?? $defaults['overtime_reminder_email']),
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
        $compensationMonths = max(1, (int) ($input['overtime_compensation_months'] ?? 6));
        $reminderAfterMonths = max(1, (int) ($input['overtime_reminder_after_months'] ?? 5));
        if ($reminderAfterMonths >= $compensationMonths) {
            $reminderAfterMonths = max(1, $compensationMonths - 1);
        }

        SettingsStore::set(self::STORE_KEY, [
            'auto_break_enabled' => !empty($input['auto_break_enabled']),
            'force_break_before_clock_out' => !empty($input['force_break_before_clock_out']),
            'auto_close_open_days' => !empty($input['auto_close_open_days']),
            'break_after_6h_minutes' => max(0, (int) ($input['break_after_6h_minutes'] ?? 30)),
            'break_after_9h_minutes' => max(0, (int) ($input['break_after_9h_minutes'] ?? 45)),
            'break_threshold_6h_minutes' => max(60, (int) ($input['break_threshold_6h_minutes'] ?? 360)),
            'break_threshold_9h_minutes' => max(60, (int) ($input['break_threshold_9h_minutes'] ?? 540)),
            'overtime_compensation_months' => $compensationMonths,
            'overtime_reminder_after_months' => $reminderAfterMonths,
            'overtime_reminder_enabled' => !empty($input['overtime_reminder_enabled']),
            'overtime_reminder_email' => !empty($input['overtime_reminder_email']),
        ]);
    }
}
