-- Belegkette: Dokumentart + Verknüpfung (Angebot → … → Schlussrechnung)
SET NAMES utf8mb4;

ALTER TABLE dg_vouchers
    ADD COLUMN document_kind VARCHAR(32) NOT NULL DEFAULT '' AFTER voucher_type,
    ADD COLUMN parent_voucher_id INT UNSIGNED NULL AFTER document_kind,
    ADD KEY idx_voucher_parent (parent_voucher_id),
    ADD CONSTRAINT fk_voucher_parent FOREIGN KEY (parent_voucher_id) REFERENCES dg_vouchers (id) ON DELETE SET NULL;
