-- AP1: Dynamische Artikel-/Leistungseigenschaften (Gruppen, Definitionen, Werte, Instanz-Aktivierung)
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS dg_article_property_groups (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    code VARCHAR(64) NOT NULL,
    label VARCHAR(191) NOT NULL,
    description TEXT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    is_system TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_article_property_group_code (code),
    KEY idx_article_property_group_sort (sort_order, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS dg_article_properties (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    group_id INT UNSIGNED NOT NULL,
    code VARCHAR(64) NOT NULL,
    label VARCHAR(191) NOT NULL,
    description TEXT NULL,
    data_type VARCHAR(32) NOT NULL DEFAULT 'text',
    unit VARCHAR(32) NOT NULL DEFAULT '',
    allowed_values_json TEXT NULL,
    min_value DECIMAL(18, 6) NULL,
    max_value DECIMAL(18, 6) NULL,
    is_required TINYINT(1) NOT NULL DEFAULT 0,
    is_filterable TINYINT(1) NOT NULL DEFAULT 0,
    is_searchable TINYINT(1) NOT NULL DEFAULT 0,
    sort_order INT NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_article_property_group_code (group_id, code),
    KEY idx_article_property_group (group_id),
    KEY idx_article_property_sort (group_id, sort_order, id),
    CONSTRAINT fk_article_property_group
        FOREIGN KEY (group_id) REFERENCES dg_article_property_groups (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS dg_article_property_values (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    article_id INT UNSIGNED NOT NULL,
    property_id INT UNSIGNED NOT NULL,
    value_text TEXT NULL,
    value_integer BIGINT NULL,
    value_decimal DECIMAL(18, 6) NULL,
    value_boolean TINYINT(1) NULL,
    value_date DATE NULL,
    value_json TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_article_property_value (article_id, property_id),
    KEY idx_article_property_value_property (property_id),
    KEY idx_article_property_value_article (article_id),
    CONSTRAINT fk_article_property_value_article
        FOREIGN KEY (article_id) REFERENCES dg_calendar_articles (id) ON DELETE CASCADE,
    CONSTRAINT fk_article_property_value_property
        FOREIGN KEY (property_id) REFERENCES dg_article_properties (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS dg_instance_property_groups (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    group_id INT UNSIGNED NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 0,
    sort_order INT NOT NULL DEFAULT 0,
    activated_at DATETIME NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_instance_property_group (group_id),
    KEY idx_instance_property_group_active (is_active, sort_order),
    CONSTRAINT fk_instance_property_group
        FOREIGN KEY (group_id) REFERENCES dg_article_property_groups (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
