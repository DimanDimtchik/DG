-- Regal: getrennte Stellplätze für Paletten, Kartons, Einheiten

SET NAMES utf8mb4;

ALTER TABLE dg_stock_shelves
    ADD COLUMN slots_pallets INT UNSIGNED NOT NULL DEFAULT 0 AFTER code,
    ADD COLUMN slots_cartons INT UNSIGNED NOT NULL DEFAULT 0 AFTER slots_pallets,
    ADD COLUMN slots_units INT UNSIGNED NOT NULL DEFAULT 0 AFTER slots_cartons;

UPDATE dg_stock_shelves
SET slots_pallets = slot_count
WHERE shelf_type = 'floor_slots' AND slot_count > 0;

UPDATE dg_stock_shelves
SET slots_units = slot_count
WHERE shelf_type <> 'floor_slots' AND slot_count > 0 AND slots_units = 0 AND slots_pallets = 0;

ALTER TABLE dg_stock_places
    ADD COLUMN place_kind ENUM('pallet', 'carton', 'unit') NOT NULL DEFAULT 'unit' AFTER code;

UPDATE dg_stock_places SET place_kind = 'unit' WHERE code REGEXP '^P[0-9]+$';
