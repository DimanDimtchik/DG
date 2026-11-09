-- Registerkasse: Betriebsstätten / Filialen (K1b, Spec K1a)
-- Nicht verwechseln mit dg_stock_locations (Lager).

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS dg_cash_locations (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    code VARCHAR(32) NOT NULL,
    name VARCHAR(255) NOT NULL DEFAULT '',
    street VARCHAR(255) NOT NULL DEFAULT '',
    zip VARCHAR(16) NOT NULL DEFAULT '',
    city VARCHAR(128) NOT NULL DEFAULT '',
    country CHAR(2) NOT NULL DEFAULT 'DE',
    notes VARCHAR(500) NOT NULL DEFAULT '',
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    sort_order INT NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_cash_location_code (code),
    KEY idx_cash_location_active (is_active, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
