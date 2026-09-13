<?php
declare(strict_types=1);

/**
 * Öffentliche Rechtstext-Seiten mit Produkt-Tabs anreichern.
 */
final class LegalPagePublicHelper
{
    /**
     * @param array<string, mixed> $page
     * @return array<string, mixed>
     */
    public static function enrichPage(array $page, bool $previewMode = false): array
    {
        $slug = (string) ($page['slug'] ?? '');
        if (!LegalProductSettings::isLegalSlug($slug) || !LegalProductSettings::multiProductEnabled()) {
            return $page;
        }

        WebsiteLegalVariantRepository::ensureDefaultsForPage($slug);
        $variants = WebsiteLegalVariantRepository::listForPublic($slug, $previewMode);
        if ($variants === []) {
            return $page;
        }

        $requested = LegalProductSettings::sanitizeProductKey((string) ($_GET['produkt'] ?? ''));
        $active = null;
        if ($requested !== '') {
            foreach ($variants as $variant) {
                if ($variant['product_key'] === $requested) {
                    $active = $variant;
                    break;
                }
            }
        }
        if ($active === null) {
            $active = $variants[0];
        }

        $page['layout'] = $active['layout'];
        $page['legal_variants'] = array_map(static function (array $variant): array {
            return [
                'key' => $variant['product_key'],
                'label' => $variant['label'],
                'status' => $variant['status'],
                'url' => self::tabUrl($variant['page_slug'], $variant['product_key']),
            ];
        }, $variants);
        $page['legal_active_key'] = $active['product_key'];

        return $page;
    }

    public static function tabUrl(string $pageSlug, string $productKey): string
    {
        $base = '/' . ltrim(LegalProductSettings::sanitizeSlug($pageSlug), '/');
        if ($productKey === '' || $productKey === LegalProductSettings::DEFAULT_PRODUCT_KEY) {
            return $base;
        }

        return $base . '?produkt=' . rawurlencode($productKey);
    }
}
