<?php
declare(strict_types=1);

/**
 * Calendar Color Presets.
 */
final class CalendarColorPresets
{
    /**
     * Liefert vordefinierte Presets.
     * @return array<string, array<string, string>>
     */
    public static function presets(): array
    {
        return [
            'kaffee_braun' => [
                'name' => 'Kaffee Braun',
                'desc' => 'Passend zum Software Design (Kaffee Braun)',
                'colors' => [
                    'primary_color' => '#6e6258',
                    'button_hover' => '#5a5048',
                    'slot_bg' => '#fdfcfb',
                    'slot_hover' => '#efe9e4',
                    'slot_selected_bg' => '#ebe4dc',
                    'slot_selected_border' => '#6e6258',
                    'booked_bg' => '#f5f3f1',
                ],
            ],
            'classic_blue' => [
                'name' => 'Klassisch Blau',
                'desc' => 'Klar und vertrauensvoll',
                'colors' => [
                    'primary_color' => '#0a74da',
                    'button_hover' => '#0959a3',
                    'slot_bg' => '#fdfdfd',
                    'slot_hover' => '#f0f0f0',
                    'slot_selected_bg' => '#e6f0ff',
                    'slot_selected_border' => '#0a74da',
                    'booked_bg' => '#f8f8f8',
                ],
            ],
            'rose_beauty' => [
                'name' => 'Rosé & Beauty',
                'desc' => 'Warm und einladend – ideal fürs Kosmetikstudio',
                'colors' => [
                    'primary_color' => '#c45b7a',
                    'button_hover' => '#a84864',
                    'slot_bg' => '#fffbfb',
                    'slot_hover' => '#fce8ee',
                    'slot_selected_bg' => '#fdeef3',
                    'slot_selected_border' => '#c45b7a',
                    'booked_bg' => '#f9f5f6',
                ],
            ],
            'nature_green' => [
                'name' => 'Natur & Wellness',
                'desc' => 'Beruhigend und frisch',
                'colors' => [
                    'primary_color' => '#2d8a5e',
                    'button_hover' => '#236f4b',
                    'slot_bg' => '#fbfdfc',
                    'slot_hover' => '#e8f5ee',
                    'slot_selected_bg' => '#e3f2ea',
                    'slot_selected_border' => '#2d8a5e',
                    'booked_bg' => '#f4f7f5',
                ],
            ],
            'luxury_gold' => [
                'name' => 'Luxus Gold',
                'desc' => 'Edel und hochwertig',
                'colors' => [
                    'primary_color' => '#b8860b',
                    'button_hover' => '#946e09',
                    'slot_bg' => '#fffef9',
                    'slot_hover' => '#f5f0e1',
                    'slot_selected_bg' => '#faf5e4',
                    'slot_selected_border' => '#b8860b',
                    'booked_bg' => '#f7f5f0',
                ],
            ],
            'modern_violet' => [
                'name' => 'Modern Violett',
                'desc' => 'Stilvoll und zeitgemäß',
                'colors' => [
                    'primary_color' => '#6b4c9a',
                    'button_hover' => '#553d7c',
                    'slot_bg' => '#fcfbfe',
                    'slot_hover' => '#eee8f5',
                    'slot_selected_bg' => '#ebe3f5',
                    'slot_selected_border' => '#6b4c9a',
                    'booked_bg' => '#f6f4f8',
                ],
            ],
            'fresh_teal' => [
                'name' => 'Frisch Türkis',
                'desc' => 'Modern und freundlich',
                'colors' => [
                    'primary_color' => '#0d9488',
                    'button_hover' => '#0b7a72',
                    'slot_bg' => '#fafeff',
                    'slot_hover' => '#e0f5f3',
                    'slot_selected_bg' => '#d5f0ed',
                    'slot_selected_border' => '#0d9488',
                    'booked_bg' => '#f2f8f7',
                ],
            ],
            'elegant_anthracite' => [
                'name' => 'Elegant Anthrazit',
                'desc' => 'Zurückhaltend und professionell',
                'colors' => [
                    'primary_color' => '#4a5568',
                    'button_hover' => '#3a4351',
                    'slot_bg' => '#fdfdfd',
                    'slot_hover' => '#eceef1',
                    'slot_selected_bg' => '#e2e6eb',
                    'slot_selected_border' => '#4a5568',
                    'booked_bg' => '#f3f4f6',
                ],
            ],
        ];
    }

    /**
     * Methode default colors.
     * @return array<string, mixed>
     */
    public static function defaultColors(): array
    {
        return self::presets()['kaffee_braun']['colors'];
    }

    /**
     * Methode color keys.
     * @return array<string, mixed>
     */
    public static function colorKeys(): array
    {
        return array_keys(self::defaultColors());
    }
}
