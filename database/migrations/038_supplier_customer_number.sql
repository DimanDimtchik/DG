-- Kundennummer beim Lieferanten (unsere Kontonummer beim Aussteller, z. B. auf Eingangsrechnungen)
SET NAMES utf8mb4;

ALTER TABLE dg_contacts
    ADD COLUMN supplier_customer_number VARCHAR(50) NOT NULL DEFAULT '' AFTER supplier_number;
