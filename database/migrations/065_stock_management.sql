-- Lagerführung Stufe A+B: Bestand, Bewegungen, Inventur (ein Lager)

SET NAMES utf8mb4;

ALTER TABLE dg_calendar_articles
    ADD COLUMN track_stock TINYINT(1) NOT NULL DEFAULT 0 AFTER is_active,
    ADD COLUMN stock_qty DECIMAL(12, 3) NOT NULL DEFAULT 0.000 AFTER track_stock,
    ADD COLUMN min_stock DECIMAL(12, 3) NOT NULL DEFAULT 0.000 AFTER stock_qty;

CREATE TABLE IF NOT EXISTS dg_stock_movements (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    article_id INT UNSIGNED NOT NULL,
    movement_date DATE NOT NULL,
    quantity DECIMAL(12, 3) NOT NULL DEFAULT 0.000,
    reason ENUM('purchase', 'sale', 'adjustment', 'inventory', 'opening', 'reversal') NOT NULL DEFAULT 'adjustment',
    voucher_id INT UNSIGNED NULL,
    inventory_id INT UNSIGNED NULL,
    note VARCHAR(500) NOT NULL DEFAULT '',
    created_by INT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_stock_mov_article (article_id),
    KEY idx_stock_mov_voucher (voucher_id),
    KEY idx_stock_mov_inventory (inventory_id),
    KEY idx_stock_mov_date (movement_date),
    CONSTRAINT fk_stock_mov_article FOREIGN KEY (article_id) REFERENCES dg_calendar_articles (id) ON DELETE CASCADE,
    CONSTRAINT fk_stock_mov_voucher FOREIGN KEY (voucher_id) REFERENCES dg_vouchers (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS dg_stock_inventories (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    inventory_date DATE NOT NULL,
    note VARCHAR(500) NOT NULL DEFAULT '',
    status ENUM('open', 'closed') NOT NULL DEFAULT 'open',
    created_by INT UNSIGNED NULL,
    closed_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_stock_inv_date (inventory_date),
    KEY idx_stock_inv_status (status),
    CONSTRAINT fk_stock_inv_user FOREIGN KEY (created_by) REFERENCES dg_users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS dg_stock_inventory_lines (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    inventory_id INT UNSIGNED NOT NULL,
    article_id INT UNSIGNED NOT NULL,
    book_quantity DECIMAL(12, 3) NOT NULL DEFAULT 0.000,
    counted_quantity DECIMAL(12, 3) NOT NULL DEFAULT 0.000,
    diff_quantity DECIMAL(12, 3) NOT NULL DEFAULT 0.000,
    PRIMARY KEY (id),
    UNIQUE KEY uq_stock_inv_line (inventory_id, article_id),
    KEY idx_stock_inv_line_article (article_id),
    CONSTRAINT fk_stock_inv_line_header FOREIGN KEY (inventory_id) REFERENCES dg_stock_inventories (id) ON DELETE CASCADE,
    CONSTRAINT fk_stock_inv_line_article FOREIGN KEY (article_id) REFERENCES dg_calendar_articles (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE dg_stock_movements
    ADD CONSTRAINT fk_stock_mov_inventory FOREIGN KEY (inventory_id) REFERENCES dg_stock_inventories (id) ON DELETE SET NULL;
