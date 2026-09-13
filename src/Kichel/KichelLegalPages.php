<?php
declare(strict_types=1);

/**
 * Pflichtseiten / Rechtstexte — klare Links zum Ansehen und Bearbeiten.
 */
final class KichelLegalPages
{
    /**
     * @return array{
     *   kind: string,
     *   answer: string,
     *   page_links: list<array{title: string, slug: string, view_label: string, view_href: string, edit_label: string, edit_href: string}>,
     *   overview_links: list<array{label: string, href: string}>
     * }|null
     */
    public static function tryAnswer(string $query, array $tokens): ?array
    {
        if (!self::isLegalPagesQuestion($query, $tokens)) {
            return null;
        }

        $base = App::publicBaseUrl();
        $pageLinks = [];
        foreach (LegalProductSettings::LEGAL_PAGES as $slug => $title) {
            $viewHref = ($base !== '' ? $base : '') . '/' . $slug;
            $editHref = self::editHrefForSlug($slug);
            $pageLinks[] = [
                'title' => $title,
                'slug' => $slug,
                'view_label' => 'Öffentlich ansehen',
                'view_href' => $viewHref,
                'edit_label' => 'Im CRM bearbeiten',
                'edit_href' => $editHref,
            ];
        }

        $overviewLinks = [
            ['label' => 'Website → Seiten (Pflichtseiten anlegen)', 'href' => '/app?page=website-seiten'],
            ['label' => 'Einstellungen → Rechtliches / Produkte', 'href' => SettingsRegistry::tabUrl('agb')],
        ];

        $lines = [
            'Pflichtseiten sind Impressum, Datenschutz, AGB und Widerruf.',
            'Für jede Seite: öffentliche URL zum Ansehen und CRM-Link zum Bearbeiten — siehe Links unten.',
        ];
        if (LegalProductSettings::multiProductEnabled()) {
            $lines[] = 'Mehrprodukt-Modus ist aktiv: pro Produktgruppe eigene Tabs (z. B. /datenschutz?produkt=klarwin).';
        } else {
            $lines[] = 'Bearbeiten unter Website → Seiten (jede Pflichtseite) oder Pflichtseiten-Bereich auf der Seiten-Übersicht.';
        }

        return [
            'kind' => 'legal_pages',
            'answer' => implode("\n\n", $lines),
            'page_links' => $pageLinks,
            'overview_links' => $overviewLinks,
        ];
    }

    /**
     * @param list<string> $tokens
     */
    private static function isLegalPagesQuestion(string $query, array $tokens): bool
    {
        $normalized = mb_strtolower($query, 'UTF-8');
        $keywords = [
            'pflichtseite', 'pflichtseiten', 'rechtstext', 'rechtstexte',
            'impressum', 'datenschutz', 'agb', 'widerruf', 'legal',
        ];
        foreach ($keywords as $kw) {
            if (str_contains($normalized, $kw)) {
                return true;
            }
        }
        foreach ($tokens as $token) {
            if (in_array($token, ['pflichtseite', 'pflichtseiten', 'impressum', 'datenschutz', 'agb', 'widerruf'], true)) {
                return true;
            }
            if (str_contains($token, 'pflicht')) {
                return true;
            }
        }

        if (preg_match('/wo\s+(finde|findest|ist|sind).*(pflicht|impressum|datenschutz|recht)/u', $normalized)) {
            return true;
        }

        return false;
    }

    private static function editHrefForSlug(string $slug): string
    {
        if (LegalProductSettings::multiProductEnabled()) {
            return '/app?page=website-recht&slug=' . rawurlencode($slug)
                . '&product=' . rawurlencode(LegalProductSettings::DEFAULT_PRODUCT_KEY);
        }

        if (Database::isConfigured()) {
            $page = WebsitePageRepository::findBySlugAnyStatus($slug);
            if ($page !== null && (int) ($page['id'] ?? 0) > 0) {
                return '/app?page=website-seite-form&action=edit&id=' . (int) $page['id'];
            }
        }

        return '/app?page=website-seiten';
    }
}
