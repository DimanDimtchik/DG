<?php
declare(strict_types=1);

final class CrmThemeSettings
{
    public const STORE_KEY = 'crm_theme';

    /** @return array<string, string> */
    public static function defaults(): array
    {
        return CrmThemePresets::defaultColors();
    }

    /** @return array<string, string> */
    public static function colors(): array
    {
        $stored = self::storedColors();

        return CrmThemePresets::expandColors($stored);
    }

    /** @return array<string, string> */
    public static function forForm(): array
    {
        $stored = self::storedColors();
        $normalized = [];
        foreach (CrmThemePresets::colorKeys() as $key) {
            $normalized[$key] = ThemeColor::sanitizeHex(
                (string) ($stored[$key] ?? ''),
                self::defaults()[$key]
            );
        }

        return $normalized;
    }

    /** @return array<string, string> */
    private static function storedColors(): array
    {
        if (!Database::isConfigured()) {
            return self::defaults();
        }

        try {
            $stored = SettingsStore::get(self::STORE_KEY, self::defaults());
        } catch (Throwable) {
            return self::defaults();
        }

        return self::normalizeLegacyKaffeeBraun($stored);
    }

    /** @param array<string, mixed> $stored */
    private static function normalizeLegacyKaffeeBraun(array $stored): array
    {
        $menuBg = ThemeColor::sanitizeHex((string) ($stored['menu_bg'] ?? ''), '');
        $brand = ThemeColor::sanitizeHex((string) ($stored['brand'] ?? ''), '');
        $primary = ThemeColor::sanitizeHex((string) ($stored['primary'] ?? ''), '');

        if (
            $menuBg === '#59524c'
            && in_array($brand, ['#03a9f4', '#0288d1'], true)
            && in_array($primary, ['#2271b1', '#135e96'], true)
        ) {
            return self::defaults();
        }

        return $stored;
    }

    /** @param array<string, mixed> $input */
    public static function save(array $input): void
    {
        $defaults = self::defaults();
        $colors = [];

        foreach (CrmThemePresets::colorKeys() as $key) {
            $colors[$key] = ThemeColor::sanitizeHex((string) ($input[$key] ?? ''), $defaults[$key]);
        }

        SettingsStore::set(self::STORE_KEY, $colors);
    }

    /** @return array<string, array{label: string, hint: string, group: string}> */
    public static function fieldDefinitions(): array
    {
        return [
            'menu_bg' => [
                'group' => 'navigation',
                'label' => 'Menü & Seitenleiste (Hintergrund)',
                'hint' => 'Oberes Menü und linke Navigation',
            ],
            'menu_text' => [
                'group' => 'navigation',
                'label' => 'Menü-Text',
                'hint' => 'Beschriftungen in Menü und Seitenleiste',
            ],
            'brand' => [
                'group' => 'brand',
                'label' => 'Markenfarbe',
                'hint' => 'Logo-Akzent und Hervorhebungen in der Navigation',
            ],
            'body_bg' => [
                'group' => 'layout',
                'label' => 'Seiten-Hintergrund',
                'hint' => 'Hintergrund hinter den Inhaltsbereichen',
            ],
            'surface' => [
                'group' => 'layout',
                'label' => 'Karten & Panels',
                'hint' => 'Hintergrund von Formularen, Tabellen und Boxen',
            ],
            'text' => [
                'group' => 'text',
                'label' => 'Fließtext',
                'hint' => 'Haupttextfarbe im Inhaltsbereich',
            ],
            'text_secondary' => [
                'group' => 'text',
                'label' => 'Sekundärtext',
                'hint' => 'Untertitel und weniger betonte Texte',
            ],
            'text_muted' => [
                'group' => 'text',
                'label' => 'Gedämpfter Text',
                'hint' => 'Hinweise und Platzhalter',
            ],
            'border' => [
                'group' => 'controls',
                'label' => 'Rahmen',
                'hint' => 'Linien um Felder und Tabellen',
            ],
            'border_strong' => [
                'group' => 'controls',
                'label' => 'Rahmen (stark)',
                'hint' => 'Betonte Rahmen und Trennlinien',
            ],
            'primary' => [
                'group' => 'controls',
                'label' => 'Primärfarbe (Buttons)',
                'hint' => 'Hauptaktionen und Links',
            ],
            'primary_hover' => [
                'group' => 'controls',
                'label' => 'Primärfarbe Hover',
                'hint' => 'Buttons und Links beim Darüberfahren',
            ],
        ];
    }

    /** @return array<string, string> */
    public static function groupLabels(): array
    {
        return [
            'navigation' => 'Oberes Menü & Seitenleiste',
            'brand' => 'Marke',
            'layout' => 'Hintergrund',
            'text' => 'Text',
            'controls' => 'Rahmen & Bedienelemente',
        ];
    }
}
