<?php
declare(strict_types=1);

/**
 * FL2c: Puffer voll/leer-Grenzen + Kapazität > 0 (SQLite in-memory).
 *
 * Usage: php bin/production-line-buffers-store-selftest.php
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
    }
}

require dirname(__DIR__) . '/src/Recipe/ProductionLineStore.php';

function assertFl2c(bool $ok, string $msg): void
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
Database::$pdo->exec(
    'CREATE TABLE dg_production_line_buffers (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        line_id INTEGER NOT NULL,
        after_station_id INTEGER NOT NULL UNIQUE,
        capacity_qty REAL NOT NULL,
        min_qty REAL NOT NULL DEFAULT 0,
        unit TEXT NOT NULL DEFAULT "Stk",
        current_qty REAL NOT NULL DEFAULT 0,
        updated_at TEXT DEFAULT CURRENT_TIMESTAMP
    )'
);

$lineId = ProductionLineStore::saveLine(['name' => 'Linie Puffer']);
ProductionLineStore::saveStations($lineId, [
    ['work_center_id' => 1, 'label' => 'A'],
    ['work_center_id' => 2, 'label' => 'B'],
    ['work_center_id' => 3, 'label' => 'C'],
]);

$capReject = false;
try {
    ProductionLineStore::saveBuffers($lineId, [
        ['after_station_order' => 1, 'capacity_qty' => '0'],
    ]);
} catch (InvalidArgumentException $e) {
    $capReject = str_contains($e->getMessage(), 'größer');
}
assertFl2c($capReject, 'Kapazität 0 wird abgelehnt');

$lastReject = false;
try {
    ProductionLineStore::saveBuffers($lineId, [
        ['after_station_order' => 3, 'capacity_qty' => '10'],
    ]);
} catch (InvalidArgumentException $e) {
    $lastReject = str_contains($e->getMessage(), 'after_station_order');
}
assertFl2c($lastReject, 'Puffer nach letzter Station wird abgelehnt');

ProductionLineStore::saveBuffers($lineId, [
    ['after_station_order' => 1, 'capacity_qty' => '100', 'min_qty' => '5', 'unit' => 'kg', 'current_qty' => '0'],
    ['after_station_order' => 2, 'capacity_qty' => '50', 'min_qty' => '0', 'current_qty' => '10'],
]);
$bufs = ProductionLineStore::listBuffers($lineId);
assertFl2c(count($bufs) === 2, 'zwei Puffer (Lücken 1 und 2)');
assertFl2c((float) $bufs[0]['capacity_qty'] === 100.0 && (float) $bufs[0]['min_qty'] === 5.0, 'Puffer1 Kapazität/min');
assertFl2c((int) $bufs[0]['after_station_order'] === 1 && (int) $bufs[1]['after_station_order'] === 2, 'Order 1 dann 2');

$id1 = (int) $bufs[0]['id'];
$id2 = (int) $bufs[1]['id'];

// leer / Starving-Grenze
ProductionLineStore::setBufferCurrentQty($id1, 5);
$bufs = ProductionLineStore::listBuffers($lineId);
assertFl2c((float) $bufs[0]['current_qty'] === 5.0, 'Ist = min_qty (Starving-Grenze)');

ProductionLineStore::setBufferCurrentQty($id1, 0);
assertFl2c((float) ProductionLineStore::listBuffers($lineId)[0]['current_qty'] === 0.0, 'Ist leer (0)');

// voll / Blocking-Grenze
ProductionLineStore::setBufferCurrentQty($id1, 100);
assertFl2c((float) ProductionLineStore::listBuffers($lineId)[0]['current_qty'] === 100.0, 'Ist = Kapazität (voll)');

$overReject = false;
try {
    ProductionLineStore::setBufferCurrentQty($id1, 100.1);
} catch (InvalidArgumentException $e) {
    $overReject = str_contains($e->getMessage(), 'Kapazität');
}
assertFl2c($overReject, 'Ist über Kapazität wird abgelehnt');

ProductionLineStore::setBufferCurrentQty($id2, '50');
assertFl2c((float) ProductionLineStore::listBuffers($lineId)[1]['current_qty'] === 50.0, 'Puffer2 voll');

// Station-Rewrite: Puffer nach Order wiederhergestellt
ProductionLineStore::saveStations($lineId, [
    ['work_center_id' => 1, 'label' => 'A'],
    ['work_center_id' => 2, 'label' => 'B'],
    ['work_center_id' => 3, 'label' => 'C'],
]);
$after = ProductionLineStore::listBuffers($lineId);
assertFl2c(count($after) === 2, 'nach Station-Rewrite: 2 Puffer');
assertFl2c(
    (float) $after[0]['capacity_qty'] === 100.0
    && (float) $after[0]['current_qty'] === 100.0
    && (float) $after[1]['capacity_qty'] === 50.0
    && (float) $after[1]['current_qty'] === 50.0,
    'Puffer-Snapshot Kapazität/Ist erhalten'
);

echo "FL2c Selftest bestanden.\n";
exit(0);
