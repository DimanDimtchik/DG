-- Multi-Firma MF3: Gewinnermittlung + Rechtsform am KDV-Firmenslot
SET NAMES utf8mb4;

ALTER TABLE dg_kdv_customers
    ADD COLUMN gewinnermittlung VARCHAR(20) NOT NULL DEFAULT ''
        COMMENT 'euer|bilanz|leer' AFTER firm_relation,
    ADD COLUMN company_type VARCHAR(80) NOT NULL DEFAULT ''
        COMMENT 'Rechtsform Freitext' AFTER gewinnermittlung,
    ADD COLUMN tax_number_note VARCHAR(191) NOT NULL DEFAULT ''
        COMMENT 'Steuernummer/USt-Id Hinweis (noch nicht final)' AFTER company_type;
