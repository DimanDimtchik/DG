<?php
declare(strict_types=1);

/**
 * Crm Theme Presets.
 */
final class CrmThemePresets
{
    /**
     * Methode color keys.
     * @return array<string, mixed>
     */
    public static function colorKeys(): array
    {
        return array_keys(self::defaultColors());
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
     * Liefert vordefinierte Presets.
     * @return array<string, array<string, string>>
     */
    public static function presets(): array
    {
        return [
            'kaffee_braun' => [
                'name' => 'Kaffee Braun',
                'desc' => 'Warmes Braun — Menü, Akzente und Buttons in Brauntönen',
                'colors' => [
                    'menu_bg' => '#59524c',
                    'menu_text' => '#ffffff',
                    'brand' => '#c4b5a5',
                    'body_bg' => '#ebe6e1',
                    'surface' => '#ffffff',
                    'text' => '#1d2327',
                    'text_secondary' => '#50575e',
                    'text_muted' => '#787c82',
                    'border' => '#d4ccc4',
                    'border_strong' => '#b8aea3',
                    'primary' => '#6e6258',
                    'primary_hover' => '#5a5048',
                ],
            ],
            'klassisch_blau' => [
                'name' => 'Klassisch Blau',
                'desc' => 'Dunkle Navigation, blaue Akzente',
                'colors' => [
                    'menu_bg' => '#1d2327',
                    'menu_text' => '#ffffff',
                    'brand' => '#03a9f4',
                    'body_bg' => '#f0f0f1',
                    'surface' => '#ffffff',
                    'text' => '#1d2327',
                    'text_secondary' => '#50575e',
                    'text_muted' => '#787c82',
                    'border' => '#c3c4c7',
                    'border_strong' => '#a7aaad',
                    'primary' => '#2271b1',
                    'primary_hover' => '#135e96',
                ],
            ],
            'rose_beauty' => [
                'name' => 'Rosé & Beauty',
                'desc' => 'Warmes Braun-Rosé für Studios',
                'colors' => [
                    'menu_bg' => '#5c3d47',
                    'menu_text' => '#ffffff',
                    'brand' => '#c45b7a',
                    'body_bg' => '#f7f0f2',
                    'surface' => '#ffffff',
                    'text' => '#2a1f23',
                    'text_secondary' => '#5c4a50',
                    'text_muted' => '#8a747b',
                    'border' => '#dbc9cf',
                    'border_strong' => '#c9b0b8',
                    'primary' => '#b84d6a',
                    'primary_hover' => '#9a3f58',
                ],
            ],
            'natur_wellness' => [
                'name' => 'Natur & Wellness',
                'desc' => 'Beruhigendes Grün',
                'colors' => [
                    'menu_bg' => '#2f4f40',
                    'menu_text' => '#ffffff',
                    'brand' => '#2d8a5e',
                    'body_bg' => '#eef4f0',
                    'surface' => '#ffffff',
                    'text' => '#1c2a22',
                    'text_secondary' => '#3f5247',
                    'text_muted' => '#6b7f73',
                    'border' => '#c5d5cb',
                    'border_strong' => '#a8bdb0',
                    'primary' => '#268656',
                    'primary_hover' => '#1e6d45',
                ],
            ],
            'luxus_gold' => [
                'name' => 'Luxus Gold',
                'desc' => 'Edles Braun-Gold',
                'colors' => [
                    'menu_bg' => '#4a4028',
                    'menu_text' => '#ffffff',
                    'brand' => '#b8860b',
                    'body_bg' => '#f7f4eb',
                    'surface' => '#ffffff',
                    'text' => '#2a2418',
                    'text_secondary' => '#5c5340',
                    'text_muted' => '#8a8068',
                    'border' => '#d8cfba',
                    'border_strong' => '#c4b89a',
                    'primary' => '#9a7209',
                    'primary_hover' => '#7d5d07',
                ],
            ],
            'modern_violett' => [
                'name' => 'Modern Violett',
                'desc' => 'Stilvolles Violett',
                'colors' => [
                    'menu_bg' => '#3f3255',
                    'menu_text' => '#ffffff',
                    'brand' => '#6b4c9a',
                    'body_bg' => '#f3f0f7',
                    'surface' => '#ffffff',
                    'text' => '#231c30',
                    'text_secondary' => '#4f455c',
                    'text_muted' => '#7d738a',
                    'border' => '#cfc6db',
                    'border_strong' => '#b8adc8',
                    'primary' => '#5d4189',
                    'primary_hover' => '#4b3470',
                ],
            ],
            'frisch_tuerkis' => [
                'name' => 'Frisch Türkis',
                'desc' => 'Modern und freundlich',
                'colors' => [
                    'menu_bg' => '#1f4e4a',
                    'menu_text' => '#ffffff',
                    'brand' => '#0d9488',
                    'body_bg' => '#edf7f6',
                    'surface' => '#ffffff',
                    'text' => '#142826',
                    'text_secondary' => '#3d5654',
                    'text_muted' => '#6b8481',
                    'border' => '#c0d9d6',
                    'border_strong' => '#a3c4c0',
                    'primary' => '#0b7f75',
                    'primary_hover' => '#09665e',
                ],
            ],
            'elegant_anthrazit' => [
                'name' => 'Elegant Anthrazit',
                'desc' => 'Zurückhaltend und professionell',
                'colors' => [
                    'menu_bg' => '#2d3748',
                    'menu_text' => '#ffffff',
                    'brand' => '#4a5568',
                    'body_bg' => '#eceff3',
                    'surface' => '#ffffff',
                    'text' => '#1a202c',
                    'text_secondary' => '#4a5568',
                    'text_muted' => '#718096',
                    'border' => '#cbd5e0',
                    'border_strong' => '#a0aec0',
                    'primary' => '#3d4a5c',
                    'primary_hover' => '#2f3948',
                ],
            ],
        ];
    }

    /**
     * Methode expand colors.
     * @param array $colors
     * @return array<string, mixed>
     */
    public static function expandColors(array $colors): array
    {
        $defaults = self::defaultColors();
        $menuBg = ThemeColor::sanitizeHex($colors['menu_bg'] ?? '', $defaults['menu_bg']);
        $menuText = ThemeColor::sanitizeHex($colors['menu_text'] ?? '', $defaults['menu_text']);
        $brand = ThemeColor::sanitizeHex($colors['brand'] ?? '', $defaults['brand']);
        $primary = ThemeColor::sanitizeHex($colors['primary'] ?? '', $defaults['primary']);

        $expanded = [];
        foreach (self::colorKeys() as $key) {
            $expanded[$key] = ThemeColor::sanitizeHex((string) ($colors[$key] ?? ''), $defaults[$key]);
        }

        $expanded['menu_bg_hover'] = ThemeColor::darken($menuBg, 0.08);
        $expanded['menu_bg_active'] = ThemeColor::darken($menuBg, 0.16);
        $expanded['menu_border'] = ThemeColor::darken($menuBg, 0.16);
        $expanded['menu_text_muted'] = ThemeColor::withAlpha($menuText, 0.72);
        $expanded['brand_dark'] = ThemeColor::darken($brand, 0.12);
        $expanded['focus_ring'] = ThemeColor::focusRing($primary);

        return $expanded;
    }
}
