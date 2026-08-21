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
     * @return array{items: list<array{label: string, url: string, auth_only: bool, children: list<array<string, mixed>>}>}
     */
    public static function menuDefaults(): array
    {
        return [
            'items' => [
                [
                    'label' => 'Start',
                    'url' => '/',
                    'auth_only' => false,
                    'children' => [],
                ],
            ],
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
     * @return array{items: list<array<string, mixed>>}
     */
    public static function menu(): array
    {
        $stored = SettingsStore::get(self::MENU_KEY, self::menuDefaults());
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

        return ['items' => $items];
    }

    /**
     * Menu for the public website (filters auth_only when guest).
     *
     * @return array{items: list<array<string, mixed>>}
     */
    public static function publicMenu(?bool $loggedIn = null): array
    {
        $loggedIn ??= class_exists('AuthService') && AuthService::check();
        $items = [];
        foreach (self::menu()['items'] as $item) {
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

        return ['items' => $items];
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
        SettingsStore::set(self::MENU_KEY, ['items' => $items]);
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
     * @return array{label: string, url: string, auth_only: bool, children: list<array<string, mixed>>}|null
     */
    private static function normalizeMenuItem(array $item, bool $allowChildren): ?array
    {
        $label = trim((string) ($item['label'] ?? ''));
        $url = trim((string) ($item['url'] ?? ''));
        $authOnly = !empty($item['auth_only']) && (string) ($item['auth_only'] ?? '') !== '0';

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
            'children' => $children,
        ];
    }

    /**
     * Accept `#rrggbb` only; otherwise return $fallback.
     */
    private static function sanitizeColor(string $value, string $fallback): string
    {
        $value = trim($value);

        return preg_match('/^#[0-9A-Fa-f]{6}$/', $value) === 1 ? strtolower($value) : $fallback;
    }
}
