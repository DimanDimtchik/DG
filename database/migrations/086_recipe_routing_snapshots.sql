-- Rezeptur R3: Arbeitsplan (Routing) + Kalkulations-Snapshots; optionaler EK an BOM
SET NAMES utf8mb4;

ALTER TABLE dg_recipe_bom
    ADD COLUMN unit_cost DECIMAL(14, 4) NULL DEFAULT NULL AFTER unit;

CREATE TABLE IF NOT EXISTS dg_recipe_routing (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    recipe_id INT UNSIGNED NOT NULL,
    step_order INT UNSIGNED NOT NULL DEFAULT 0,
    work_center_id INT UNSIGNED NOT NULL,
    setup_min DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
    run_min DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
    label VARCHAR(191) NOT NULL DEFAULT '',
    PRIMARY KEY (id),
    KEY idx_recipe_routing_recipe (recipe_id),
    KEY idx_recipe_routing_wc (work_center_id),
    CONSTRAINT fk_recipe_routing_recipe FOREIGN KEY (recipe_id) REFERENCES dg_recipes (id) ON DELETE CASCADE,
    CONSTRAINT fk_recipe_routing_wc FOREIGN KEY (work_center_id) REFERENCES dg_work_centers (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS dg_recipe_run_snapshots (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    recipe_id INT UNSIGNED NOT NULL,
    kind ENUM('save', 'run') NOT NULL DEFAULT 'save',
    inputs_json LONGTEXT NOT NULL,
    result_json LONGTEXT NOT NULL,
    created_by INT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_recipe_snap_recipe (recipe_id, created_at),
    KEY idx_recipe_snap_kind (kind),
    CONSTRAINT fk_recipe_snap_recipe FOREIGN KEY (recipe_id) REFERENCES dg_recipes (id) ON DELETE CASCADE,
    CONSTRAINT fk_recipe_snap_user FOREIGN KEY (created_by) REFERENCES dg_users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
