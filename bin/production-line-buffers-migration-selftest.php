<?php
declare(strict_types=1);

/**
 * FL2a: Migration Puffer vorhanden und laut FL1a-Vertrag.
 *
 * Usage: php bin/production-line-buffers-migration-selftest.php
 */

function assertFl2a(bool $ok, string $msg): void
{
    if (!$ok) {
        fwrite(STDERR, "FEHLER: {$msg}\n");
        exit(1);
    }
    echo "OK: {$msg}\n";
}

$root = dirname(__DIR__);
$path = $root . '/database/migrations/146_production_line_buffers.sql';
assertFl2a(is_file($path), '146_production_line_buffers.sql vorhanden');

$sql = (string) file_get_contents($path);
assertFl2a(str_contains($sql, 'CREATE TABLE IF NOT EXISTS dg_production_line_buffers'), 'Tabelle dg_production_line_buffers');
assertFl2a(str_contains($sql, 'after_station_id'), 'after_station_id');
assertFl2a(str_contains($sql, 'capacity_qty'), 'capacity_qty');
assertFl2a(str_contains($sql, 'min_qty'), 'min_qty');
assertFl2a(str_contains($sql, 'current_qty'), 'current_qty');
assertFl2a(str_contains($sql, 'uq_production_line_buffer_after_station'), 'UNIQUE after_station_id');
assertFl2a(str_contains($sql, 'ON DELETE CASCADE'), 'CASCADE auf line/station');
assertFl2a(!str_contains($sql, 'CREATE TABLE IF NOT EXISTS dg_production_lines'), 'nur Puffer-Migration (keine lines)');

echo "FL2a Selftest bestanden.\n";
exit(0);
