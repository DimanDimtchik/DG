-- Artikel vs. Leistung; Einkauf / Artikelpflege-Rechte

ALTER TABLE dg_calendar_articles
    ADD COLUMN catalog_kind VARCHAR(16) NOT NULL DEFAULT 'service' AFTER article_number;

UPDATE dg_calendar_articles SET catalog_kind = 'service' WHERE catalog_kind = '' OR catalog_kind IS NULL;

ALTER TABLE dg_departments
    ADD COLUMN is_purchasing TINYINT(1) NOT NULL DEFAULT 0 AFTER allow_contact_delete,
    ADD COLUMN allow_article_catalog TINYINT(1) NOT NULL DEFAULT 0 AFTER is_purchasing;

UPDATE dg_departments SET is_purchasing = 1 WHERE id = 'dept-einkauf';
