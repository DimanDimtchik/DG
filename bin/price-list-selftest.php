<?php
declare(strict_types=1);

define('DG_ROOT', dirname(__DIR__));
require_once DG_ROOT . '/src/autoload.php';

$failures = 0;
function assertTrue(bool $ok, string $msg): void
{
    global $failures;
    if ($ok) {
        echo "OK  $msg\n";
        return;
    }
    $failures++;
    echo "FAIL $msg\n";
}

$areas = [
    ['id' => 1, 'name' => 'Kosmetik', 'is_active' => 1, 'sort_order' => 1],
    ['id' => 2, 'name' => 'Inaktiv', 'is_active' => 0, 'sort_order' => 2],
];

// 1) Keine Bereiche an Zeilen → Artikel + Leistungen
$none = PriceListPageRenderer::buildSections([
    ['id' => 1, 'catalog_kind' => 'product', 'title' => 'Creme', 'area_id' => 0, 'sort_order' => 1, 'price_gross' => 10, 'tax_type' => 'normal'],
    ['id' => 2, 'catalog_kind' => 'service', 'title' => 'Beratung', 'area_id' => 0, 'sort_order' => 1, 'work_minutes' => 30, 'price_gross' => 50],
    ['id' => 3, 'catalog_kind' => 'product', 'title' => 'Serum', 'area_id' => 99, 'sort_order' => 2], // unbekannter Bereich
], $areas);
assertTrue(count($none) === 2, 'ohne Bereich: 2 Abschnitte');
assertTrue(($none[0]['key'] ?? '') === 'products' && ($none[0]['title'] ?? '') === 'Artikel', 'ohne Bereich: Artikel zuerst');
assertTrue(($none[1]['key'] ?? '') === 'services' && ($none[1]['title'] ?? '') === 'Leistungen', 'ohne Bereich: Leistungen');
assertTrue(count($none[0]['items']) === 2, 'beide Produkte inkl. orphan area_id');
assertTrue(empty($none[0]['show_kind']), 'Art-Spalte aus bei Kind-Tabellen');

// 2) Alle mit Bereich → nur Bereichstabellen
$allArea = PriceListPageRenderer::buildSections([
    ['id' => 1, 'catalog_kind' => 'service', 'title' => 'Facial', 'area_id' => 1, 'sort_order' => 2],
    ['id' => 2, 'catalog_kind' => 'product', 'title' => 'Maske', 'area_id' => 1, 'sort_order' => 1],
], $areas);
assertTrue(count($allArea) === 1, 'alle mit Bereich: 1 Abschnitt');
assertTrue(($allArea[0]['key'] ?? '') === 'area-1', 'Bereichsschlüssel');
assertTrue(($allArea[0]['title'] ?? '') === 'Kosmetik', 'Bereichstitel');
assertTrue(!empty($allArea[0]['show_kind']), 'Art-Spalte in Bereichstabelle');
assertTrue(($allArea[0]['items'][0]['catalog_kind'] ?? '') === 'service', 'Service vor Product in Bereich');
assertTrue(($allArea[0]['items'][0]['title'] ?? '') === 'Facial', 'erste Zeile Facial');

// 3) Gemischt
$mixed = PriceListPageRenderer::buildSections([
    ['id' => 1, 'catalog_kind' => 'service', 'title' => 'Facial', 'area_id' => 1, 'sort_order' => 1],
    ['id' => 2, 'catalog_kind' => 'product', 'title' => 'Creme', 'area_id' => 0, 'sort_order' => 1],
    ['id' => 3, 'catalog_kind' => 'service', 'title' => 'Extra', 'area_id' => 0, 'sort_order' => 1],
], $areas);
assertTrue(count($mixed) === 3, 'gemischt: Bereich + Artikel + Leistungen');
assertTrue(($mixed[0]['key'] ?? '') === 'area-1', 'gemischt: Bereich zuerst');
assertTrue(($mixed[1]['key'] ?? '') === 'products', 'gemischt: Rest Artikel');
assertTrue(($mixed[2]['key'] ?? '') === 'services', 'gemischt: Rest Leistungen');
assertTrue(count($mixed[1]['items']) === 1 && ($mixed[1]['items'][0]['title'] ?? '') === 'Creme', 'Rest-Artikel');
assertTrue(count($mixed[2]['items']) === 1 && ($mixed[2]['items'][0]['title'] ?? '') === 'Extra', 'Rest-Leistung');

// 4) Inaktiver Bereich zählt nicht
$inactiveArea = PriceListPageRenderer::buildSections([
    ['id' => 1, 'catalog_kind' => 'service', 'title' => 'X', 'area_id' => 2, 'sort_order' => 1],
], $areas);
assertTrue(count($inactiveArea) === 1 && ($inactiveArea[0]['key'] ?? '') === 'services', 'inaktiver Bereich → Leistungen');

// 5) HTML-Render leer
ob_start();
PriceListPageRenderer::renderSections([]);
$htmlEmpty = (string) ob_get_clean();
assertTrue(str_contains($htmlEmpty, 'ws-pricelist__empty'), 'leerer Hinweis');

ob_start();
PriceListPageRenderer::renderSections($mixed);
$html = (string) ob_get_clean();
assertTrue(str_contains($html, 'Kosmetik'), 'HTML Bereichstitel');
assertTrue(str_contains($html, 'Artikel'), 'HTML Artikel');
assertTrue(str_contains($html, 'Leistungen'), 'HTML Leistungen');
assertTrue(str_contains($html, 'Facial'), 'HTML Zeile');
assertTrue(str_contains($html, 'ws-pricelist__table'), 'HTML Tabelle');

$layout = PriceListWebsiteService::pageLayout();
assertTrue(($layout['page_kind'] ?? '') === 'price_list', 'page_kind');
assertTrue(
    json_encode($layout) !== false && str_contains((string) json_encode($layout), 'price_list'),
    'Layout enthält price_list-Block'
);

if ($failures > 0) {
    echo "FAILED: $failures\n";
    exit(1);
}
echo "ALL OK\n";
