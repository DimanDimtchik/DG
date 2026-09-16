-- Mengen-Reservierung aus Angebot / Auftragsbestätigung (Phase 1)
CREATE TABLE IF NOT EXISTS dg_stock_reservations (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    article_id INT UNSIGNED NOT NULL,
    voucher_id INT UNSIGNED NOT NULL,
    voucher_item_id INT UNSIGNED NULL DEFAULT NULL,
    quantity DECIMAL(12, 3) NOT NULL DEFAULT 0.000,
    status VARCHAR(20) NOT NULL DEFAULT 'active' COMMENT 'active|released|consumed',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_dg_stock_res_article_status (article_id, status),
    KEY idx_dg_stock_res_voucher (voucher_id, status),
    KEY idx_dg_stock_res_item (voucher_item_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
