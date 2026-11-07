-- Registerkasse: Kassensitzungen (K1e)
-- Öffnen mit Bediener + Anfangsbestand; max. eine offene Sitzung je Kasse (App-Regel K11).

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS dg_cash_sessions (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    register_id INT UNSIGNED NOT NULL,
    business_date DATE NOT NULL,
    status VARCHAR(16) NOT NULL DEFAULT 'open',
    opening_balance DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
    closing_expected_balance DECIMAL(12, 2) NULL,
    closing_counted_balance DECIMAL(12, 2) NULL,
    closing_difference DECIMAL(12, 2) NULL,
    opened_by INT UNSIGNED NULL,
    closed_by INT UNSIGNED NULL,
    opened_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    closed_at DATETIME NULL,
    notes VARCHAR(500) NOT NULL DEFAULT '',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_cash_session_register_status (register_id, status),
    KEY idx_cash_session_business_date (business_date),
    KEY idx_cash_session_opened (opened_at),
    CONSTRAINT fk_cash_session_register FOREIGN KEY (register_id)
        REFERENCES dg_cash_registers (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
