<?php
declare(strict_types=1);

final class CalendarAppearanceSettings
{
    public const STORE_KEY = 'calendar_colors';

    /** @return array<string, string> */
    public static function defaults(): array
    {
        return CalendarColorPresets::defaultColors();
    }

    /** @return array<string, string> */
    public static function config(): array
    {
        return SettingsStore::get(self::STORE_KEY, self::defaults());
    }

    /** @return array<string, string> */
    public static function forForm(): array
    {
        $colors = self::config();
        $normalized = [];
        foreach (CalendarColorPresets::colorKeys() as $key) {
            $normalized[$key] = CalendarFrontendTheme::sanitizeHex(
                (string) ($colors[$key] ?? ''),
                CalendarColorPresets::defaultColors()[$key]
            );
        }

        return $normalized;
    }

    /** @param array<string, mixed> $input */
    public static function save(array $input): void
    {
        $defaults = self::defaults();
        $existing = self::config();
        $colors = [];

        foreach (CalendarColorPresets::colorKeys() as $key) {
            $raw = $input[$key] ?? ($existing[$key] ?? $defaults[$key]);
            $colors[$key] = CalendarFrontendTheme::sanitizeHex((string) $raw, $defaults[$key]);
        }

        SettingsStore::set(self::STORE_KEY, $colors);
    }

    /** @return array<string, array{label: string, hint: string}> */
    public static function fieldDefinitions(): array
    {
        return [
            'primary_color' => [
                'label' => 'Primärfarbe (Buttons)',
                'hint' => 'Farbe für Buttons und aktive Elemente im Buchungskalender',
            ],
            'button_hover' => [
                'label' => 'Button Hover-Farbe',
                'hint' => 'Farbe beim Hover über Buttons',
            ],
            'slot_bg' => [
                'label' => 'Termin-Slot Hintergrund',
                'hint' => 'Hintergrundfarbe für verfügbare Termine',
            ],
            'slot_hover' => [
                'label' => 'Termin-Slot Hover',
                'hint' => 'Hover-Farbe für verfügbare Termine',
            ],
            'slot_selected_bg' => [
                'label' => 'Ausgewählter Termin Hintergrund',
                'hint' => 'Hintergrundfarbe für den gewählten Termin',
            ],
            'slot_selected_border' => [
                'label' => 'Ausgewählter Termin Rahmen',
                'hint' => 'Rahmenfarbe für den gewählten Termin',
            ],
            'booked_bg' => [
                'label' => 'Gebuchter Termin Hintergrund',
                'hint' => 'Hintergrundfarbe für bereits gebuchte Termine',
            ],
        ];
    }
}
