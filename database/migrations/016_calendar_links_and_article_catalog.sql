-- Leistungen: Katalogfelder; Bereiche ↔ Abteilungen; Kalender-Mitarbeiter ↔ Kontakte

ALTER TABLE dg_calendar_articles
    ADD COLUMN IF NOT EXISTS article_number VARCHAR(64) NOT NULL DEFAULT '' AFTER id,
    ADD COLUMN IF NOT EXISTS gtin VARCHAR(14) NOT NULL DEFAULT '' AFTER article_number,
    ADD COLUMN IF NOT EXISTS description TEXT NULL AFTER title,
    ADD COLUMN IF NOT EXISTS note TEXT NULL AFTER description,
    ADD COLUMN IF NOT EXISTS unit VARCHAR(64) NOT NULL DEFAULT 'Stück' AFTER note,
    ADD COLUMN IF NOT EXISTS tax_type VARCHAR(32) NOT NULL DEFAULT 'ust19' AFTER unit,
    ADD COLUMN IF NOT EXISTS price_gross DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER tax_type;

UPDATE dg_calendar_articles
SET article_number = CONCAT('L-', LPAD(id, 4, '0'))
WHERE article_number = '' OR article_number IS NULL;

ALTER TABLE dg_calendar_articles
    ADD UNIQUE KEY uq_article_number (article_number);

ALTER TABLE dg_calendar_areas
    ADD COLUMN IF NOT EXISTS department_id VARCHAR(64) NOT NULL DEFAULT '' AFTER name,
    ADD KEY idx_department (department_id);

ALTER TABLE dg_calendar_employees
    ADD COLUMN IF NOT EXISTS contact_id INT UNSIGNED NOT NULL DEFAULT 0 AFTER name,
    ADD KEY idx_contact (contact_id);
