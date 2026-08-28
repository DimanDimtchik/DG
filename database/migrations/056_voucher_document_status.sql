-- Dokumentstatus in der Verkaufskette (versendet, angenommen, …)
SET NAMES utf8mb4;

ALTER TABLE dg_vouchers
    ADD COLUMN document_status VARCHAR(32) NOT NULL DEFAULT '' AFTER document_kind,
    ADD KEY idx_voucher_document_status (document_status);
