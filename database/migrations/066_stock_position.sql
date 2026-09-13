-- Lagerplatz: Positionscode Ort-Halle-Regal-Platz

SET NAMES utf8mb4;

ALTER TABLE dg_calendar_articles
    ADD COLUMN stock_ort VARCHAR(32) NOT NULL DEFAULT '' AFTER min_stock,
    ADD COLUMN stock_halle VARCHAR(32) NOT NULL DEFAULT '' AFTER stock_ort,
    ADD COLUMN stock_regal VARCHAR(32) NOT NULL DEFAULT '' AFTER stock_halle,
    ADD COLUMN stock_platz VARCHAR(32) NOT NULL DEFAULT '' AFTER stock_regal;
