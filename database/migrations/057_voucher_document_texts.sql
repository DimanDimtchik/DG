-- Freitext vor/nach Rechnungspositionen (Einleitung, Kleinunternehmer-Hinweis, …)
SET NAMES utf8mb4;

ALTER TABLE dg_vouchers
    ADD COLUMN document_intro_text TEXT NULL AFTER notes,
    ADD COLUMN document_footer_text TEXT NULL AFTER document_intro_text;
