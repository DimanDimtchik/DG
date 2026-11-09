<?php
declare(strict_types=1);

/**
 * FL1b: Migration lines+stations vorhanden und laut FL1a-Vertrag.
 *
 * Usage: php bin/production-lines-migration-selftest.php
 */

function assertFl1b(bool $ok, string $msg): void
{
    if (!$ok) {
        fwrite(STDERR, "FEHLER: {$msg}\n");
        exit(1);
    }
    echo "OK: {$msg}\n";
}

$root = dirname(__DIR__);
$path = $root . '/database/migrations/159_production_lines.sql';
assertFl1b(is_file($path), '159_production_lines.sql vorhanden');

$sql = (string) file_get_contents($path);
assertFl1b(!str_contains($sql, 'dg_production_line_buffers'), 'kein Puffer (FL2)');
assertFl1b(str_contains($sql, 'CREATE TABLE IF NOT EXISTS dg_production_lines'), 'Tabelle dg_production_lines');
assertFl1b(str_contains($sql, 'CREATE TABLE IF NOT EXISTS dg_production_line_stations'), 'Tabelle dg_production_line_stations');
assertFl1b(str_contains($sql, 'ON DELETE SET NULL'), 'recipe_id ON DELETE SET NULL');
assertFl1b(str_contains($sql, 'ON DELETE CASCADE'), 'line_id ON DELETE CASCADE');
assertFl1b(str_contains($sql, 'ON DELETE RESTRICT'), 'work_center_id ON DELETE RESTRICT');
assertFl1b(str_contains($sql, 'uq_production_line_station_order'), 'Unique (line_id, station_order)');
assertFl1b(str_contains($sql, 'uq_production_line_station_wc'), 'Unique (line_id, work_center_id)');

echo "FL1b Selftest bestanden.\n";
exit(0);
