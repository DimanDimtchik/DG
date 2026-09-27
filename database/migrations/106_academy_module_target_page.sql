-- Akademie: Zielseite im CRM für „Zum Thema“-Button am Video

ALTER TABLE dg_academy_modules
    ADD COLUMN target_page VARCHAR(100) NOT NULL DEFAULT '' AFTER description;
