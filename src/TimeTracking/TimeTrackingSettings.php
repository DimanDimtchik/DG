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
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function forForm(): array
    {
        $stored = SettingsStore::get(self::STORE_KEY, self::defaults());
        $defaults = self::defaults();

        return [
            'auto_break_enabled' => !empty($stored['auto_break_enabled'] ?? $defaults['auto_break_enabled']),
            'force_break_before_clock_out' => !empty($stored['force_break_before_clock_out'] ?? $defaults['force_break_before_clock_out']),
            'auto_close_open_days' => !empty($stored['auto_close_open_days'] ?? $defaults['auto_close_open_days']),
            'break_after_6h_minutes' => max(0, (int) ($stored['break_after_6h_minutes'] ?? $defaults['break_after_6h_minutes'])),
            'break_after_9h_minutes' => max(0, (int) ($stored['break_after_9h_minutes'] ?? $defaults['break_after_9h_minutes'])),
            'break_threshold_6h_minutes' => max(60, (int) ($stored['break_threshold_6h_minutes'] ?? $defaults['break_threshold_6h_minutes'])),
            'break_threshold_9h_minutes' => max(60, (int) ($stored['break_threshold_9h_minutes'] ?? $defaults['break_threshold_9h_minutes'])),
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
        SettingsStore::set(self::STORE_KEY, [
            'auto_break_enabled' => !empty($input['auto_break_enabled']),
            'force_break_before_clock_out' => !empty($input['force_break_before_clock_out']),
            'auto_close_open_days' => !empty($input['auto_close_open_days']),
            'break_after_6h_minutes' => max(0, (int) ($input['break_after_6h_minutes'] ?? 30)),
            'break_after_9h_minutes' => max(0, (int) ($input['break_after_9h_minutes'] ?? 45)),
            'break_threshold_6h_minutes' => max(60, (int) ($input['break_threshold_6h_minutes'] ?? 360)),
            'break_threshold_9h_minutes' => max(60, (int) ($input['break_threshold_9h_minutes'] ?? 540)),
        ]);
    }
}
