-- Lohn L3b: Mandanten-Lohnarten (Stufe A, ohne Bau/Gastro)

CREATE TABLE IF NOT EXISTS dg_payroll_wage_types (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    code VARCHAR(32) NOT NULL,
    label VARCHAR(120) NOT NULL,
    category VARCHAR(32) NOT NULL DEFAULT 'other',
    unit VARCHAR(16) NOT NULL DEFAULT 'amount',
    direction VARCHAR(16) NOT NULL DEFAULT 'earning',
    taxable TINYINT(1) NOT NULL DEFAULT 1,
    subject_to_sv TINYINT(1) NOT NULL DEFAULT 1,
    flat_tax_possible TINYINT(1) NOT NULL DEFAULT 0,
    increases_net TINYINT(1) NOT NULL DEFAULT 1,
    skr03_expense_hint VARCHAR(16) NOT NULL DEFAULT '',
    skr04_expense_hint VARCHAR(16) NOT NULL DEFAULT '',
    skr03_liability_hint VARCHAR(16) NOT NULL DEFAULT '',
    skr04_liability_hint VARCHAR(16) NOT NULL DEFAULT '',
    sort_order INT NOT NULL DEFAULT 0,
    is_system TINYINT(1) NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    notes TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_payroll_wage_code (code),
    KEY idx_payroll_wage_active_sort (is_active, sort_order, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
