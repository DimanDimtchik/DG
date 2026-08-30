<?php
declare(strict_types=1);

/**
 * Stroke icons for the public website menu (Lucide bundled locally, no CDN).
 */
final class WebsiteMenuIcons
{
    /** @var array<string, mixed>|null */
    private static ?array $manifest = null;

    /**
     * @return array<string, mixed>
     */
    private static function manifest(): array
    {
        if (self::$manifest !== null) {
            return self::$manifest;
        }
        $file = __DIR__ . '/data/lucide-menu-icons.php';
        if (!is_file($file)) {
            self::$manifest = ['icons' => [], 'legacy_aliases' => []];

            return self::$manifest;
        }
        $loaded = require $file;

        return self::$manifest = is_array($loaded) ? $loaded : ['icons' => [], 'legacy_aliases' => []];
    }

    /**
     * Resolve stored id through legacy aliases to a Lucide icon id.
     */
    public static function canonicalId(string $name): string
    {
        $name = strtolower(trim($name));
        if ($name === '' || $name === 'auto') {
            return $name;
        }
        $aliases = self::manifest()['legacy_aliases'] ?? [];
        if (is_array($aliases) && isset($aliases[$name])) {
            return (string) $aliases[$name];
        }

        return $name;
    }

    /**
     * @return array<string, string> id => label
     */
    public static function options(): array
    {
        static $cache = null;
        if ($cache !== null) {
            return $cache;
        }
        $options = [
            'auto' => 'Automatisch (Vorschlag)',
            '' => 'Kein Icon',
        ];
        $icons = self::manifest()['icons'] ?? [];
        if (is_array($icons)) {
            foreach ($icons as $id => $meta) {
                if (!is_array($meta)) {
                    continue;
                }
                $options[(string) $id] = (string) ($meta['label'] ?? $id);
            }
        }
        asort($options, SORT_FLAG_CASE | SORT_NATURAL);
        $cache = ['auto' => $options['auto'], '' => $options['']] + $options;

        return $cache;
    }

    /**
     * Filter icons for the picker search box.
     *
     * @return list<array{id: string, label: string}>
     */
    public static function searchOptions(string $query, int $limit = 120): array
    {
        $query = mb_strtolower(trim($query));
        $results = [];
        $icons = self::manifest()['icons'] ?? [];
        if (!is_array($icons)) {
            return [];
        }
        foreach ($icons as $id => $meta) {
            if (!is_array($meta)) {
                continue;
            }
            $label = (string) ($meta['label'] ?? $id);
            $tags = is_array($meta['tags'] ?? null) ? $meta['tags'] : [];
            $hay = mb_strtolower($id . ' ' . $label . ' ' . implode(' ', $tags));
            if ($query !== '' && !str_contains($hay, $query)) {
                continue;
            }
            $results[] = ['id' => (string) $id, 'label' => $label];
            if (count($results) >= $limit) {
                break;
            }
        }
        usort($results, static fn (array $a, array $b): int => strnatcasecmp($a['label'], $b['label']));

        return $results;
    }

    public static function isValid(string $name): bool
    {
        if ($name === 'auto' || $name === '') {
            return true;
        }
        $canonical = self::canonicalId($name);
        $icons = self::manifest()['icons'] ?? [];

        return is_array($icons) && isset($icons[$canonical]);
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
        if (!self::isValid($icon)) {
            return self::suggest(
                (string) ($item['label'] ?? ''),
                (string) ($item['url'] ?? ''),
                $hasChildren
            );
        }

        return self::canonicalId($icon);
    }

    /**
     * Suggest an icon from label/url; parents with children default to chevron-down.
     */
    public static function suggest(string $label, string $url, bool $hasChildren = false): string
    {
        $hay = mb_strtolower(trim($label . ' ' . $url));
        $rules = [
            'house' => ['start', 'home', 'startseite', '/$'],
            'calendar' => ['termin', 'kalender', 'buchung'],
            'users' => ['kontakt', 'kunde', 'person', 'team'],
            'mail' => ['mail', 'e-mail', 'email', 'nachricht'],
            'tag' => ['preis', 'tarif', 'abo', 'paket'],
            'globe' => ['website', 'internet', 'builder', 'web'],
            'calculator' => ['buchhaltung', 'rechnung', 'finanz'],
            'package' => ['artikel', 'leistung', 'katalog', 'produkt'],
            'image' => ['bild', 'galerie', 'foto', 'media'],
            'scale' => ['impressum', 'datenschutz', 'agb', 'recht', 'legal'],
            'file-text' => ['dokument', 'pdf', 'handbuch', 'seite'],
            'info' => ['info', 'über', 'uber', 'about'],
            'settings' => ['einstellung', 'konto', 'profil'],
            'folder' => ['ordner', 'archiv', 'download'],
            'external-link' => ['http://', 'https://', 'shop.', 'extern'],
            'phone' => ['telefon', 'anruf', 'hotline'],
            'map-pin' => ['standort', 'adresse', 'anfahrt'],
            'shopping-cart' => ['shop', 'warenkorb', 'bestell'],
            'shield' => ['datenschutz', 'sicherheit', 'ssl'],
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
     * @return array{
     *   size: string,
     *   color: string,
     *   color_custom: string,
     *   position: string,
     *   gap: string,
     *   stroke: string,
     *   visibility: string,
     *   badge: string,
     *   hover: string,
     *   hover_color_custom: string,
     *   hide_mobile: bool
     * }
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
            'visibility' => 'show',
            'badge' => '',
            'hover' => 'inherit',
            'hover_color_custom' => '',
            'hide_mobile' => false,
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
    public static function submenuIconHoverOptions(): array
    {
        return [
            'inherit' => 'Wie Icon-Farbe',
            'primary' => 'Primärfarbe (Design)',
            'text' => 'Textfarbe (Design)',
            'custom' => 'Eigene Farbe',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function submenuIconVisibilityOptions(): array
    {
        return [
            'show' => 'Icon anzeigen',
            'hidden' => 'Icon ausblenden',
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
     * @return array<string, string>
     */
    public static function iconGapOptions(): array
    {
        return [
            'tight' => 'Eng',
            'normal' => 'Normal',
            'wide' => 'Weit',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function iconStrokeOptions(): array
    {
        return [
            'light' => 'Dünn',
            'normal' => 'Normal',
            'bold' => 'Kräftig',
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
        if (!isset(self::iconGapOptions()[$gap])) {
            $gap = $defaults['gap'];
        }
        $stroke = (string) ($raw['stroke'] ?? $defaults['stroke']);
        if (!isset(self::iconStrokeOptions()[$stroke])) {
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
     * @return array{
     *   size: string,
     *   color: string,
     *   color_custom: string,
     *   position: string,
     *   gap: string,
     *   stroke: string,
     *   visibility: string,
     *   badge: string,
     *   hover: string,
     *   hover_color_custom: string,
     *   hide_mobile: bool
     * }
     */
    public static function normalizeSubmenuIconStyle(array $raw): array
    {
        $base = self::normalizeIconStyle($raw, true);
        $defaults = self::submenuIconStyleDefaults();
        $visibility = (string) ($raw['visibility'] ?? $defaults['visibility']);
        if (!isset(self::submenuIconVisibilityOptions()[$visibility])) {
            $visibility = $defaults['visibility'];
        }
        $hover = (string) ($raw['hover'] ?? $defaults['hover']);
        if (!isset(self::submenuIconHoverOptions()[$hover])) {
            $hover = $defaults['hover'];
        }
        $hoverCustom = trim((string) ($raw['hover_color_custom'] ?? ''));
        if ($hoverCustom !== '' && preg_match('/^#[0-9A-Fa-f]{6}$/', $hoverCustom) !== 1) {
            $hoverCustom = '';
        }
        $badge = mb_substr(trim((string) ($raw['badge'] ?? '')), 0, 24);
        $hideMobile = !empty($raw['hide_mobile']) && (string) ($raw['hide_mobile'] ?? '') !== '0';

        return $base + [
            'visibility' => $visibility,
            'badge' => $badge,
            'hover' => $hover,
            'hover_color_custom' => strtolower($hoverCustom),
            'hide_mobile' => $hideMobile,
        ];
    }

    /**
     * Whether the submenu item should render its icon.
     *
     * @param array<string, mixed> $item
     */
    public static function submenuIconVisible(array $item): bool
    {
        $style = self::normalizeSubmenuIconStyle(is_array($item['icon_style'] ?? null) ? $item['icon_style'] : []);

        return $style['visibility'] !== 'hidden';
    }

    /**
     * Optional badge label for a submenu item (empty = none).
     *
     * @param array<string, mixed> $item
     */
    public static function submenuBadgeLabel(array $item): string
    {
        $style = self::normalizeSubmenuIconStyle(is_array($item['icon_style'] ?? null) ? $item['icon_style'] : []);

        return $style['badge'];
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

    /**
     * CSS classes for submenu link (position, mobile icon hide).
     *
     * @param array<string, mixed> $item
     * @return list<string>
     */
    public static function submenuLinkClasses(array $item, bool $active = false): array
    {
        $classes = [];
        if ($active) {
            $classes[] = 'active';
        }
        if (self::submenuLinkIconRight($item)) {
            $classes[] = 'ws-nav__link--icon-right';
        }
        $style = self::normalizeSubmenuIconStyle(is_array($item['icon_style'] ?? null) ? $item['icon_style'] : []);
        if ($style['hide_mobile']) {
            $classes[] = 'ws-nav__link--icon-hide-mobile';
        }
        if ($style['visibility'] === 'hidden') {
            $classes[] = 'ws-nav__link--no-icon';
        }

        return $classes;
    }

    public static function submenuLinkIconRight(array $item): bool
    {
        $style = self::normalizeSubmenuIconStyle(is_array($item['icon_style'] ?? null) ? $item['icon_style'] : []);

        return $style['position'] === 'right';
    }

    /**
     * CSS custom properties for the public menu header.
     *
     * @param array<string, mixed> $iconStyle
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
        $color = self::resolveColor((string) $style['color'], (string) ($style['color_custom'] ?? ''), $submenu);
        $vars = [
            '--ws-nav-icon-size' => $sizePx,
            '--ws-nav-icon-gap' => $gapPx,
            '--ws-nav-icon-stroke' => $strokeW,
            '--ws-nav-icon-color' => $color,
        ];
        if ($submenu) {
            $hover = (string) ($style['hover'] ?? 'inherit');
            $hoverCustom = (string) ($style['hover_color_custom'] ?? '');
            if ($hover !== 'inherit') {
                $vars['--ws-nav-icon-hover-color'] = self::resolveColor($hover, $hoverCustom, true);
            }
        }

        return $vars;
    }

    private static function resolveColor(string $mode, string $custom, bool $submenu): string
    {
        return match ($mode) {
            'primary' => 'var(--ws-primary)',
            'text' => 'var(--ws-text)',
            'custom' => $custom !== '' ? $custom : 'currentColor',
            default => $submenu ? 'currentColor' : 'currentColor',
        };
    }

    public static function svg(string $name, string $class = 'ws-nav__icon', ?array $iconStyle = null): string
    {
        $canonical = self::canonicalId($name);
        if ($canonical === '' || !self::isValid($canonical)) {
            return '';
        }
        $paths = self::paths($canonical);
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
        $canonical = self::canonicalId($name);
        $icons = self::manifest()['icons'] ?? [];
        if (!is_array($icons) || !isset($icons[$canonical]) || !is_array($icons[$canonical])) {
            return '';
        }

        return (string) ($icons[$canonical]['paths'] ?? '');
    }

    /**
     * Compact catalog for the CRM icon picker (local Lucide, no CDN).
     *
     * @return list<array{id: string, label: string, tags: list<string>, paths: string}>
     */
    public static function pickerCatalog(): array
    {
        static $cache = null;
        if ($cache !== null) {
            return $cache;
        }
        $out = [];
        $icons = self::manifest()['icons'] ?? [];
        if (!is_array($icons)) {
            return $cache = [];
        }
        foreach ($icons as $id => $meta) {
            if (!is_array($meta)) {
                continue;
            }
            $out[] = [
                'id' => (string) $id,
                'label' => (string) ($meta['label'] ?? $id),
                'tags' => is_array($meta['tags'] ?? null) ? array_values($meta['tags']) : [],
                'paths' => (string) ($meta['paths'] ?? ''),
            ];
        }
        usort($out, static fn (array $a, array $b): int => strnatcasecmp($a['label'], $b['label']));

        return $cache = $out;
    }

    /** @return list<string> */
    public static function pickerFeaturedIds(): array
    {
        return [
            'house', 'calendar', 'users', 'mail', 'globe', 'file-text', 'menu', 'folder', 'tag',
            'scale', 'info', 'image', 'package', 'calculator', 'receipt', 'settings', 'palette',
            'layout-grid', 'external-link', 'phone', 'map-pin', 'shopping-cart', 'shield', 'search',
            'chevron-down', 'heart', 'star', 'bell', 'clock', 'building', 'book-open', 'download',
        ];
    }
}
