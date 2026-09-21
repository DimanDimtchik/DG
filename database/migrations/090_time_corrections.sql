-- Zeiterfassung Z2e: Stempel-Korrekturen (Audit) + Überstunden-Abbau-Protokoll

CREATE TABLE IF NOT EXISTS dg_time_corrections (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    contact_id INT UNSIGNED NOT NULL,
    work_date DATE NOT NULL,
    delta_worked_minutes INT NOT NULL,
    reason VARCHAR(500) NOT NULL,
    created_by INT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_time_corr_contact_date (contact_id, work_date),
    KEY idx_time_corr_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS dg_time_overtime_reductions (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    contact_id INT UNSIGNED NOT NULL,
    minutes INT UNSIGNED NOT NULL,
    reason VARCHAR(500) NOT NULL,
    created_by INT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_time_ot_red_contact (contact_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
