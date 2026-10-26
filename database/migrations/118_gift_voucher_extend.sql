-- RG2/RG3 Port: Mehrzweck-Gutschein erweitern (baut auf 114_gift_vouchers auf)
SET NAMES utf8mb4;

ALTER TABLE dg_gift_vouchers
    ADD COLUMN IF NOT EXISTS voucher_type VARCHAR(16) NOT NULL DEFAULT 'sale' AFTER code,
    ADD COLUMN IF NOT EXISTS complaint_id INT UNSIGNED NULL AFTER contact_id,
    ADD COLUMN IF NOT EXISTS liability_account VARCHAR(16) NOT NULL DEFAULT '1796' AFTER issued_voucher_id,
    ADD COLUMN IF NOT EXISTS expense_account VARCHAR(16) NOT NULL DEFAULT '' AFTER liability_account;

CREATE TABLE IF NOT EXISTS dg_voucher_gift_lines (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    voucher_id INT UNSIGNED NOT NULL,
    line_no SMALLINT UNSIGNED NOT NULL DEFAULT 1,
    line_kind VARCHAR(16) NOT NULL DEFAULT 'sale',
    gift_voucher_id INT UNSIGNED NOT NULL DEFAULT 0,
    gift_code VARCHAR(64) NOT NULL DEFAULT '',
    title VARCHAR(255) NOT NULL DEFAULT '',
    amount DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
    PRIMARY KEY (id),
    KEY idx_voucher_gift_lines_voucher (voucher_id),
    CONSTRAINT fk_voucher_gift_lines_voucher FOREIGN KEY (voucher_id) REFERENCES dg_vouchers (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
