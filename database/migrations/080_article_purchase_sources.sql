-- Einkaufsquellen je Artikel (mehrere Lieferanten / Shops)
CREATE TABLE IF NOT EXISTS dg_article_purchase_sources (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    article_id INT UNSIGNED NOT NULL,
    supplier_contact_id INT UNSIGNED NULL DEFAULT NULL,
    supplier_name VARCHAR(191) NOT NULL DEFAULT '',
    purchase_price DECIMAL(12, 4) NOT NULL DEFAULT 0.0000,
    currency CHAR(3) NOT NULL DEFAULT 'EUR',
    order_url VARCHAR(500) NOT NULL DEFAULT '',
    external_sku VARCHAR(100) NOT NULL DEFAULT '',
    is_preferred TINYINT(1) NOT NULL DEFAULT 0,
    sort_order INT NOT NULL DEFAULT 0,
    note VARCHAR(500) NOT NULL DEFAULT '',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_dg_aps_article (article_id, sort_order, id),
    KEY idx_dg_aps_preferred (article_id, is_preferred),
    KEY idx_dg_aps_supplier (supplier_contact_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
