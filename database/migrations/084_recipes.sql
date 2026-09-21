-- Rezeptur R1: Rezepte + Stückliste (ohne Maschinen/Routing)
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS dg_recipes (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    title VARCHAR(191) NOT NULL DEFAULT '',
    target_qty DECIMAL(12, 3) NOT NULL DEFAULT 1.000,
    labor_minutes DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
    margin_pct DECIMAL(8, 3) NOT NULL DEFAULT 0.000,
    status ENUM('draft', 'active', 'archived') NOT NULL DEFAULT 'draft',
    version INT UNSIGNED NOT NULL DEFAULT 1,
    notes VARCHAR(1000) NOT NULL DEFAULT '',
    created_by INT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_recipes_status (status),
    KEY idx_recipes_title (title),
    CONSTRAINT fk_recipes_user FOREIGN KEY (created_by) REFERENCES dg_users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS dg_recipe_bom (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    recipe_id INT UNSIGNED NOT NULL,
    sort_order INT UNSIGNED NOT NULL DEFAULT 0,
    material_label VARCHAR(191) NOT NULL DEFAULT '',
    article_id INT UNSIGNED NULL,
    qty DECIMAL(12, 4) NOT NULL DEFAULT 1.0000,
    scrap_pct DECIMAL(8, 3) NOT NULL DEFAULT 0.000,
    unit VARCHAR(32) NOT NULL DEFAULT 'Stk',
    PRIMARY KEY (id),
    KEY idx_recipe_bom_recipe (recipe_id),
    KEY idx_recipe_bom_article (article_id),
    CONSTRAINT fk_recipe_bom_recipe FOREIGN KEY (recipe_id) REFERENCES dg_recipes (id) ON DELETE CASCADE,
    CONSTRAINT fk_recipe_bom_article FOREIGN KEY (article_id) REFERENCES dg_calendar_articles (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
