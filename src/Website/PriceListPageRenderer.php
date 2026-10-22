<?php
declare(strict_types=1);

/**
 * Öffentliche Preisliste: aktive Artikel/Leistungen live aus dem Katalog,
 * gruppiert nach Kalender-Bereich bzw. Fallback-Tabellen Artikel / Leistungen.
 */
final class PriceListPageRenderer
{
    public const BLOCK_TYPE = 'price_list';

    /**
     * Baut Abschnitte aus Katalogzeilen und Bereichen (ohne DB — für Tests).
     *
     * Regeln:
     * - Keine gültigen Bereiche an Zeilen → zwei Tabellen Artikel / Leistungen
     * - Alle Zeilen mit Bereich → eine Tabelle pro Bereich
     * - Gemischt → Bereichs-Tabellen + Rest in Artikel / Leistungen
     *
     * @param list<array<string, mixed>> $articles
     * @param list<array<string, mixed>> $areas
     * @return list<array{key: string, title: string, show_kind: bool, items: list<array<string, mixed>>}>
     */
    public static function buildSections(array $articles, array $areas): array
    {
        $areaNames = [];
        foreach ($areas as $area) {
            if (!is_array($area)) {
                continue;
            }
            $id = (int) ($area['id'] ?? 0);
            if ($id < 1) {
                continue;
            }
            if (array_key_exists('is_active', $area) && !(int) $area['is_active']) {
                continue;
            }
            $name = trim((string) ($area['name'] ?? ''));
            if ($name === '') {
                $name = 'Bereich #' . $id;
            }
            $areaNames[$id] = $name;
        }

        /** @var array<int, list<array<string, mixed>>> $byArea */
        $byArea = [];
        $withoutArea = [];
        foreach ($articles as $row) {
            if (!is_array($row)) {
                continue;
            }
            $areaId = (int) ($row['area_id'] ?? 0);
            if ($areaId > 0 && isset($areaNames[$areaId])) {
                $byArea[$areaId][] = $row;
            } else {
                $withoutArea[] = $row;
            }
        }

        $sections = [];
        if ($byArea !== []) {
            $orderedIds = array_keys($areaNames);
            foreach ($orderedIds as $areaId) {
                if (!isset($byArea[$areaId])) {
                    continue;
                }
                $items = self::sortItems($byArea[$areaId]);
                $sections[] = [
                    'key' => 'area-' . $areaId,
                    'title' => $areaNames[$areaId],
                    'show_kind' => true,
                    'items' => $items,
                ];
            }
            // Bereiche mit Artikeln, die nicht mehr in der Area-Liste stehen (sollte nicht vorkommen)
            foreach ($byArea as $areaId => $items) {
                if (isset($areaNames[$areaId])) {
                    continue;
                }
                $sections[] = [
                    'key' => 'area-' . $areaId,
                    'title' => 'Bereich #' . $areaId,
                    'show_kind' => true,
                    'items' => self::sortItems($items),
                ];
            }
        }

        $needKindTables = $byArea === [] || $withoutArea !== [];
        if ($needKindTables) {
            $pool = $byArea === [] ? $articles : $withoutArea;
            $products = [];
            $services = [];
            foreach ($pool as $row) {
                if (!is_array($row)) {
                    continue;
                }
                if (CalendarArticleCatalog::normalizeKind((string) ($row['catalog_kind'] ?? '')) === CalendarArticleCatalog::KIND_PRODUCT) {
                    $products[] = $row;
                } else {
                    $services[] = $row;
                }
            }
            if ($products !== []) {
                $sections[] = [
                    'key' => 'products',
                    'title' => 'Artikel',
                    'show_kind' => false,
                    'items' => self::sortItems($products),
                ];
            }
            if ($services !== []) {
                $sections[] = [
                    'key' => 'services',
                    'title' => 'Leistungen',
                    'show_kind' => false,
                    'items' => self::sortItems($services),
                ];
            }
        }

        return $sections;
    }

    /**
     * Live-Daten aus CRM (aktive Katalogzeilen + aktive Bereiche).
     *
     * @return list<array{key: string, title: string, show_kind: bool, items: list<array<string, mixed>>}>
     */
    public static function sectionsFromCatalog(): array
    {
        $articles = [];
        $areas = [];
        try {
            if (class_exists('CalendarArticleRepository') && Database::isConfigured()) {
                $articles = CalendarArticleRepository::all(true, null);
            }
        } catch (Throwable) {
            $articles = [];
        }
        try {
            if (class_exists('CalendarStaffRepository') && Database::isConfigured()) {
                $areas = CalendarStaffRepository::getAreas(true);
            }
        } catch (Throwable) {
            $areas = [];
        }

        return self::buildSections($articles, $areas);
    }

    /** HTML für Website-Block / Seite. */
    public static function renderHtml(): string
    {
        $sections = self::sectionsFromCatalog();
        ob_start();
        self::renderSections($sections);

        return (string) ob_get_clean();
    }

    /**
     * @param list<array{key: string, title: string, show_kind: bool, items: list<array<string, mixed>>}> $sections
     */
    public static function renderSections(array $sections): void
    {
        echo '<div class="ws-pricelist">';
        if ($sections === []) {
            echo '<p class="ws-pricelist__empty">Derzeit sind keine aktiven Artikel oder Leistungen hinterlegt.</p>';
            echo '</div>';

            return;
        }

        echo '<p class="ws-pricelist__note">Preise brutto (inkl. MwSt.), sofern ausgewiesen. Änderungen im CRM erscheinen hier automatisch.</p>';

        foreach ($sections as $section) {
            $title = (string) ($section['title'] ?? '');
            $items = is_array($section['items'] ?? null) ? $section['items'] : [];
            $showKind = !empty($section['show_kind']);
            if ($items === []) {
                continue;
            }
            echo '<section class="ws-pricelist__section">';
            echo '<h2 class="ws-pricelist__heading">' . View::escape($title) . '</h2>';
            echo '<div class="ws-pricelist__table-wrap">';
            echo '<table class="ws-pricelist__table">';
            echo '<thead><tr>';
            if ($showKind) {
                echo '<th scope="col">Art</th>';
            }
            echo '<th scope="col">Nr.</th>';
            echo '<th scope="col">Bezeichnung</th>';
            echo '<th scope="col">Einheit</th>';
            echo '<th scope="col">Dauer</th>';
            echo '<th scope="col">Preis</th>';
            echo '<th scope="col">MwSt.</th>';
            echo '</tr></thead><tbody>';
            foreach ($items as $row) {
                if (!is_array($row)) {
                    continue;
                }
                $kind = CalendarArticleCatalog::normalizeKind((string) ($row['catalog_kind'] ?? ''));
                $kindLabel = CalendarArticleCatalog::kindLabel($kind);
                $number = trim((string) ($row['article_number'] ?? ''));
                $name = trim((string) ($row['title'] ?? ''));
                $desc = trim((string) ($row['description'] ?? ''));
                $unit = trim((string) ($row['unit'] ?? ''));
                $minutes = (int) ($row['work_minutes'] ?? 0);
                $priceLabel = (string) ($row['price_label'] ?? '');
                if ($priceLabel === '' && isset($row['price_gross'])) {
                    $priceLabel = CalendarArticleValidator::formatPrice((float) $row['price_gross']);
                }
                $taxLabel = (string) ($row['tax_label'] ?? '');
                if ($taxLabel === '' && isset($row['tax_type'])) {
                    $taxLabel = CalendarArticleValidator::taxLabel((string) $row['tax_type']);
                }
                $duration = '';
                if ($kind === CalendarArticleCatalog::KIND_SERVICE && $minutes > 0) {
                    $duration = $minutes . ' Min.';
                }

                echo '<tr>';
                if ($showKind) {
                    echo '<td data-label="Art">' . View::escape($kindLabel) . '</td>';
                }
                echo '<td data-label="Nr.">' . View::escape($number !== '' ? $number : '–') . '</td>';
                echo '<td data-label="Bezeichnung">';
                echo '<span class="ws-pricelist__title">' . View::escape($name !== '' ? $name : '–') . '</span>';
                if ($desc !== '') {
                    echo '<span class="ws-pricelist__desc">' . View::escape($desc) . '</span>';
                }
                echo '</td>';
                echo '<td data-label="Einheit">' . View::escape($unit !== '' ? $unit : '–') . '</td>';
                echo '<td data-label="Dauer">' . View::escape($duration !== '' ? $duration : '–') . '</td>';
                echo '<td data-label="Preis" class="ws-pricelist__price">'
                    . View::escape($priceLabel !== '' ? $priceLabel : '–')
                    . '</td>';
                echo '<td data-label="MwSt.">' . View::escape($taxLabel !== '' ? $taxLabel : '–') . '</td>';
                echo '</tr>';
            }
            echo '</tbody></table></div></section>';
        }
        echo '</div>';
    }

    /**
     * @param list<array<string, mixed>> $items
     * @return list<array<string, mixed>>
     */
    private static function sortItems(array $items): array
    {
        usort($items, static function (array $a, array $b): int {
            $ka = CalendarArticleCatalog::normalizeKind((string) ($a['catalog_kind'] ?? ''));
            $kb = CalendarArticleCatalog::normalizeKind((string) ($b['catalog_kind'] ?? ''));
            // Leistungen vor Artikeln in Bereichstabellen
            if ($ka !== $kb) {
                if ($ka === CalendarArticleCatalog::KIND_SERVICE) {
                    return -1;
                }
                if ($kb === CalendarArticleCatalog::KIND_SERVICE) {
                    return 1;
                }
            }
            $sa = (int) ($a['sort_order'] ?? 0);
            $sb = (int) ($b['sort_order'] ?? 0);
            if ($sa !== $sb) {
                return $sa <=> $sb;
            }

            return strcasecmp((string) ($a['title'] ?? ''), (string) ($b['title'] ?? ''));
        });

        return array_values($items);
    }
}
