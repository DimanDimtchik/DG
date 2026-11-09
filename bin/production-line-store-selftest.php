<?php
declare(strict_types=1);

/**
 * FL1d: ProductionLineStore — 3 Stationen, Reihenfolge stabil (SQLite in-memory).
 *
 * Usage: php bin/production-line-store-selftest.php
 */

if (!function_exists('mb_strlen')) {
    function mb_strlen(string $s, ?string $_ = null): int
    {
        return strlen($s);
    }
}
if (!function_exists('mb_substr')) {
    function mb_substr(string $s, int $start, ?int $length = null, ?string $_ = null): string
    {
        return $length === null ? substr($s, $start) : substr($s, $start, $length);
    }
}

final class Database
{
    public static PDO $pdo;

    public static function isConfigured(): bool
    {
        return true;
    }

    public static function pdo(): PDO
    {
        return self::$pdo;
    }
}

final class MigrationRunner
{
    public static function runPending(): void
    {
        // Schema im Selftest.
    }
}

require dirname(__DIR__) . '/src/Recipe/ProductionLineStore.php';

function assertFl1d(bool $ok, string $msg): void
{
    if (!$ok) {
        fwrite(STDERR, "FEHLER: {$msg}\n");
        exit(1);
    }
    echo "OK: {$msg}\n";
}

Database::$pdo = new PDO('sqlite::memory:');
Database::$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

Database::$pdo->exec(
    'CREATE TABLE dg_production_lines (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL,
        code TEXT NOT NULL DEFAULT "",
        recipe_id INTEGER NULL,
        is_active INTEGER NOT NULL DEFAULT 1,
        notes TEXT NOT NULL DEFAULT "",
        created_at TEXT DEFAULT CURRENT_TIMESTAMP,
        updated_at TEXT DEFAULT CURRENT_TIMESTAMP
    )'
);
Database::$pdo->exec(
    'CREATE TABLE dg_production_line_stations (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        line_id INTEGER NOT NULL,
        work_center_id INTEGER NOT NULL,
        station_order INTEGER NOT NULL,
        label TEXT NOT NULL DEFAULT "",
        created_at TEXT DEFAULT CURRENT_TIMESTAMP,
        updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
        UNIQUE (line_id, station_order),
        UNIQUE (line_id, work_center_id)
    )'
);

$lineId = ProductionLineStore::saveLine([
    'name' => 'Linie Alpha',
    'code' => 'LA',
    'notes' => 'FL1d',
]);
assertFl1d($lineId > 0, 'Linie anlegen');

$lines = ProductionLineStore::listLines(true);
assertFl1d(count($lines) === 1 && (string) $lines[0]['name'] === 'Linie Alpha', 'listLines aktiv');

ProductionLineStore::saveStations($lineId, [
    ['work_center_id' => 10, 'label' => 'Mischer'],
    ['work_center_id' => 20, 'label' => 'Ofen'],
    ['work_center_id' => 30, 'label' => 'Packer'],
]);

$got = ProductionLineStore::getLineWithStations($lineId);
assertFl1d($got !== null && count($got['stations']) === 3, '3 Stationen gespeichert');
assertFl1d(
    (int) $got['stations'][0]['station_order'] === 1
    && (int) $got['stations'][0]['work_center_id'] === 10
    && (int) $got['stations'][1]['station_order'] === 2
    && (int) $got['stations'][1]['work_center_id'] === 20
    && (int) $got['stations'][2]['station_order'] === 3
    && (int) $got['stations'][2]['work_center_id'] === 30,
    'Reihenfolge 1…3 / WC 10→20→30 stabil'
);

// Rewrite: Reihenfolge tauschen
ProductionLineStore::saveStations($lineId, [
    ['work_center_id' => 30, 'label' => 'Packer'],
    ['work_center_id' => 10, 'label' => 'Mischer'],
    ['work_center_id' => 20, 'label' => 'Ofen'],
]);
$got2 = ProductionLineStore::getLineWithStations($lineId);
assertFl1d(
    $got2 !== null
    && (int) $got2['stations'][0]['work_center_id'] === 30
    && (int) $got2['stations'][1]['work_center_id'] === 10
    && (int) $got2['stations'][2]['work_center_id'] === 20
    && (int) $got2['stations'][0]['station_order'] === 1
    && (int) $got2['stations'][2]['station_order'] === 3,
    'Rewrite: neue Reihenfolge stabil'
);

$dupOk = false;
try {
    ProductionLineStore::saveStations($lineId, [
        ['work_center_id' => 10],
        ['work_center_id' => 10],
    ]);
} catch (InvalidArgumentException $e) {
    $dupOk = str_contains($e->getMessage(), 'einmal');
}
assertFl1d($dupOk, 'Doppelter Work Center wird abgelehnt');

ProductionLineStore::deactivateLine($lineId);
assertFl1d(ProductionLineStore::listLines(true) === [], 'Soft-Deaktivieren: nicht in aktiv-Liste');
assertFl1d(count(ProductionLineStore::listLines(false)) === 1, 'Soft-Deaktivieren: Zeile bleibt');

$noDelete = !method_exists(ProductionLineStore::class, 'deleteLine')
    && !method_exists(ProductionLineStore::class, 'delete');
assertFl1d($noDelete, 'kein Hard-DELETE am Store');

echo "FL1d Selftest bestanden.\n";
exit(0);
