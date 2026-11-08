-- FL1b: Fließlinie Stammdaten — Linie + Stationen (kein Puffer; Puffer = FL2a)
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS dg_production_lines (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(191) NOT NULL,
    code VARCHAR(64) NOT NULL DEFAULT '',
    recipe_id INT UNSIGNED NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    notes VARCHAR(1000) NOT NULL DEFAULT '',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_production_lines_active (is_active),
    KEY idx_production_lines_recipe (recipe_id),
    CONSTRAINT fk_production_lines_recipe FOREIGN KEY (recipe_id) REFERENCES dg_recipes (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS dg_production_line_stations (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    line_id INT UNSIGNED NOT NULL,
    work_center_id INT UNSIGNED NOT NULL,
    station_order INT UNSIGNED NOT NULL,
    label VARCHAR(191) NOT NULL DEFAULT '',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_production_line_station_order (line_id, station_order),
    UNIQUE KEY uq_production_line_station_wc (line_id, work_center_id),
    KEY idx_production_line_stations_wc (work_center_id),
    CONSTRAINT fk_production_line_stations_line FOREIGN KEY (line_id) REFERENCES dg_production_lines (id) ON DELETE CASCADE,
    CONSTRAINT fk_production_line_stations_wc FOREIGN KEY (work_center_id) REFERENCES dg_work_centers (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
