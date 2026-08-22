-- Vordefinierte gesetzliche Hinweise (JSON-Liste von Schlüsseln)
SET NAMES utf8mb4;

ALTER TABLE dg_vouchers
    ADD COLUMN document_legal_clauses JSON NULL AFTER document_footer_text;
