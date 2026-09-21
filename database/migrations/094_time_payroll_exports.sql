-- Zeiterfassung Z6b: Protokoll Lohn-Exporte

CREATE TABLE IF NOT EXISTS dg_time_payroll_exports (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    year_month CHAR(7) NOT NULL,
    format VARCHAR(32) NOT NULL DEFAULT 'csv',
    filename VARCHAR(255) NOT NULL,
    row_count INT UNSIGNED NOT NULL DEFAULT 0,
    created_by INT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_time_payroll_ym (year_month, created_at),
    KEY idx_time_payroll_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
