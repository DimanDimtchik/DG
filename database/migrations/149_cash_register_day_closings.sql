-- Registerkasse: Tagesabschluss je Kasse (K1h)
-- NICHT FiBu-Kassenbuch dg_cash_day_closings (054) — anderer Name bewusst.

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS dg_cash_register_day_closings (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    location_id INT UNSIGNED NOT NULL,
    register_id INT UNSIGNED NOT NULL,
    business_date DATE NOT NULL,
    opening_balance DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
    total_cash DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
    total_ec DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
    total_card DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
    total_voucher DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
    total_gross DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
    receipt_count INT UNSIGNED NOT NULL DEFAULT 0,
    expected_balance DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
    counted_balance DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
    difference DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
    summary_json TEXT NULL,
    notes VARCHAR(500) NOT NULL DEFAULT '',
    closed_by INT UNSIGNED NULL,
    closed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_cash_reg_closing_register_date (register_id, business_date),
    KEY idx_cash_reg_closing_location_date (location_id, business_date),
    KEY idx_cash_reg_closing_closed (closed_at),
    CONSTRAINT fk_cash_reg_closing_location FOREIGN KEY (location_id)
        REFERENCES dg_cash_locations (id) ON DELETE RESTRICT,
    CONSTRAINT fk_cash_reg_closing_register FOREIGN KEY (register_id)
        REFERENCES dg_cash_registers (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
