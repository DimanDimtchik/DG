<?php
declare(strict_types=1);

/**
 * Rechtstexte — Mehrprodukt-Modus und Produktgruppen (Tabs auf Pflichtseiten).
 */
final class LegalProductSettings
{
    public const STORE_KEY = 'legal_products';

    public const DEFAULT_PRODUCT_KEY = 'allgemein';

    /** @var array<string, string> slug => Seitentitel */
    public const LEGAL_PAGES = [
        'impressum' => 'Impressum',
        'datenschutz' => 'Datenschutzerklärung',
        'agb' => 'Allgemeine Geschäftsbedingungen',
        'widerruf' => 'Widerrufsbelehrung',
    ];

    /**
     * @return array{multi_product_enabled: bool, product_groups: list<array{key: string, label: string}>}
     */
    public static function defaults(): array
    {
        return [
            'multi_product_enabled' => false,
            'product_groups' => [],
        ];
    }

    /**
     * @return array{multi_product_enabled: bool, product_groups: list<array{key: string, label: string}>}
     */
    public static function config(): array
    {
        $stored = Database::isConfigured()
            ? SettingsStore::get(self::STORE_KEY, self::defaults())
            : self::defaults();

        return [
            'multi_product_enabled' => !empty($stored['multi_product_enabled']),
            'product_groups' => self::normalizeGroups(is_array($stored['product_groups'] ?? null) ? $stored['product_groups'] : []),
        ];
    }

    public static function multiProductEnabled(): bool
    {
        return self::config()['multi_product_enabled'];
    }

    public static function isLegalSlug(string $slug): bool
    {
        return isset(self::LEGAL_PAGES[self::sanitizeSlug($slug)]);
    }

    /**
     * Alle Produkt-Tabs inkl. „Allgemein“.
     *
     * @return list<array{key: string, label: string}>
     */
    public static function allProductTabs(): array
    {
        $tabs = [
            ['key' => self::DEFAULT_PRODUCT_KEY, 'label' => 'Allgemein'],
        ];
        foreach (self::config()['product_groups'] as $group) {
            $tabs[] = $group;
        }

        return $tabs;
    }

    /**
     * Status-Labels für Rechtstext-Tabs (gleiche DB-Werte wie Website-Seiten).
     *
     * @return array<string, string>
     */
    public static function statusOptions(): array
    {
        return [
            WebsitePageRepository::STATUS_PUBLISHED => 'Online',
            WebsitePageRepository::STATUS_DRAFT => 'Entwurf',
            WebsitePageRepository::STATUS_PRIVATE => 'Offline',
        ];
    }

    /**
     * @throws InvalidArgumentException
     */
    public static function saveFromPost(array $input): void
    {
        $enabled = !empty($input['legal_multi_product']);
        $groups = [];
        $keys = $input['legal_product_key'] ?? [];
        $labels = $input['legal_product_label'] ?? [];
        if (!is_array($keys)) {
            $keys = [];
        }
        if (!is_array($labels)) {
            $labels = [];
        }

        foreach ($keys as $i => $rawKey) {
            $key = self::sanitizeProductKey((string) $rawKey);
            $label = trim((string) ($labels[$i] ?? ''));
            if ($key === '' || $key === self::DEFAULT_PRODUCT_KEY) {
                continue;
            }
            if ($label === '') {
                $label = ucfirst($key);
            }
            $groups[] = ['key' => $key, 'label' => $label];
        }

        $groups = self::normalizeGroups($groups);

        SettingsStore::set(self::STORE_KEY, [
            'multi_product_enabled' => $enabled,
            'product_groups' => $groups,
        ]);

        if ($enabled) {
            WebsiteLegalVariantRepository::ensureVariantsForAllProducts();
        }
    }

    public static function sanitizeSlug(string $slug): string
    {
        return WebsitePageRepository::sanitizeSlug($slug);
    }

    public static function sanitizeProductKey(string $key): string
    {
        $key = strtolower(trim($key));
        $key = preg_replace('/[^a-z0-9-]+/', '-', $key) ?? '';
        $key = trim((string) $key, '-');

        return substr($key, 0, 64);
    }

    /**
     * @param list<array{key?: string, label?: string}> $groups
     * @return list<array{key: string, label: string}>
     */
    private static function normalizeGroups(array $groups): array
    {
        $out = [];
        $seen = [];
        foreach ($groups as $group) {
            $key = self::sanitizeProductKey((string) ($group['key'] ?? ''));
            if ($key === '' || $key === self::DEFAULT_PRODUCT_KEY || isset($seen[$key])) {
                continue;
            }
            $label = trim((string) ($group['label'] ?? ''));
            if ($label === '') {
                $label = ucfirst(str_replace('-', ' ', $key));
            }
            $out[] = ['key' => $key, 'label' => $label];
            $seen[$key] = true;
        }

        return $out;
    }
}
