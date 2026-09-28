-- Audit: Urlaubszuschnitt bei Krankheit/Sonderurlaub (Anspruchs-Anpassung)

CREATE TABLE IF NOT EXISTS dg_time_absence_adjustments (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    interrupt_absence_id INT UNSIGNED NOT NULL,
    vacation_absence_id INT UNSIGNED NOT NULL,
    action ENUM('carve', 'restore') NOT NULL,
    date_from DATE NOT NULL,
    date_to DATE NOT NULL,
    days_carved DECIMAL(6,1) NOT NULL DEFAULT 0.0,
    payload_json MEDIUMTEXT NULL,
    created_by INT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_taa_interrupt (interrupt_absence_id),
    KEY idx_taa_vacation (vacation_absence_id),
    KEY idx_taa_action (action)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
