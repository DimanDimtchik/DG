-- Überweisungen vorbereiten (SEPA / GiroCode)
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS dg_bank_transfers (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    voucher_id INT UNSIGNED NULL,
    contact_id INT UNSIGNED NULL,
    recipient_name VARCHAR(191) NOT NULL DEFAULT '',
    recipient_iban VARCHAR(40) NOT NULL DEFAULT '',
    recipient_bic VARCHAR(20) NOT NULL DEFAULT '',
    amount DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
    currency VARCHAR(3) NOT NULL DEFAULT 'EUR',
    purpose VARCHAR(255) NOT NULL DEFAULT '',
    invoice_number VARCHAR(100) NOT NULL DEFAULT '',
    status ENUM('prepared', 'executed') NOT NULL DEFAULT 'prepared',
    executed_at DATETIME NULL,
    created_by INT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_bt_status (status),
    KEY idx_bt_voucher (voucher_id),
    KEY idx_bt_contact (contact_id),
    CONSTRAINT fk_bt_voucher FOREIGN KEY (voucher_id) REFERENCES dg_vouchers (id) ON DELETE SET NULL,
    CONSTRAINT fk_bt_contact FOREIGN KEY (contact_id) REFERENCES dg_contacts (id) ON DELETE SET NULL,
    CONSTRAINT fk_bt_created_by FOREIGN KEY (created_by) REFERENCES dg_users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
