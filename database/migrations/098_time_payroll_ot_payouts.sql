-- Zeiterfassung Z6e: geplante Überstunden-Auszahlung je Monat (vor Lohn-Export)

CREATE TABLE IF NOT EXISTS dg_time_payroll_ot_payouts (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `year_month` CHAR(7) NOT NULL,
    contact_id INT UNSIGNED NOT NULL,
    minutes INT UNSIGNED NOT NULL DEFAULT 0,
    applied_minutes INT UNSIGNED NULL,
    export_id INT UNSIGNED NULL,
    created_by INT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    applied_at DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_payroll_ot_ym_contact (`year_month`, contact_id),
    KEY idx_payroll_ot_contact (contact_id),
    KEY idx_payroll_ot_export (export_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
