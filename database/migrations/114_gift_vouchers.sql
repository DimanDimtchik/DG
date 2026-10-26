-- Geschenkgutscheine: Stamm + Einlösung auf Erlös-Belegen
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS dg_gift_vouchers (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    code VARCHAR(64) NOT NULL,
    title VARCHAR(191) NOT NULL DEFAULT '',
    contact_id INT UNSIGNED NULL,
    face_value_gross DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
    remaining_gross DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
    currency CHAR(3) NOT NULL DEFAULT 'EUR',
    status ENUM('active', 'redeemed', 'cancelled', 'expired') NOT NULL DEFAULT 'active',
    valid_until DATE NULL,
    issued_voucher_id INT UNSIGNED NULL,
    notes TEXT NULL,
    created_by INT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_gift_voucher_code (code),
    KEY idx_gift_voucher_status (status),
    KEY idx_gift_voucher_contact (contact_id),
    KEY idx_gift_voucher_remaining (remaining_gross),
    CONSTRAINT fk_gift_voucher_created_by FOREIGN KEY (created_by) REFERENCES dg_users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS dg_voucher_gift_redemptions (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    voucher_id INT UNSIGNED NOT NULL,
    gift_voucher_id INT UNSIGNED NOT NULL,
    amount_gross DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_gift_redemption_voucher_gift (voucher_id, gift_voucher_id),
    KEY idx_gift_redemption_voucher (voucher_id),
    KEY idx_gift_redemption_gift (gift_voucher_id),
    CONSTRAINT fk_gift_redemption_voucher FOREIGN KEY (voucher_id) REFERENCES dg_vouchers (id) ON DELETE CASCADE,
    CONSTRAINT fk_gift_redemption_gift FOREIGN KEY (gift_voucher_id) REFERENCES dg_gift_vouchers (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE dg_vouchers
    ADD COLUMN IF NOT EXISTS gift_redeemed_amount DECIMAL(12, 2) NOT NULL DEFAULT 0.00 AFTER discount_amount;
