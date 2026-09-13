<?php
declare(strict_types=1);

/**
 * Pflichtseiten / Rechtstexte — klare Links zum Ansehen und Bearbeiten.
 * Läuft auch ohne Mehrprodukt-Branch (LegalProductSettings optional).
 */
final class KichelLegalPages
{
    /** @var array<string, string> slug => Titel */
    private const PAGES = [
        'impressum' => 'Impressum',
        'datenschutz' => 'Datenschutzerklärung',
        'agb' => 'Allgemeine Geschäftsbedingungen',
        'widerruf' => 'Widerrufsbelehrung',
    ];

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
        foreach (self::PAGES as $slug => $title) {
            $viewHref = ($base !== '' ? $base : '') . '/' . $slug;
            $pageLinks[] = [
                'title' => $title,
                'slug' => $slug,
                'view_label' => 'Öffentlich ansehen',
                'view_href' => $viewHref,
                'edit_label' => 'Im CRM bearbeiten',
                'edit_href' => self::editHrefForSlug($slug),
            ];
        }

        $overviewLinks = [
            ['label' => 'Website → Seiten (Pflichtseiten anlegen)', 'href' => '/app?page=website-seiten'],
        ];
        if (class_exists('SettingsRegistry')) {
            $overviewLinks[] = [
                'label' => 'Einstellungen → Rechtliches / Produkte',
                'href' => SettingsRegistry::tabUrl('agb'),
            ];
        }

        $lines = [
            'Pflichtseiten: Impressum, Datenschutz, AGB und Widerruf.',
            'Unten finden Sie pro Seite den Link zum Ansehen (Website) und zum Bearbeiten (CRM).',
        ];
        if (self::multiProductEnabled()) {
            $lines[] = 'Mehrprodukt-Modus ist aktiv — je Produktgruppe eigene Tabs (z. B. /datenschutz?produkt=klarwin).';
        } else {
            $lines[] = 'Bearbeiten: Website → Seiten → jeweilige Seite öffnen.';
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

    private static function multiProductEnabled(): bool
    {
        if (!is_readable(DG_ROOT . '/src/Legal/LegalProductSettings.php')) {
            return false;
        }

        return LegalProductSettings::multiProductEnabled();
    }

    private static function editHrefForSlug(string $slug): string
    {
        if (self::multiProductEnabled()) {
            return '/app?page=website-recht&slug=' . rawurlencode($slug)
                . '&product=' . rawurlencode('allgemein');
        }

        if (Database::isConfigured() && class_exists('WebsitePageRepository')) {
            $page = WebsitePageRepository::findBySlugAnyStatus($slug);
            if ($page !== null && (int) ($page['id'] ?? 0) > 0) {
                return '/app?page=website-seite-form&action=edit&id=' . (int) $page['id'];
            }
        }

        return '/app?page=website-seiten';
    }
}
