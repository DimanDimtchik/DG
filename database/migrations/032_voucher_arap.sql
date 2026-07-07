-- Rechnungsabgrenzung (ARAP / PRAP) — Verteilung auf Geschäftsjahre

ALTER TABLE dg_vouchers
    ADD COLUMN arap_enabled TINYINT(1) NOT NULL DEFAULT 0 AFTER delivery_date,
    ADD COLUMN arap_current_year_percent TINYINT UNSIGNED NOT NULL DEFAULT 100 AFTER arap_enabled,
    ADD COLUMN arap_next_year_percent TINYINT UNSIGNED NOT NULL DEFAULT 0 AFTER arap_current_year_percent;
