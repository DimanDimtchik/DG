-- Zahlungsbedingungen (Skonto-Stufen) und Mahnwesen auf Belegen

ALTER TABLE dg_vouchers
    ADD COLUMN payment_due_date DATE NULL AFTER paid_at,
    ADD COLUMN payment_term_tiers JSON NULL AFTER payment_due_date,
    ADD COLUMN dunning_level TINYINT UNSIGNED NOT NULL DEFAULT 0 AFTER payment_term_tiers,
    ADD COLUMN dunning_fee_total DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER dunning_level,
    ADD COLUMN last_dunning_sent_at DATETIME NULL AFTER dunning_fee_total;

CREATE TABLE IF NOT EXISTS dg_voucher_dunnings (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    voucher_id INT UNSIGNED NOT NULL,
    level TINYINT UNSIGNED NOT NULL DEFAULT 1,
    label VARCHAR(120) NOT NULL DEFAULT '',
    fee_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
    recipient_email VARCHAR(191) NULL,
    sent_at DATETIME NOT NULL,
    created_by INT UNSIGNED NULL,
    PRIMARY KEY (id),
    KEY idx_voucher_dunnings_voucher (voucher_id),
    KEY idx_voucher_dunnings_sent (sent_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
