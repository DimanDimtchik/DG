<?php
declare(strict_types=1);

/**
 * CLI: Lagerführung Stufe A+B — Migration, Bewegung, Beleg, Inventur.
 */
if (!defined('DG_ROOT')) {
    define('DG_ROOT', dirname(__DIR__));
}
require_once DG_ROOT . '/src/autoload.php';

if (!Database::isConfigured()) {
    fwrite(STDERR, "stock-selftest: DB nicht konfiguriert.\n");
    exit(1);
}

MigrationRunner::runPending();

$pdo = Database::pdo();
$errors = [];

$hasStock = $pdo->query("SHOW TABLES LIKE 'dg_stock_movements'")->fetchColumn() !== false;
if (!$hasStock) {
    $errors[] = 'Migration 065 nicht angewendet (dg_stock_movements fehlt).';
}

$articleId = (int) ($pdo->query(
    "SELECT id FROM dg_calendar_articles WHERE catalog_kind = 'product' ORDER BY id ASC LIMIT 1"
)->fetchColumn() ?: 0);

if ($articleId < 1) {
    $pdo->prepare(
        "INSERT INTO dg_calendar_articles
         (article_number, catalog_kind, title, unit, tax_type, price_gross, work_minutes, is_active, track_stock, stock_qty, min_stock)
         VALUES ('TST-LAGER-1', 'product', 'Selftest Lagerartikel', 'Stück', 'ust19', 10.00, 30, 1, 1, 0, 2)"
    )->execute();
    $articleId = (int) $pdo->lastInsertId();
    StockMovementService::recordOpeningBalance($articleId, 10.0, null);
}

$article = CalendarArticleRepository::findById($articleId);
if ($article === null || empty($article['track_stock'])) {
    $pdo->prepare('UPDATE dg_calendar_articles SET track_stock = 1, min_stock = 2 WHERE id = :id')->execute(['id' => $articleId]);
}

StockMovementService::manualAdjust($articleId, -1.0, 'Selftest Korrektur', null);
$afterAdjust = CalendarArticleRepository::findById($articleId);
$qty = (float) ($afterAdjust['stock_qty'] ?? 0);
if ($qty < 0) {
    $errors[] = 'Bestand nach Korrektur unerwartet: ' . $qty;
}

$open = StockInventoryService::openInventories();
foreach ($open as $inv) {
    StockInventoryService::close((int) $inv['id'], null);
}

$invId = StockInventoryService::start(date('Y-m-d'), 'Selftest', null);
StockInventoryService::saveCounts($invId, [$articleId => $qty]);
StockInventoryService::close($invId, null);

$overview = StockMovementService::stockOverview(false);
if ($overview === []) {
    $errors[] = 'stockOverview leer obwohl Artikel mit Lagerführung existiert.';
}

if ($errors !== []) {
    foreach ($errors as $err) {
        fwrite(STDERR, 'FAIL: ' . $err . "\n");
    }
    exit(1);
}

$code = StockPositionCode::compose('WH1', 'H2', 'R3', 'P4');
if ($code !== 'WH1-H2-R3-P4') {
    fwrite(STDERR, "FAIL: Positionscode compose → {$code}\n");
    exit(1);
}

$hasStructure = $pdo->query("SHOW TABLES LIKE 'dg_stock_locations'")->fetchColumn() !== false;
if (!$hasStructure) {
    $errors[] = 'Migration 067 nicht angewendet (dg_stock_locations fehlt).';
}

if ($hasStructure) {
    $testCode = 'TST-LOC-' . date('His');
    $locId = StockStructureRepository::saveLocation([
        'code' => $testCode,
        'name' => 'Selftest Ort',
        'function_text' => 'Testlager',
        'is_active' => 1,
    ]);
    $hallId = StockStructureRepository::saveHall([
        'location_id' => $locId,
        'code' => 'H1',
        'usage_text' => 'Trockenlager',
        'is_active' => 1,
    ]);
    $shelfId = StockStructureRepository::saveShelf([
        'location_id' => $locId,
        'hall_id' => $hallId,
        'code' => 'R1',
        'shelf_type' => 'shelf',
        'slot_count' => 2,
        'is_active' => 1,
    ]);
    $places = StockStructureRepository::placesForShelf($shelfId);
    if (count($places) < 2) {
        $errors[] = 'Stellplätze wurden nicht automatisch angelegt.';
    }
    $free = StockPlaceService::suggestFreePlaces($hallId, null, 5);
    if ($free === []) {
        $errors[] = 'Keine freien flexiblen Plätze gefunden.';
    }
    StockStructureRepository::deleteShelf($shelfId);
    StockStructureRepository::deleteHall($hallId);
    StockStructureRepository::deleteLocation($locId);
}

if ($errors !== []) {
    foreach ($errors as $err) {
        fwrite(STDERR, 'FAIL: ' . $err . "\n");
    }
    exit(1);
}

echo "stock-selftest: OK (Artikel #{$articleId}, Bestand {$qty}, Lagerstruktur)\n";
