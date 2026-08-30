<?php
declare(strict_types=1);

/**
 * Stroke icons for the public website menu (no external icon CDN).
 */
final class WebsiteMenuIcons
{
    /**
     * @return array<string, string> id => label
     */
    public static function options(): array
    {
        return [
            'auto' => 'Automatisch (Vorschlag)',
            '' => 'Kein Icon',
            'chevron-down' => 'Pfeil (Untermenü)',
            'home' => 'Start / Haus',
            'calendar' => 'Kalender',
            'contacts' => 'Personen',
            'mail' => 'E-Mail',
            'website' => 'Website / Globus',
            'document' => 'Dokument',
            'nav' => 'Liste',
            'folder' => 'Ordner',
            'tag' => 'Preis / Etikett',
            'scale' => 'Rechtliches',
            'info' => 'Info',
            'images' => 'Bilder',
            'catalog' => 'Katalog',
            'accounting' => 'Buchhaltung',
            'receipt' => 'Beleg',
            'settings' => 'Einstellungen',
            'palette' => 'Design',
            'layout' => 'Layout',
            'external' => 'Externer Link',
        ];
    }

    public static function isValid(string $name): bool
    {
        return isset(self::options()[$name]);
    }

    /**
     * Resolve stored icon setting to a concrete icon id (may be empty).
     *
     * @param array<string, mixed> $item
     */
    public static function resolve(array $item): string
    {
        $icon = strtolower(trim((string) ($item['icon'] ?? 'auto')));
        $children = is_array($item['children'] ?? null) ? $item['children'] : [];
        $hasChildren = $children !== [];
        if ($icon === 'auto' || $icon === '') {
            if ($icon === '') {
                return '';
            }

            return self::suggest(
                (string) ($item['label'] ?? ''),
                (string) ($item['url'] ?? ''),
                $hasChildren
            );
        }
        if (!isset(self::options()[$icon])) {
            return self::suggest(
                (string) ($item['label'] ?? ''),
                (string) ($item['url'] ?? ''),
                $hasChildren
            );
        }

        return $icon;
    }

    /**
     * Suggest an icon from label/url; parents with children default to chevron-down.
     */
    public static function suggest(string $label, string $url, bool $hasChildren = false): string
    {
        $hay = mb_strtolower(trim($label . ' ' . $url));
        $rules = [
            'home' => ['start', 'home', 'startseite', '/$'],
            'calendar' => ['termin', 'kalender', 'buchung'],
            'contacts' => ['kontakt', 'kunde', 'person'],
            'mail' => ['mail', 'e-mail', 'email', 'nachricht'],
            'tag' => ['preis', 'tarif', 'abo', 'paket'],
            'website' => ['website', 'internet', 'builder', 'web'],
            'accounting' => ['buchhaltung', 'rechnung', 'finanz'],
            'catalog' => ['artikel', 'leistung', 'katalog', 'produkt'],
            'images' => ['bild', 'galerie', 'foto', 'media'],
            'scale' => ['impressum', 'datenschutz', 'agb', 'recht', 'legal'],
            'document' => ['dokument', 'pdf', 'handbuch', 'seite'],
            'info' => ['info', 'über', 'uber', 'about'],
            'settings' => ['einstellung', 'konto', 'profil'],
            'folder' => ['ordner', 'archiv', 'download'],
            'external' => ['http://', 'https://', 'shop.', 'extern'],
        ];
        foreach ($rules as $icon => $needles) {
            foreach ($needles as $needle) {
                if ($needle === '/$' && (rtrim($url, '/') === '' || $url === '/')) {
                    return $icon;
                }
                if ($needle !== '/$' && $needle !== '' && str_contains($hay, $needle)) {
                    return $icon;
                }
            }
        }

        return $hasChildren ? 'chevron-down' : '';
    }

    /**
     * Defaults for icons in the main navigation bar (header).
     *
     * @return array{size: string, color: string, color_custom: string, position: string, gap: string, stroke: string}
     */
    public static function headerIconStyleDefaults(): array
    {
        return [
            'size' => 'md',
            'color' => 'inherit',
            'color_custom' => '',
            'position' => 'left',
            'gap' => 'normal',
            'stroke' => 'normal',
        ];
    }

    /**
     * Defaults for icons in dropdown submenus (white panel).
     *
     * @return array{size: string, color: string, color_custom: string, position: string, gap: string, stroke: string}
     */
    public static function submenuIconStyleDefaults(): array
    {
        return [
            'size' => 'md',
            'color' => 'primary',
            'color_custom' => '',
            'position' => 'left',
            'gap' => 'normal',
            'stroke' => 'normal',
        ];
    }

    /** @deprecated use headerIconStyleDefaults() */
    public static function iconStyleDefaults(): array
    {
        return self::headerIconStyleDefaults();
    }

    /**
     * @return array<string, string>
     */
    public static function submenuIconColorOptions(): array
    {
        return [
            'primary' => 'Primärfarbe (Design)',
            'text' => 'Textfarbe (Design)',
            'inherit' => 'Wie Untermenü-Text',
            'custom' => 'Eigene Farbe',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function iconSizeOptions(): array
    {
        return [
            'sm' => 'Klein (14 px)',
            'md' => 'Mittel (18 px)',
            'lg' => 'Groß (22 px)',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function iconColorOptions(): array
    {
        return [
            'inherit' => 'Wie Menütext (Standard)',
            'primary' => 'Primärfarbe (Design)',
            'text' => 'Textfarbe (Design)',
            'custom' => 'Eigene Farbe',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function iconPositionOptions(): array
    {
        return [
            'left' => 'Links vom Text',
            'right' => 'Rechts vom Text',
        ];
    }

    /**
     * @param array<string, mixed> $raw
     * @return array{size: string, color: string, color_custom: string, position: string, gap: string, stroke: string}
     */
    public static function normalizeIconStyle(array $raw, bool $submenu = false): array
    {
        $defaults = $submenu ? self::submenuIconStyleDefaults() : self::headerIconStyleDefaults();
        $size = (string) ($raw['size'] ?? $defaults['size']);
        if (!isset(self::iconSizeOptions()[$size])) {
            $size = $defaults['size'];
        }
        $colorOptions = $submenu ? self::submenuIconColorOptions() : self::iconColorOptions();
        $color = (string) ($raw['color'] ?? $defaults['color']);
        if (!isset($colorOptions[$color])) {
            $color = $defaults['color'];
        }
        $position = (string) ($raw['position'] ?? $defaults['position']);
        if (!isset(self::iconPositionOptions()[$position])) {
            $position = $defaults['position'];
        }
        $gap = (string) ($raw['gap'] ?? $defaults['gap']);
        if (!in_array($gap, ['tight', 'normal', 'wide'], true)) {
            $gap = $defaults['gap'];
        }
        $stroke = (string) ($raw['stroke'] ?? $defaults['stroke']);
        if (!in_array($stroke, ['light', 'normal', 'bold'], true)) {
            $stroke = $defaults['stroke'];
        }
        $colorCustom = trim((string) ($raw['color_custom'] ?? ''));
        if ($colorCustom !== '' && preg_match('/^#[0-9A-Fa-f]{6}$/', $colorCustom) !== 1) {
            $colorCustom = '';
        }

        return [
            'size' => $size,
            'color' => $color,
            'color_custom' => strtolower($colorCustom),
            'position' => $position,
            'gap' => $gap,
            'stroke' => $stroke,
        ];
    }

    /**
     * @param array<string, mixed> $raw
     * @return array{size: string, color: string, color_custom: string, position: string, gap: string, stroke: string}
     */
    public static function normalizeSubmenuIconStyle(array $raw): array
    {
        return self::normalizeIconStyle($raw, true);
    }

    /**
     * Inline CSS variables for one menu link.
     *
     * @param array<string, mixed> $item
     */
    public static function submenuLinkStyleAttr(array $item): string
    {
        $style = self::normalizeSubmenuIconStyle(is_array($item['icon_style'] ?? null) ? $item['icon_style'] : []);
        $parts = [];
        foreach (self::iconStyleCssVars($style, true) as $key => $value) {
            $parts[] = $key . ':' . $value;
        }

        return $parts !== [] ? implode(';', $parts) : '';
    }

    public static function submenuLinkIconRight(array $item): bool
    {
        $style = self::normalizeSubmenuIconStyle(is_array($item['icon_style'] ?? null) ? $item['icon_style'] : []);

        return $style['position'] === 'right';
    }

    /**
     * CSS custom properties for the public menu header.
     *
     * @param array<string, string> $iconStyle
     * @return array<string, string>
     */
    public static function iconStyleCssVars(array $iconStyle, bool $submenu = false): array
    {
        $style = $submenu ? self::normalizeSubmenuIconStyle($iconStyle) : self::normalizeIconStyle($iconStyle);
        $sizePx = match ($style['size']) {
            'sm' => '14px',
            'lg' => '22px',
            default => '18px',
        };
        $gapPx = match ($style['gap']) {
            'tight' => '0.25em',
            'wide' => '0.65em',
            default => '0.4em',
        };
        $strokeW = match ($style['stroke']) {
            'light' => '1.35',
            'bold' => '2.1',
            default => '1.75',
        };
        $color = match ($style['color']) {
            'primary' => 'var(--ws-primary)',
            'text' => 'var(--ws-text)',
            'custom' => $style['color_custom'] !== '' ? $style['color_custom'] : 'currentColor',
            default => 'currentColor',
        };

        return [
            '--ws-nav-icon-size' => $sizePx,
            '--ws-nav-icon-gap' => $gapPx,
            '--ws-nav-icon-stroke' => $strokeW,
            '--ws-nav-icon-color' => $color,
        ];
    }

    public static function svg(string $name, string $class = 'ws-nav__icon', ?array $iconStyle = null): string
    {
        if ($name === '' || !self::isValid($name)) {
            return '';
        }
        $paths = self::paths($name);
        if ($paths === '') {
            return '';
        }
        $stroke = '1.75';
        if ($iconStyle !== null) {
            $normalized = self::normalizeIconStyle($iconStyle);
            $stroke = match ($normalized['stroke']) {
                'light' => '1.35',
                'bold' => '2.1',
                default => '1.75',
            };
        }

        return '<svg class="' . htmlspecialchars($class, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
            . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="' . $stroke . '" '
            . 'stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'
            . $paths
            . '</svg>';
    }

    private static function paths(string $name): string
    {
        return match ($name) {
            'chevron-down' => '<path d="m6 9 6 6 6-6"/>',
            'home' => '<path d="M3 10.5 12 3l9 7.5V20a1 1 0 0 1-1 1h-5v-6H9v6H4a1 1 0 0 1-1-1v-9.5Z"/>',
            'calendar' => '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M8 3v4M16 3v4M3 11h18"/>',
            'contacts' => '<circle cx="12" cy="8" r="4"/><path d="M5 20a7 7 0 0 1 14 0"/>',
            'mail' => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/>',
            'website' => '<circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3c3 3.2 4.5 6.2 4.5 9S15 17.8 12 21c-3-3.2-4.5-6.2-4.5-9S9 6.2 12 3Z"/>',
            'document' => '<path d="M6 2h8l4 4v16H6z"/><path d="M14 2v4h4"/><path d="M9 13h6M9 17h6"/>',
            'nav' => '<path d="M4 7h16M4 12h16M4 17h10"/>',
            'folder' => '<path d="M3 7a2 2 0 0 1 2-2h5l2 2h7a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2Z"/>',
            'tag' => '<path d="M20.6 13.4 12 22l-8.5-8.5a2 2 0 0 1 0-2.8L10.7 3.5a2 2 0 0 1 1.4-.6H20v7.9a2 2 0 0 1-.6 1.4Z"/><circle cx="16" cy="8" r="1.25"/>',
            'scale' => '<path d="M12 3v18"/><path d="M5 7h14"/><path d="M7 7 4 14h6L7 7Zm10 0-3 7h6l-3-7Z"/>',
            'info' => '<circle cx="12" cy="12" r="9"/><path d="M12 10v6M12 7.5h.01"/>',
            'images' => '<rect x="3" y="5" width="18" height="14" rx="2"/><circle cx="9" cy="11" r="2"/><path d="m21 17-5.5-5.5a1.5 1.5 0 0 0-2.12 0L3 19"/>',
            'catalog' => '<path d="M21 8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16Z"/><path d="m3.3 7 8.7 5 8.7-5M12 22V12"/>',
            'accounting' => '<path d="M4 4h16v16H4z"/><path d="M8 8h8M8 12h8M8 16h5"/>',
            'receipt' => '<path d="M6 3h12v18l-2-1.5L14 21l-2-1.5L10 21l-2-1.5L6 21V3z"/><path d="M9 8h6M9 12h6M9 16h4"/>',
            'settings' => '<circle cx="12" cy="12" r="3"/><path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M4.93 19.07l1.41-1.41M17.66 6.34l1.41-1.41"/>',
            'palette' => '<path d="M12 3a9 9 0 1 0 0 18h1.5a2.5 2.5 0 0 0 0-5H12"/><circle cx="7.5" cy="10" r="1"/><circle cx="10.5" cy="7.5" r="1"/><circle cx="14.5" cy="7.5" r="1"/><circle cx="16.5" cy="11" r="1"/>',
            'layout' => '<rect x="3" y="4" width="18" height="16" rx="2"/><path d="M3 9h18M3 15h18"/>',
            'external' => '<path d="M14 4h6v6"/><path d="M10 14 20 4"/><path d="M20 14v5a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V5a1 1 0 0 1 1-1h5"/>',
            default => '',
        };
    }
}
