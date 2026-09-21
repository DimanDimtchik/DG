-- Zeiterfassung Z3c: Schicht-Zuordnung MA ↔ Tag ↔ Vorlage

CREATE TABLE IF NOT EXISTS dg_time_shift_assignments (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    contact_id INT UNSIGNED NOT NULL,
    work_date DATE NOT NULL,
    template_id INT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_time_shift_assign_contact_date (contact_id, work_date),
    KEY idx_time_shift_assign_date (work_date),
    KEY idx_time_shift_assign_template (template_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
