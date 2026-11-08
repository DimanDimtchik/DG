-- ERP E2a: Maschinenwissen / Stoerungen (Handbuch, Akademie, externe URL)
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS dg_machine_knowledge_links (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    work_center_id INT UNSIGNED NULL,
    recipe_id INT UNSIGNED NULL,
    title VARCHAR(191) NOT NULL,
    content_type ENUM('manual_pdf', 'academy_module', 'external_url') NOT NULL,
    storage_path VARCHAR(500) NOT NULL DEFAULT '',
    academy_module_id INT UNSIGNED NULL,
    video_duration_seconds INT UNSIGNED NULL,
    error_codes_json JSON NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_machine_knowledge_work_center (work_center_id),
    KEY idx_machine_knowledge_recipe (recipe_id),
    KEY idx_machine_knowledge_academy_module (academy_module_id),
    KEY idx_machine_knowledge_active (is_active),
    CONSTRAINT fk_machine_knowledge_work_center
        FOREIGN KEY (work_center_id) REFERENCES dg_work_centers (id) ON DELETE SET NULL,
    CONSTRAINT fk_machine_knowledge_recipe
        FOREIGN KEY (recipe_id) REFERENCES dg_recipes (id) ON DELETE SET NULL,
    CONSTRAINT fk_machine_knowledge_academy_module
        FOREIGN KEY (academy_module_id) REFERENCES dg_academy_modules (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
