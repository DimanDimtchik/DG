-- Maschinenblock M0b: Kapazitaet, Zusatzmaterial und Wartung
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS dg_machine_capacity_profiles (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    work_center_id INT UNSIGNED NOT NULL,
    recipe_id INT UNSIGNED NULL,
    input_material_label VARCHAR(191) NOT NULL DEFAULT '',
    input_article_id INT UNSIGNED NULL,
    input_qty_per_hour DECIMAL(14, 4) NOT NULL DEFAULT 0.0000,
    output_product_label VARCHAR(191) NOT NULL DEFAULT '',
    output_article_id INT UNSIGNED NULL,
    output_qty_per_hour DECIMAL(14, 4) NOT NULL DEFAULT 0.0000,
    unit VARCHAR(50) NOT NULL DEFAULT '',
    yield_percent DECIMAL(6, 2) NOT NULL DEFAULT 100.00,
    valid_from DATE NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    notes VARCHAR(1000) NOT NULL DEFAULT '',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_machine_capacity_work_center (work_center_id),
    KEY idx_machine_capacity_recipe (recipe_id),
    KEY idx_machine_capacity_input_article (input_article_id),
    KEY idx_machine_capacity_output_article (output_article_id),
    KEY idx_machine_capacity_active (is_active),
    CONSTRAINT fk_machine_capacity_work_center FOREIGN KEY (work_center_id) REFERENCES dg_work_centers (id) ON DELETE CASCADE,
    CONSTRAINT fk_machine_capacity_recipe FOREIGN KEY (recipe_id) REFERENCES dg_recipes (id) ON DELETE SET NULL,
    CONSTRAINT fk_machine_capacity_input_article FOREIGN KEY (input_article_id) REFERENCES dg_calendar_articles (id) ON DELETE SET NULL,
    CONSTRAINT fk_machine_capacity_output_article FOREIGN KEY (output_article_id) REFERENCES dg_calendar_articles (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS dg_recipe_auxiliary_materials (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    recipe_id INT UNSIGNED NOT NULL,
    article_id INT UNSIGNED NULL,
    material_label VARCHAR(191) NOT NULL DEFAULT '',
    role ENUM('packaging', 'additive', 'consumable', 'other') NOT NULL DEFAULT 'other',
    qty_per_output_unit DECIMAL(14, 4) NOT NULL DEFAULT 0.0000,
    unit VARCHAR(50) NOT NULL DEFAULT '',
    cost_per_unit DECIMAL(14, 4) NOT NULL DEFAULT 0.0000,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_recipe_aux_recipe (recipe_id),
    KEY idx_recipe_aux_article (article_id),
    KEY idx_recipe_aux_role (role),
    KEY idx_recipe_aux_active (is_active),
    CONSTRAINT fk_recipe_aux_recipe FOREIGN KEY (recipe_id) REFERENCES dg_recipes (id) ON DELETE CASCADE,
    CONSTRAINT fk_recipe_aux_article FOREIGN KEY (article_id) REFERENCES dg_calendar_articles (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS dg_machine_maintenance_plans (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    work_center_id INT UNSIGNED NOT NULL,
    title VARCHAR(191) NOT NULL DEFAULT '',
    interval_type ENUM('days', 'runtime_hours', 'cycles') NOT NULL,
    interval_value DECIMAL(14, 2) NOT NULL DEFAULT 0.00,
    planned_downtime_minutes INT UNSIGNED NOT NULL DEFAULT 0,
    helper_materials_json JSON NULL,
    estimated_material_cost DECIMAL(14, 2) NOT NULL DEFAULT 0.00,
    estimated_service_cost DECIMAL(14, 2) NOT NULL DEFAULT 0.00,
    next_due_date DATE NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_machine_maintenance_plan_work_center (work_center_id),
    KEY idx_machine_maintenance_plan_due (next_due_date),
    KEY idx_machine_maintenance_plan_active (is_active),
    CONSTRAINT fk_machine_maintenance_plan_work_center FOREIGN KEY (work_center_id) REFERENCES dg_work_centers (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS dg_machine_maintenance_logs (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    maintenance_plan_id INT UNSIGNED NULL,
    work_center_id INT UNSIGNED NOT NULL,
    performed_at DATETIME NOT NULL,
    runtime_hours_at_service DECIMAL(14, 2) NULL,
    actual_material_cost DECIMAL(14, 2) NOT NULL DEFAULT 0.00,
    actual_service_cost DECIMAL(14, 2) NOT NULL DEFAULT 0.00,
    notes VARCHAR(1000) NOT NULL DEFAULT '',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_machine_maintenance_log_work_center (work_center_id),
    KEY idx_machine_maintenance_log_plan (maintenance_plan_id),
    KEY idx_machine_maintenance_log_performed (performed_at),
    CONSTRAINT fk_machine_maintenance_log_plan FOREIGN KEY (maintenance_plan_id) REFERENCES dg_machine_maintenance_plans (id) ON DELETE SET NULL,
    CONSTRAINT fk_machine_maintenance_log_work_center FOREIGN KEY (work_center_id) REFERENCES dg_work_centers (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
