-- Maschinenblock M3a: Belegungsfenster fuer Produktion, Wartung und Sperrzeiten
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS dg_machine_booking_windows (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    work_center_id INT UNSIGNED NOT NULL,
    booking_type ENUM('production', 'maintenance', 'blocked', 'other') NOT NULL DEFAULT 'production',
    recipe_id INT UNSIGNED NULL,
    maintenance_plan_id INT UNSIGNED NULL,
    maintenance_log_id INT UNSIGNED NULL,
    title VARCHAR(191) NOT NULL DEFAULT '',
    starts_at DATETIME NOT NULL,
    ends_at DATETIME NOT NULL,
    planned_output_qty DECIMAL(14, 4) NOT NULL DEFAULT 0.0000,
    unit VARCHAR(50) NOT NULL DEFAULT '',
    booking_status ENUM('planned', 'done', 'cancelled') NOT NULL DEFAULT 'planned',
    notes VARCHAR(1000) NOT NULL DEFAULT '',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_machine_booking_work_center (work_center_id),
    KEY idx_machine_booking_time (starts_at, ends_at),
    KEY idx_machine_booking_type (booking_type),
    KEY idx_machine_booking_status (booking_status),
    KEY idx_machine_booking_recipe (recipe_id),
    KEY idx_machine_booking_maintenance_plan (maintenance_plan_id),
    KEY idx_machine_booking_maintenance_log (maintenance_log_id),
    CONSTRAINT fk_machine_booking_work_center FOREIGN KEY (work_center_id) REFERENCES dg_work_centers (id) ON DELETE CASCADE,
    CONSTRAINT fk_machine_booking_recipe FOREIGN KEY (recipe_id) REFERENCES dg_recipes (id) ON DELETE SET NULL,
    CONSTRAINT fk_machine_booking_maintenance_plan FOREIGN KEY (maintenance_plan_id) REFERENCES dg_machine_maintenance_plans (id) ON DELETE SET NULL,
    CONSTRAINT fk_machine_booking_maintenance_log FOREIGN KEY (maintenance_log_id) REFERENCES dg_machine_maintenance_logs (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
