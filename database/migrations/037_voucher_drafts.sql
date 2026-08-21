-- Beleg-Entwürfe (z. B. Datei hochgeladen, Formular noch nicht fertig)

ALTER TABLE dg_vouchers
    ADD COLUMN is_draft TINYINT(1) NOT NULL DEFAULT 0 AFTER voucher_type;

CREATE INDEX idx_voucher_draft ON dg_vouchers (is_draft);
