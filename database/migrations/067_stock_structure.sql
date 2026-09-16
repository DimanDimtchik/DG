-- Lagerstruktur: Ort → Halle → Regal/Stellplätze → Platz (fest/flexibel)

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS dg_stock_locations (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    code VARCHAR(32) NOT NULL,
    name VARCHAR(255) NOT NULL DEFAULT '',
    address TEXT NULL,
    function_text VARCHAR(500) NOT NULL DEFAULT '',
    notes TEXT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    sort_order INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uk_stock_location_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS dg_stock_halls (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    location_id INT UNSIGNED NOT NULL,
    code VARCHAR(32) NOT NULL,
    usage_text VARCHAR(255) NOT NULL DEFAULT '',
    notes TEXT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    sort_order INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uk_stock_hall_location_code (location_id, code),
    KEY idx_stock_hall_location (location_id),
    CONSTRAINT fk_stock_hall_location FOREIGN KEY (location_id)
        REFERENCES dg_stock_locations (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS dg_stock_shelves (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    location_id INT UNSIGNED NOT NULL,
    hall_id INT UNSIGNED NOT NULL,
    code VARCHAR(32) NOT NULL,
    shelf_type ENUM('shelf', 'floor_slots') NOT NULL DEFAULT 'shelf',
    capacity_units DECIMAL(12, 3) NOT NULL DEFAULT 0,
    capacity_label VARCHAR(64) NOT NULL DEFAULT '',
    slot_count INT UNSIGNED NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    sort_order INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uk_stock_shelf_hall_code (hall_id, code),
    KEY idx_stock_shelf_hall (hall_id),
    KEY idx_stock_shelf_location (location_id),
    CONSTRAINT fk_stock_shelf_location FOREIGN KEY (location_id)
        REFERENCES dg_stock_locations (id) ON DELETE CASCADE,
    CONSTRAINT fk_stock_shelf_hall FOREIGN KEY (hall_id)
        REFERENCES dg_stock_halls (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS dg_stock_places (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    shelf_id INT UNSIGNED NOT NULL,
    location_id INT UNSIGNED NOT NULL,
    hall_id INT UNSIGNED NOT NULL,
    code VARCHAR(32) NOT NULL,
    place_mode ENUM('fixed', 'flexible') NOT NULL DEFAULT 'flexible',
    fixed_article_id INT UNSIGNED NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    sort_order INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uk_stock_place_shelf_code (shelf_id, code),
    KEY idx_stock_place_shelf (shelf_id),
    KEY idx_stock_place_hall (hall_id),
    KEY idx_stock_place_fixed_article (fixed_article_id),
    CONSTRAINT fk_stock_place_shelf FOREIGN KEY (shelf_id)
        REFERENCES dg_stock_shelves (id) ON DELETE CASCADE,
    CONSTRAINT fk_stock_place_location FOREIGN KEY (location_id)
        REFERENCES dg_stock_locations (id) ON DELETE CASCADE,
    CONSTRAINT fk_stock_place_hall FOREIGN KEY (hall_id)
        REFERENCES dg_stock_halls (id) ON DELETE CASCADE,
    CONSTRAINT fk_stock_place_fixed_article FOREIGN KEY (fixed_article_id)
        REFERENCES dg_calendar_articles (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS dg_stock_place_occupancy (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    place_id INT UNSIGNED NOT NULL,
    article_id INT UNSIGNED NOT NULL,
    quantity DECIMAL(12, 3) NOT NULL DEFAULT 0,
    assigned_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uk_stock_place_occupancy (place_id, article_id),
    KEY idx_stock_occupancy_article (article_id),
    CONSTRAINT fk_stock_occupancy_place FOREIGN KEY (place_id)
        REFERENCES dg_stock_places (id) ON DELETE CASCADE,
    CONSTRAINT fk_stock_occupancy_article FOREIGN KEY (article_id)
        REFERENCES dg_calendar_articles (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE dg_calendar_articles
    ADD COLUMN stock_location_id INT UNSIGNED NULL AFTER stock_platz,
    ADD COLUMN stock_hall_id INT UNSIGNED NULL AFTER stock_location_id,
    ADD COLUMN stock_shelf_id INT UNSIGNED NULL AFTER stock_hall_id,
    ADD COLUMN stock_place_id INT UNSIGNED NULL AFTER stock_shelf_id,
    ADD COLUMN stock_place_mode ENUM('fixed', 'flexible') NOT NULL DEFAULT 'flexible' AFTER stock_place_id;
