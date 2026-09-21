-- Rezeptur R6: Soll/Ist-Protokoll je Lauf-Snapshot (informationspflichtig, nicht buchungswirksam)
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS dg_recipe_run_actuals (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    snapshot_id INT UNSIGNED NOT NULL,
    planned_qty DECIMAL(12, 3) NOT NULL DEFAULT 0.000,
    actual_qty DECIMAL(12, 3) NOT NULL DEFAULT 0.000,
    planned_setup_min DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
    actual_setup_min DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
    planned_run_min DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
    actual_run_min DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
    planned_self_cost DECIMAL(14, 4) NOT NULL DEFAULT 0.0000,
    actual_self_cost DECIMAL(14, 4) NULL DEFAULT NULL,
    note VARCHAR(1000) NOT NULL DEFAULT '',
    created_by INT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_recipe_actual_snapshot (snapshot_id),
    KEY idx_recipe_actual_created (created_at),
    CONSTRAINT fk_recipe_actual_snapshot FOREIGN KEY (snapshot_id) REFERENCES dg_recipe_run_snapshots (id) ON DELETE CASCADE,
    CONSTRAINT fk_recipe_actual_user FOREIGN KEY (created_by) REFERENCES dg_users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
