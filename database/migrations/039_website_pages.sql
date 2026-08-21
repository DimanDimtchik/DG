-- Öffentliche Website-Seiten (Page-Builder)
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS dg_website_pages (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    title VARCHAR(191) NOT NULL DEFAULT '',
    slug VARCHAR(191) NOT NULL DEFAULT '',
    status VARCHAR(20) NOT NULL DEFAULT 'draft',
    layout_json LONGTEXT NULL,
    created_by INT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_website_page_slug (slug),
    KEY idx_website_page_status (status),
    CONSTRAINT fk_website_page_user FOREIGN KEY (created_by) REFERENCES dg_users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
