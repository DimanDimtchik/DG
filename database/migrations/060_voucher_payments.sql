-- Teilzahlungen: Zahlungshistorie pro Beleg

CREATE TABLE IF NOT EXISTS dg_voucher_payments (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    voucher_id INT UNSIGNED NOT NULL,
    amount DECIMAL(12, 2) NOT NULL,
    payment_date DATE NOT NULL,
    payment_method VARCHAR(32) NOT NULL DEFAULT 'bank',
    reference_text VARCHAR(255) NULL,
    bank_transaction_id INT UNSIGNED NULL,
    bank_transfer_id INT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_by INT UNSIGNED NULL,
    PRIMARY KEY (id),
    KEY idx_voucher_payments_voucher (voucher_id),
    KEY idx_voucher_payments_date (payment_date),
    KEY idx_voucher_payments_bank_tx (bank_transaction_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
