-- DATEV-Doppelbuchung: Journal-Felder, Personenkonten, Skonto, Kassenbuch
SET NAMES utf8mb4;

ALTER TABLE dg_ledger_postings
    ADD COLUMN person_account VARCHAR(8) NOT NULL DEFAULT '' AFTER contra_account,
    ADD COLUMN tax_key VARCHAR(10) NOT NULL DEFAULT '' AFTER tax_rate,
    ADD COLUMN document_field1 VARCHAR(36) NOT NULL DEFAULT '' AFTER description,
    ADD COLUMN document_field2 VARCHAR(36) NOT NULL DEFAULT '' AFTER document_field1;

ALTER TABLE dg_contacts
    ADD COLUMN debtor_account VARCHAR(8) NOT NULL DEFAULT '' AFTER supplier_customer_number,
    ADD COLUMN creditor_account VARCHAR(8) NOT NULL DEFAULT '' AFTER debtor_account;

ALTER TABLE dg_vouchers
    ADD COLUMN discount_percent TINYINT UNSIGNED NOT NULL DEFAULT 0 AFTER gross_amount,
    ADD COLUMN discount_amount DECIMAL(12, 2) NOT NULL DEFAULT 0.00 AFTER discount_percent,
    ADD COLUMN paid_amount DECIMAL(12, 2) NOT NULL DEFAULT 0.00 AFTER discount_amount,
    ADD COLUMN paid_at DATE NULL AFTER paid_amount;

CREATE TABLE IF NOT EXISTS dg_cash_journal (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    entry_date DATE NOT NULL,
    voucher_id INT UNSIGNED NULL,
    account_number VARCHAR(8) NOT NULL DEFAULT '1000',
    side ENUM('in', 'out') NOT NULL,
    amount DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
    description VARCHAR(500) NOT NULL DEFAULT '',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_cash_date (entry_date),
    KEY idx_cash_voucher (voucher_id),
    CONSTRAINT fk_cash_voucher FOREIGN KEY (voucher_id) REFERENCES dg_vouchers (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
