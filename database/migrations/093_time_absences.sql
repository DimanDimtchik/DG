-- Zeiterfassung Z4b: Urlaubsanspruch + Abwesenheiten (Urlaub/Krankheit)

CREATE TABLE IF NOT EXISTS dg_time_vacation_entitlements (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    contact_id INT UNSIGNED NOT NULL,
    year SMALLINT UNSIGNED NOT NULL,
    days_entitled DECIMAL(5,1) NOT NULL DEFAULT 0.0,
    days_carried DECIMAL(5,1) NOT NULL DEFAULT 0.0,
    note VARCHAR(255) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_time_vac_ent_contact_year (contact_id, year),
    KEY idx_time_vac_ent_year (year)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS dg_time_absences (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    contact_id INT UNSIGNED NOT NULL,
    type ENUM('vacation', 'sick', 'other') NOT NULL,
    date_from DATE NOT NULL,
    date_to DATE NOT NULL,
    days_count DECIMAL(5,1) NOT NULL,
    status ENUM('requested', 'approved', 'rejected', 'cancelled') NOT NULL DEFAULT 'requested',
    reason VARCHAR(500) NOT NULL DEFAULT '',
    document_ref VARCHAR(255) NULL,
    decided_by INT UNSIGNED NULL,
    decided_at DATETIME NULL,
    created_by INT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_time_abs_contact_dates (contact_id, date_from, date_to),
    KEY idx_time_abs_status (status, type),
    KEY idx_time_abs_range (date_from, date_to)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
