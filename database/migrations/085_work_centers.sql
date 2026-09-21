-- Rezeptur R2: Maschinen / Arbeitsplätze (Work Centers)
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS dg_work_centers (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(191) NOT NULL DEFAULT '',
    purchase_price DECIMAL(14, 2) NOT NULL DEFAULT 0.00,
    life_hours DECIMAL(14, 2) NOT NULL DEFAULT 1.00,
    kw DECIMAL(10, 3) NOT NULL DEFAULT 0.000,
    space_m2 DECIMAL(10, 3) NOT NULL DEFAULT 0.000,
    operators DECIMAL(8, 2) NOT NULL DEFAULT 1.00,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    notes VARCHAR(1000) NOT NULL DEFAULT '',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_work_centers_active (is_active),
    KEY idx_work_centers_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
