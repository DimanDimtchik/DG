-- Belegerfassung (Phase 2)
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS dg_vouchers (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    voucher_type ENUM('receipt', 'expense', 'invoice', 'credit') NOT NULL DEFAULT 'expense',
    voucher_date DATE NOT NULL,
    contact_id INT UNSIGNED NULL,
    supplier_name VARCHAR(191) NOT NULL DEFAULT '',
    invoice_number VARCHAR(100) NOT NULL DEFAULT '',
    description VARCHAR(500) NOT NULL DEFAULT '',
    gross_amount DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
    net_amount DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
    tax_amount DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
    tax_rate TINYINT UNSIGNED NOT NULL DEFAULT 19,
    tax_key VARCHAR(10) NOT NULL DEFAULT '',
    account_number VARCHAR(8) NOT NULL DEFAULT '',
    payment_status ENUM('open', 'paid') NOT NULL DEFAULT 'open',
    notes TEXT NULL,
    created_by INT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_voucher_date (voucher_date),
    KEY idx_voucher_type (voucher_type),
    KEY idx_voucher_contact (contact_id),
    KEY idx_voucher_payment (payment_status),
    CONSTRAINT fk_voucher_contact FOREIGN KEY (contact_id) REFERENCES dg_contacts (id) ON DELETE SET NULL,
    CONSTRAINT fk_voucher_created_by FOREIGN KEY (created_by) REFERENCES dg_users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
