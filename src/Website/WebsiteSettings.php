<?php
declare(strict_types=1);

/**
 * Website chrome, design tokens, and navigation menu (SettingsStore-backed).
 */
final class WebsiteSettings
{
    private const MENU_KEY = 'website.menu';
    private const CHROME_KEY = 'website.chrome';
    private const DESIGN_KEY = 'website.design';

    /**
     * Default top navigation when nothing is stored yet.
     *
     * @return array{
     *   items: list<array{label: string, url: string, auth_only: bool, children: list<array<string, mixed>>}>,
     *   layout: string,
     *   breakpoint: int
     * }
     */
    public static function menuDefaults(): array
    {
        return [
            'items' => [
                [
                    'label' => 'Start',
                    'url' => '/',
                    'auth_only' => false,
                    'icon' => 'auto',
                    'children' => [],
                ],
            ],
            'layout' => 'auto',
            'breakpoint' => 768,
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function menuLayoutOptions(): array
    {
        return [
            'standard' => 'Standard (immer horizontales Menü)',
            'mobile' => 'Mobil (immer Hamburger-Menü)',
            'auto' => 'Automatisch (Umschalten ab Breite)',
        ];
    }

    /**
     * Default header/footer chrome fields.
     *
     * @return array{
     *   header_title: string,
     *   header_tagline: string,
     *   footer_text: string,
     *   header_js: string,
     *   footer_js: string,
     *   ga_measurement_id: string,
     *   gtm_container_id: string
     * }
     */
    public static function chromeDefaults(): array
    {
        return [
            'header_title' => '',
            'header_tagline' => '',
            'footer_text' => '',
            'header_js' => '',
            'footer_js' => '',
            'ga_measurement_id' => '',
            'gtm_container_id' => '',
        ];
    }

    /**
     * Default brand colors for the public website.
     *
     * @return array{primary: string, background: string, text: string}
     */
    public static function designDefaults(): array
    {
        return [
            'primary' => '#6e6258',
            'background' => '#ffffff',
            'text' => '#1d2327',
        ];
    }

    /**
     * Full menu as stored (editor). Includes private entries.
     *
     * @return array{items: list<array<string, mixed>>, layout: string, breakpoint: int}
     */
    public static function menu(): array
    {
        $stored = SettingsStore::get(self::MENU_KEY, self::menuDefaults());
        if (!is_array($stored)) {
            $stored = [];
        }
        $items = [];
        $rawItems = is_array($stored['items'] ?? null) ? $stored['items'] : [];
        foreach ($rawItems as $item) {
            if (!is_array($item)) {
                continue;
            }
            $normalized = self::normalizeMenuItem($item, true);
            if ($normalized !== null) {
                $items[] = $normalized;
            }
        }
        if ($items === []) {
            $items = self::menuDefaults()['items'];
        }

        return [
            'items' => $items,
            'layout' => self::normalizeLayout((string) ($stored['layout'] ?? 'auto')),
            'breakpoint' => self::normalizeBreakpoint($stored['breakpoint'] ?? 768),
        ];
    }

    /**
     * Menu for the public website (filters auth_only when guest).
     *
     * @return array{items: list<array<string, mixed>>, layout: string, breakpoint: int}
     */
    public static function publicMenu(?bool $loggedIn = null): array
    {
        $full = self::menu();
        $loggedIn ??= class_exists('AuthService') && AuthService::check();
        $items = [];
        foreach ($full['items'] as $item) {
            if (!empty($item['auth_only']) && !$loggedIn) {
                continue;
            }
            $children = [];
            foreach ($item['children'] ?? [] as $child) {
                if (!is_array($child)) {
                    continue;
                }
                if (!empty($child['auth_only']) && !$loggedIn) {
                    continue;
                }
                $children[] = $child;
            }
            $item['children'] = $children;
            $url = trim((string) ($item['url'] ?? ''));
            if ($url === '' || $url === '#') {
                if ($children === []) {
                    continue;
                }
            }
            $items[] = $item;
        }
        if ($items === []) {
            $items = self::menuDefaults()['items'];
        }

        return [
            'items' => $items,
            'layout' => $full['layout'],
            'breakpoint' => $full['breakpoint'],
        ];
    }

    /**
     * Stored header/footer chrome (falls back to defaults).
     *
     * @return array<string, string>
     */
    public static function chrome(): array
    {
        $stored = SettingsStore::get(self::CHROME_KEY, self::chromeDefaults());
        if (!is_array($stored)) {
            $stored = [];
        }

        return array_merge(self::chromeDefaults(), $stored);
    }

    /**
     * Stored design colors (falls back to defaults).
     *
     * @return array<string, string>
     */
    public static function design(): array
    {
        return SettingsStore::get(self::DESIGN_KEY, self::designDefaults());
    }

    /**
     * Persist menu from a POST payload (`items` list).
     *
     * @param array<string, mixed> $post
     */
    public static function saveMenu(array $post): void
    {
        $raw = $post['items'] ?? [];
        if (!is_array($raw)) {
            $raw = [];
        }
        $items = [];
        foreach ($raw as $item) {
            if (!is_array($item)) {
                continue;
            }
            $normalized = self::normalizeMenuItem($item, true);
            if ($normalized !== null) {
                $items[] = $normalized;
            }
        }
        if ($items === []) {
            $items = self::menuDefaults()['items'];
        }
        $existing = SettingsStore::get(self::MENU_KEY, self::menuDefaults());
        if (!is_array($existing)) {
            $existing = [];
        }
        $layout = array_key_exists('layout', $post)
            ? self::normalizeLayout((string) $post['layout'])
            : self::normalizeLayout((string) ($existing['layout'] ?? 'auto'));
        $breakpoint = array_key_exists('breakpoint', $post)
            ? self::normalizeBreakpoint($post['breakpoint'])
            : self::normalizeBreakpoint($existing['breakpoint'] ?? 768);
        SettingsStore::set(self::MENU_KEY, [
            'items' => $items,
            'layout' => $layout,
            'breakpoint' => $breakpoint,
        ]);
    }

    /**
     * Persist chrome fields from a POST payload.
     *
     * @param array<string, mixed> $post
     */
    public static function saveChrome(array $post): void
    {
        SettingsStore::set(self::CHROME_KEY, [
            'header_title' => mb_substr(trim((string) ($post['header_title'] ?? '')), 0, 120),
            'header_tagline' => mb_substr(trim((string) ($post['header_tagline'] ?? '')), 0, 191),
            'footer_text' => mb_substr(trim((string) ($post['footer_text'] ?? '')), 0, 500),
            'header_js' => trim((string) ($post['header_js'] ?? '')),
            'footer_js' => trim((string) ($post['footer_js'] ?? '')),
            'ga_measurement_id' => WebsiteAnalytics::normalizeGaId((string) ($post['ga_measurement_id'] ?? '')),
            'gtm_container_id' => WebsiteAnalytics::normalizeGtmId((string) ($post['gtm_container_id'] ?? '')),
        ]);
    }

    /**
     * Persist design colors from a POST payload.
     *
     * @param array<string, mixed> $post
     */
    public static function saveDesign(array $post): void
    {
        SettingsStore::set(self::DESIGN_KEY, [
            'primary' => self::sanitizeColor((string) ($post['primary'] ?? ''), '#6e6258'),
            'background' => self::sanitizeColor((string) ($post['background'] ?? ''), '#ffffff'),
            'text' => self::sanitizeColor((string) ($post['text'] ?? ''), '#1d2327'),
        ]);
    }

    /**
     * Normalize one menu item; returns null if empty/invalid.
     *
     * @param array<string, mixed> $item
     * @param bool $allowChildren When false, nested children are ignored (one nesting level)
     * @return array{label: string, url: string, auth_only: bool, icon: string, children: list<array<string, mixed>>, icon_style?: array<string, string>}|null
     */
    private static function normalizeMenuItem(array $item, bool $allowChildren): ?array
    {
        $label = trim((string) ($item['label'] ?? ''));
        $url = trim((string) ($item['url'] ?? ''));
        $authOnly = !empty($item['auth_only']) && (string) ($item['auth_only'] ?? '') !== '0';
        $icon = strtolower(trim((string) ($item['icon'] ?? 'auto')));
        if ($icon !== 'auto' && $icon !== '' && !WebsiteMenuIcons::isValid($icon)) {
            $icon = 'auto';
        }

        $children = [];
        if ($allowChildren) {
            $rawChildren = $item['children'] ?? [];
            if (is_array($rawChildren)) {
                foreach ($rawChildren as $child) {
                    if (!is_array($child)) {
                        continue;
                    }
                    $normalizedChild = self::normalizeMenuItem($child, false);
                    if ($normalizedChild !== null) {
                        $children[] = $normalizedChild;
                    }
                }
            }
        }

        if ($label === '' && $url === '' && $children === []) {
            return null;
        }
        if ($label === '' && $children === []) {
            return null;
        }
        if ($label === '') {
            $label = 'Menü';
        }

        return [
            'label' => mb_substr($label, 0, 80),
            'url' => mb_substr($url !== '' ? $url : ($children !== [] ? '#' : '/'), 0, 255),
            'auth_only' => $authOnly,
            'icon' => $icon,
            'children' => $children,
        ] + ($allowChildren ? [] : [
            'icon_style' => WebsiteMenuIcons::normalizeSubmenuIconStyle(
                is_array($item['icon_style'] ?? null) ? $item['icon_style'] : []
            ),
        ]);
    }

    /**
     * Accept `#rrggbb` only; otherwise return $fallback.
     */
    private static function sanitizeColor(string $value, string $fallback): string
    {
        $value = trim($value);

        return preg_match('/^#[0-9A-Fa-f]{6}$/', $value) === 1 ? strtolower($value) : $fallback;
    }

    private static function normalizeLayout(string $value): string
    {
        $value = strtolower(trim($value));

        return isset(self::menuLayoutOptions()[$value]) ? $value : 'auto';
    }

    private static function normalizeBreakpoint(mixed $value): int
    {
        $n = (int) $value;
        if ($n < 320) {
            return 320;
        }
        if ($n > 2000) {
            return 2000;
        }

        return $n > 0 ? $n : 768;
    }
}
