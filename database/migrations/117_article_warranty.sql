-- RG1: Garantie / Gewährleistung an Artikel & Leistungen (Port von Cloud 114)
SET NAMES utf8mb4;

ALTER TABLE dg_calendar_articles
    ADD COLUMN IF NOT EXISTS warranty_kind VARCHAR(32) NOT NULL DEFAULT 'none' AFTER note,
    ADD COLUMN IF NOT EXISTS warranty_months SMALLINT UNSIGNED NOT NULL DEFAULT 0 AFTER warranty_kind,
    ADD COLUMN IF NOT EXISTS warranty_text TEXT NULL AFTER warranty_months;
