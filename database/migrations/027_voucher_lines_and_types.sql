-- Belegarten (Lexoffice-Logik) + Betragsaufteilung auf mehrere Konten
SET NAMES utf8mb4;

ALTER TABLE dg_vouchers
    MODIFY voucher_type VARCHAR(32) NOT NULL DEFAULT 'expense';

UPDATE dg_vouchers SET voucher_type = 'expense' WHERE voucher_type IN ('receipt', 'invoice');
UPDATE dg_vouchers SET voucher_type = 'credit' WHERE voucher_type = 'credit';

CREATE TABLE IF NOT EXISTS dg_voucher_lines (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    voucher_id INT UNSIGNED NOT NULL,
    line_no SMALLINT UNSIGNED NOT NULL DEFAULT 1,
    account_number VARCHAR(8) NOT NULL DEFAULT '',
    description VARCHAR(500) NOT NULL DEFAULT '',
    gross_amount DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
    net_amount DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
    tax_amount DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
    tax_rate TINYINT UNSIGNED NOT NULL DEFAULT 19,
    PRIMARY KEY (id),
    KEY idx_voucher_line_voucher (voucher_id),
    CONSTRAINT fk_voucher_line_voucher FOREIGN KEY (voucher_id) REFERENCES dg_vouchers (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
