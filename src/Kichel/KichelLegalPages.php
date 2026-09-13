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
                'view_label' => 'Ansehen',
                'view_href' => $viewHref,
                'edit_label' => 'Bearbeiten',
                'edit_href' => self::editHrefForSlug($slug),
            ];
        }

        return [
            'kind' => 'legal_pages',
            'answer' => self::introText($query),
            'page_links' => $pageLinks,
            'overview_links' => [],
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

    private static function introText(string $query): string
    {
        $normalized = mb_strtolower($query, 'UTF-8');
        $short = [
            'impressum' => 'Impressum',
            'datenschutz' => 'Datenschutz',
            'agb' => 'AGB',
            'widerruf' => 'Widerruf',
        ];
        $mentioned = [];
        foreach ($short as $slug => $label) {
            if (str_contains($normalized, $slug)) {
                $mentioned[] = $label;
            }
        }

        if ($mentioned !== []) {
            return 'Du meinst vermutlich ' . self::naturalList($mentioned)
                . ' — das sind Pflichtseiten auf deiner Website:';
        }

        return 'Du meinst vermutlich Impressum, Datenschutz, AGB oder Widerruf — '
            . 'die Pflichtseiten deiner Website:';
    }

    /**
     * @param list<string> $items
     */
    private static function naturalList(array $items): string
    {
        $items = array_values(array_unique($items));
        if (count($items) === 1) {
            return $items[0];
        }
        if (count($items) === 2) {
            return $items[0] . ' und ' . $items[1];
        }
        $last = array_pop($items);

        return implode(', ', $items) . ' und ' . $last;
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
