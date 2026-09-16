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
        'slots_pallets' => 1,
        'slots_cartons' => 1,
        'slots_units' => 2,
        'is_active' => 1,
    ]);
    $places = StockStructureRepository::placesForShelf($shelfId);
    if (count($places) < 4) {
        $errors[] = 'Stellplätze wurden nicht automatisch angelegt (Pal+Kart+Einh).';
    }
    $free = StockPlaceService::suggestFreePlaces($hallId, null, 5);
    if ($free === []) {
        $errors[] = 'Keine freien flexiblen Plätze gefunden.';
    }
    StockStructureRepository::deleteShelf($shelfId);
    StockStructureRepository::deleteHall($hallId);
    StockStructureRepository::deleteLocation($locId);
}

$hasPackages = $pdo->query("SHOW TABLES LIKE 'dg_stock_packages'")->fetchColumn() !== false;
if (!$hasPackages) {
    $errors[] = 'Migration 069 nicht angewendet (dg_stock_packages fehlt).';
}

if ($hasPackages && $hasStructure) {
    $testGtin = '4006381333931';
    $pdo->prepare('UPDATE dg_calendar_articles SET gtin = :gtin, track_stock = 1 WHERE id = :id')
        ->execute(['gtin' => $testGtin, 'id' => $articleId]);

    $locId = StockStructureRepository::saveLocation([
        'code' => 'TST-BC-' . date('His'),
        'name' => 'Barcode Test',
        'function_text' => 'Test',
        'is_active' => 1,
    ]);
    $hallId = StockStructureRepository::saveHall([
        'location_id' => $locId,
        'code' => 'H1',
        'usage_text' => 'Test',
        'is_active' => 1,
    ]);
    $shelfId = StockStructureRepository::saveShelf([
        'location_id' => $locId,
        'hall_id' => $hallId,
        'code' => 'R1',
        'slots_pallets' => 1,
        'slots_cartons' => 0,
        'slots_units' => 0,
        'is_active' => 1,
    ]);
    $places = StockStructureRepository::placesForShelf($shelfId);
    $placeId = (int) ($places[0]['id'] ?? 0);
    if ($placeId < 1) {
        $errors[] = 'Kein Stellplatz für Barcode-Test.';
    } else {
        $placeBarcode = StockBarcodeService::generatePlaceBarcode($placeId);
        $resolvedPlace = StockBarcodeService::resolve($placeBarcode);
        if (($resolvedPlace['type'] ?? '') !== 'place') {
            $errors[] = 'Platz-Strichcode nicht auflösbar.';
        }

        $resolvedArticle = StockBarcodeService::resolve($testGtin);
        if (($resolvedArticle['type'] ?? '') !== 'article') {
            $errors[] = 'Artikel-Strichcode (GTIN) nicht auflösbar.';
        }

        $packageId = StockPackageRepository::create($articleId, 3.0, $placeId, 'TST-KRT-SELFTEST');
        $resolvedPackage = StockBarcodeService::resolve('TST-KRT-SELFTEST');
        if (($resolvedPackage['type'] ?? '') !== 'package') {
            $errors[] = 'Karton-Strichcode nicht auflösbar.';
        }

        $beforeReceipt = (float) (CalendarArticleRepository::findById($articleId)['stock_qty'] ?? 0);
        StockReceiptIssueService::processReceipt([
            ['article_id' => $articleId, 'quantity' => 2, 'place_id' => $placeId],
        ], null, null, 'Selftest WE');
        $afterReceipt = (float) (CalendarArticleRepository::findById($articleId)['stock_qty'] ?? 0);
        if ($afterReceipt <= $beforeReceipt) {
            $errors[] = 'Wareneingang hat Bestand nicht erhöht.';
        }

        StockReceiptIssueService::processIssue([
            ['package_barcode' => 'TST-KRT-SELFTEST'],
        ], null, null, 'Selftest WA Karton');
        $pkgAfter = StockPackageRepository::findByBarcode('TST-KRT-SELFTEST');
        if ($pkgAfter !== null && ($pkgAfter['status'] ?? '') !== 'issued') {
            $errors[] = 'Karton nach Warenausgang nicht als issued markiert.';
        }

        $pdo->prepare('DELETE FROM dg_stock_packages WHERE id = :id')->execute(['id' => $packageId]);
    }

    StockStructureRepository::deleteShelf($shelfId);
    StockStructureRepository::deleteHall($hallId);
    StockStructureRepository::deleteLocation($locId);
}

if ($hasStructure) {
    $locLabels = StockLabelService::collectLabels(['level' => StockLabelService::LEVEL_LOCATION]);
    if ($locLabels === []) {
        $errors[] = 'Etiketten: keine Lagerorte für Druck.';
    }
    $firstLoc = StockStructureRepository::allLocations()[0] ?? null;
    if (is_array($firstLoc)) {
        $audit = StockPlaceAuditService::audit((string) ($firstLoc['code'] ?? ''));
        if (($audit['scan_type'] ?? '') !== 'location') {
            $errors[] = 'Platz-Check Audit für Lagerort fehlgeschlagen.';
        }
        $auditManual = StockPlaceAuditService::auditById(StockLabelService::LEVEL_LOCATION, (int) ($firstLoc['id'] ?? 0));
        if (($auditManual['scan_type'] ?? '') !== 'location') {
            $errors[] = 'Platz-Check Audit (manuell) für Lagerort fehlgeschlagen.';
        }
    }
}

// Phase 1: Reservierungen
$hasReservations = $pdo->query("SHOW TABLES LIKE 'dg_stock_reservations'")->fetchColumn() !== false;
if (!$hasReservations) {
    $errors[] = 'Migration 079 nicht angewendet (dg_stock_reservations fehlt).';
} else {
    $pdo->prepare('DELETE FROM dg_stock_reservations WHERE article_id = :aid')->execute(['aid' => $articleId]);
    $pdo->prepare(
        "INSERT INTO dg_stock_reservations (article_id, voucher_id, quantity, status)
         VALUES (:aid, 0, 3.000, 'active')"
    )->execute(['aid' => $articleId]);
    $reserved = StockReservationService::reservedQty($articleId);
    if (abs($reserved - 3.0) > 0.001) {
        $errors[] = 'reservedQty erwartet 3, ist ' . $reserved;
    }
    $snap = StockAvailabilityService::snapshot($articleId);
    if ($snap === null || abs($snap['reserved'] - 3.0) > 0.001) {
        $errors[] = 'Availability snapshot reserved falsch.';
    }
    $pdo->prepare('DELETE FROM dg_stock_reservations WHERE article_id = :aid')->execute(['aid' => $articleId]);
    $overviewRes = StockMovementService::stockOverview(false);
    $foundAvail = false;
    foreach ($overviewRes as $row) {
        if ((int) ($row['id'] ?? 0) === $articleId && isset($row['available_qty'])) {
            $foundAvail = true;
            break;
        }
    }
    if (!$foundAvail) {
        $errors[] = 'stockOverview ohne available_qty.';
    }
}

if ($errors !== []) {
    foreach ($errors as $err) {
        fwrite(STDERR, 'FAIL: ' . $err . "\n");
    }
    exit(1);
}

echo "stock-selftest: OK (Artikel #{$articleId}, Bestand {$qty}, Lagerstruktur, Etiketten, Reservierung)\n";
