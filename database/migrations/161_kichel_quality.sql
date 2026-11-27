-- KH1 — Qualitätsmarkierung / Knowledge-Warteschlange am Kichel-Protokoll
SET NAMES utf8mb4;

ALTER TABLE dg_kichel_log
    ADD COLUMN quality_status VARCHAR(16) NULL DEFAULT NULL AFTER response_json,
    ADD COLUMN quality_note MEDIUMTEXT NULL AFTER quality_status,
    ADD COLUMN quality_marked_at DATETIME NULL AFTER quality_note,
    ADD COLUMN quality_marked_by INT UNSIGNED NULL AFTER quality_marked_at,
    ADD KEY idx_kichel_quality_status (quality_status);
