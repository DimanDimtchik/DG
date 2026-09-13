-- Barcodes (Artikel/GTIN, Palette/Platz, Karton) + Wareneingang/Warenausgang

SET NAMES utf8mb4;

ALTER TABLE dg_stock_places
    ADD COLUMN barcode VARCHAR(64) NULL AFTER code,
    ADD UNIQUE KEY uk_stock_place_barcode (barcode);

CREATE TABLE IF NOT EXISTS dg_stock_packages (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    article_id INT UNSIGNED NOT NULL,
    place_id INT UNSIGNED NULL,
    barcode VARCHAR(64) NOT NULL,
    quantity DECIMAL(12, 3) NOT NULL DEFAULT 0.000,
    status ENUM('in_stock', 'issued') NOT NULL DEFAULT 'in_stock',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    issued_at TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uk_stock_package_barcode (barcode),
    KEY idx_stock_package_article (article_id),
    KEY idx_stock_package_place (place_id),
    KEY idx_stock_package_status (status),
    CONSTRAINT fk_stock_package_article FOREIGN KEY (article_id)
        REFERENCES dg_calendar_articles (id) ON DELETE CASCADE,
    CONSTRAINT fk_stock_package_place FOREIGN KEY (place_id)
        REFERENCES dg_stock_places (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE dg_stock_movements
    ADD COLUMN place_id INT UNSIGNED NULL AFTER article_id,
    ADD COLUMN package_id INT UNSIGNED NULL AFTER place_id,
    ADD KEY idx_stock_mov_place (place_id),
    ADD KEY idx_stock_mov_package (package_id);

ALTER TABLE dg_stock_movements
    MODIFY COLUMN reason ENUM(
        'purchase', 'sale', 'adjustment', 'inventory', 'opening', 'reversal', 'receipt', 'issue'
    ) NOT NULL DEFAULT 'adjustment';

ALTER TABLE dg_stock_movements
    ADD CONSTRAINT fk_stock_mov_place FOREIGN KEY (place_id)
        REFERENCES dg_stock_places (id) ON DELETE SET NULL,
    ADD CONSTRAINT fk_stock_mov_package FOREIGN KEY (package_id)
        REFERENCES dg_stock_packages (id) ON DELETE SET NULL;
