-- Artikel & Leistungen: Sichtbarkeit in der Online-Preisliste (Website)
-- Standard: an (1). Aus = Eintrag erscheint nicht auf der öffentlichen Preisliste.

SET NAMES utf8mb4;

ALTER TABLE dg_calendar_articles
    ADD COLUMN price_list_online TINYINT(1) NOT NULL DEFAULT 1 AFTER is_active;
