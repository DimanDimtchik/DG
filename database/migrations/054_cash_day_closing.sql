-- Kassenbuch: Tagesabschlüsse
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS dg_cash_day_closings (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    closing_date DATE NOT NULL,
    opening_balance DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
    expected_balance DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
    counted_balance DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
    difference DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
    note VARCHAR(500) NOT NULL DEFAULT '',
    closed_by INT UNSIGNED NULL,
    closed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_cash_closing_date (closing_date),
    KEY idx_cash_closing_closed (closed_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
