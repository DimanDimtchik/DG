-- Registerkasse: Kassen / Terminals (K1c)
-- Je Kasse: eigene Kassen-ID, eigener Bon-Nummernkreis; FK auf dg_cash_locations.

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS dg_cash_registers (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    location_id INT UNSIGNED NOT NULL,
    code VARCHAR(32) NOT NULL,
    name VARCHAR(255) NOT NULL DEFAULT '',
    register_type VARCHAR(16) NOT NULL DEFAULT 'store',
    next_receipt_number INT UNSIGNED NOT NULL DEFAULT 1,
    receipt_number_prefix VARCHAR(16) NOT NULL DEFAULT '',
    notes VARCHAR(500) NOT NULL DEFAULT '',
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    sort_order INT NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_cash_register_code (code),
    KEY idx_cash_register_location (location_id),
    KEY idx_cash_register_active (is_active, sort_order),
    CONSTRAINT fk_cash_register_location FOREIGN KEY (location_id)
        REFERENCES dg_cash_locations (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
