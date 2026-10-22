<?php
declare(strict_types=1);

/**
 * Vorinstallierte Website-Seite „Preislisten“ — live aus Artikel-/Leistungskatalog.
 */
final class PriceListWebsiteService
{
    public const PAGE_SLUG = 'preislisten';

    public static function ensureDraftWebsitePage(?int $userId = null): void
    {
        if (!Database::isConfigured()) {
            return;
        }
        $existing = WebsitePageRepository::findBySlugAnyStatus(self::PAGE_SLUG);
        if ($existing === null) {
            WebsitePageRepository::save([
                'title' => 'Preislisten',
                'slug' => self::PAGE_SLUG,
                'status' => WebsitePageRepository::STATUS_PUBLISHED,
                'layout' => self::pageLayout(),
            ], null, $userId);
        }
        self::ensureMenuItem();
    }

    /** Menüpunkt „Preislisten“ nachziehen (ohne Duplikat). */
    public static function ensureMenuItem(): void
    {
        if (!Database::isConfigured()) {
            return;
        }
        $menu = SettingsStore::get('website.menu', []);
        if (!is_array($menu)) {
            $menu = [];
        }
        $items = is_array($menu['items'] ?? null) ? $menu['items'] : [];
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }
            $url = rtrim((string) ($item['url'] ?? ''), '/');
            if ($url === '/' . self::PAGE_SLUG) {
                return;
            }
        }

        $entry = [
            'label' => 'Preislisten',
            'url' => '/' . self::PAGE_SLUG,
            'auth_only' => false,
            'icon' => 'auto',
            'children' => [],
        ];
        if ($items === []) {
            $items[] = ['label' => 'Start', 'url' => '/', 'auth_only' => false, 'icon' => 'auto', 'children' => []];
        }
        $insertAt = 1;
        foreach ($items as $i => $item) {
            if (!is_array($item)) {
                continue;
            }
            $url = rtrim((string) ($item['url'] ?? ''), '/');
            if ($url === '/terminkalender' || $url === '/apps') {
                $insertAt = $i + 1;
            }
        }
        if ($insertAt > count($items)) {
            $items[] = $entry;
        } else {
            array_splice($items, $insertAt, 0, [$entry]);
        }
        $menu['items'] = $items;
        if (!isset($menu['layout'])) {
            $menu['layout'] = 'auto';
        }
        SettingsStore::set('website.menu', $menu);
    }

    /**
     * @return array{rows: list<array<string, mixed>>, page_kind?: string}
     */
    public static function pageLayout(): array
    {
        return [
            'page_kind' => 'price_list',
            'rows' => [
                [
                    'id' => 'row-pricelist-intro',
                    'columns' => [[
                        'id' => 'col-pricelist-intro',
                        'width' => 12,
                        'blocks' => [
                            [
                                'id' => 'blk-pricelist-h',
                                'type' => 'heading',
                                'text' => 'Preislisten',
                                'level' => 'h1',
                            ],
                            [
                                'id' => 'blk-pricelist-t',
                                'type' => 'text',
                                'text' => 'Hier finden Sie unsere aktuellen Artikel und Leistungen. '
                                    . 'Die Übersicht aktualisiert sich automatisch, wenn Einträge im CRM geändert werden.',
                            ],
                        ],
                    ]],
                ],
                [
                    'id' => 'row-pricelist-live',
                    'columns' => [[
                        'id' => 'col-pricelist-live',
                        'width' => 12,
                        'blocks' => [
                            [
                                'id' => 'blk-pricelist-live',
                                'type' => PriceListPageRenderer::BLOCK_TYPE,
                            ],
                        ],
                    ]],
                ],
            ],
        ];
    }
}
