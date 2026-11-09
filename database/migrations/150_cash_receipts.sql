-- Registerkasse: Bons + Positionen (K1f)
-- Fertige Bons unveränderbar (App); Storno nur als neuer Bon mit Referenz (K13).
-- Zahlungen → dg_cash_payments (K1g).

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS dg_cash_receipts (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    location_id INT UNSIGNED NOT NULL,
    register_id INT UNSIGNED NOT NULL,
    session_id INT UNSIGNED NOT NULL,
    tse_assignment_id INT UNSIGNED NULL,
    voucher_id INT UNSIGNED NULL,
    receipt_number INT UNSIGNED NOT NULL,
    receipt_number_display VARCHAR(48) NOT NULL DEFAULT '',
    status VARCHAR(16) NOT NULL DEFAULT 'draft',
    receipt_type VARCHAR(16) NOT NULL DEFAULT 'sale',
    storno_of_receipt_id INT UNSIGNED NULL,
    operator_id INT UNSIGNED NULL,
    total_gross DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
    total_net DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
    vat_summary_json TEXT NULL,
    payment_summary_json TEXT NULL,
    tse_tx_id CHAR(36) NOT NULL DEFAULT '',
    transaction_number INT UNSIGNED NULL,
    tse_serial VARCHAR(128) NOT NULL DEFAULT '',
    signature_counter VARCHAR(32) NOT NULL DEFAULT '',
    signature_value TEXT NULL,
    signature_algorithm VARCHAR(64) NOT NULL DEFAULT '',
    qr_code_data TEXT NULL,
    time_start INT UNSIGNED NULL,
    time_end INT UNSIGNED NULL,
    started_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    finished_at DATETIME NULL,
    notes VARCHAR(500) NOT NULL DEFAULT '',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_cash_receipt_register_number (register_id, receipt_number),
    KEY idx_cash_receipt_session (session_id),
    KEY idx_cash_receipt_location (location_id),
    KEY idx_cash_receipt_status (status, finished_at),
    KEY idx_cash_receipt_voucher (voucher_id),
    KEY idx_cash_receipt_storno_of (storno_of_receipt_id),
    KEY idx_cash_receipt_tse_tx (tse_tx_id),
    CONSTRAINT fk_cash_receipt_location FOREIGN KEY (location_id)
        REFERENCES dg_cash_locations (id) ON DELETE RESTRICT,
    CONSTRAINT fk_cash_receipt_register FOREIGN KEY (register_id)
        REFERENCES dg_cash_registers (id) ON DELETE RESTRICT,
    CONSTRAINT fk_cash_receipt_session FOREIGN KEY (session_id)
        REFERENCES dg_cash_sessions (id) ON DELETE RESTRICT,
    CONSTRAINT fk_cash_receipt_tse_assignment FOREIGN KEY (tse_assignment_id)
        REFERENCES dg_cash_tse_assignments (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS dg_cash_receipt_lines (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    receipt_id INT UNSIGNED NOT NULL,
    line_no SMALLINT UNSIGNED NOT NULL DEFAULT 1,
    article_id INT UNSIGNED NOT NULL DEFAULT 0,
    article_number VARCHAR(64) NOT NULL DEFAULT '',
    title VARCHAR(255) NOT NULL DEFAULT '',
    unit VARCHAR(64) NOT NULL DEFAULT 'Stück',
    quantity DECIMAL(12, 3) NOT NULL DEFAULT 1.000,
    unit_price_gross DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
    gross_amount DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
    tax_rate TINYINT UNSIGNED NOT NULL DEFAULT 19,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_cash_receipt_line (receipt_id, line_no),
    KEY idx_cash_receipt_line_receipt (receipt_id),
    CONSTRAINT fk_cash_receipt_line_receipt FOREIGN KEY (receipt_id)
        REFERENCES dg_cash_receipts (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
