-- Flyer-Vorlagen (Gutschein-Formular) + Verknüpfung am Gutschein
-- Booking-QR-Flyer bleiben in CalendarEmbedSettings; gemeinsame PHP-Klasse: FlyerService
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS dg_flyer_templates (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    purpose VARCHAR(32) NOT NULL DEFAULT 'gift_form',
    slug VARCHAR(64) NOT NULL,
    title VARCHAR(191) NOT NULL,
    occasion VARCHAR(64) NOT NULL DEFAULT 'general',
    headline VARCHAR(255) NOT NULL DEFAULT '',
    subtitle VARCHAR(255) NOT NULL DEFAULT '',
    accent_color VARCHAR(32) NOT NULL DEFAULT '',
    bg_media_id INT UNSIGNED NULL,
    layout_json TEXT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    sort_order SMALLINT NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_flyer_purpose_slug (purpose, slug),
    KEY idx_flyer_purpose_active (purpose, is_active, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE dg_gift_vouchers
    ADD COLUMN IF NOT EXISTS flyer_template_id INT UNSIGNED NULL AFTER notes;

INSERT INTO dg_flyer_templates (purpose, slug, title, occasion, headline, subtitle, accent_color, sort_order)
SELECT * FROM (
    SELECT 'gift_form' AS purpose, 'standard' AS slug, 'Standard' AS title, 'general' AS occasion,
           'Gutschein' AS headline, 'Einlösbar für Artikel und Leistungen' AS subtitle, '#2f5d50' AS accent_color, 10 AS sort_order
    UNION ALL
    SELECT 'gift_form', 'geburtstag', 'Geburtstag', 'birthday',
           'Alles Gute zum Geburtstag', 'Ein Geschenk zum Einlösen', '#8b4513', 20
    UNION ALL
    SELECT 'gift_form', 'frauentag', '8. März', 'march8',
           'Zum Internationalen Frauentag', 'Ein Geschenk zum Einlösen', '#9b2d4a', 30
) AS seed
WHERE NOT EXISTS (SELECT 1 FROM dg_flyer_templates LIMIT 1);
