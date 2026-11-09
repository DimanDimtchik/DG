-- Registerkasse: Zahlungen je Bon (K1g)
-- Gemischt = mehrere Zeilen; Summe muss Bon-Brutto ergeben (App K8d).

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS dg_cash_payments (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    receipt_id INT UNSIGNED NOT NULL,
    line_no SMALLINT UNSIGNED NOT NULL DEFAULT 1,
    payment_method VARCHAR(16) NOT NULL,
    amount DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
    currency CHAR(3) NOT NULL DEFAULT 'EUR',
    gift_voucher_id INT UNSIGNED NULL,
    external_ref VARCHAR(128) NOT NULL DEFAULT '',
    notes VARCHAR(500) NOT NULL DEFAULT '',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_cash_payment_line (receipt_id, line_no),
    KEY idx_cash_payment_receipt (receipt_id),
    KEY idx_cash_payment_method (payment_method),
    KEY idx_cash_payment_voucher (gift_voucher_id),
    CONSTRAINT fk_cash_payment_receipt FOREIGN KEY (receipt_id)
        REFERENCES dg_cash_receipts (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
