-- Einkaufsliste / Ignore (Phase 3)
CREATE TABLE IF NOT EXISTS dg_purchase_list_items (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    article_id INT UNSIGNED NOT NULL,
    reason VARCHAR(32) NOT NULL DEFAULT 'below_min' COMMENT 'below_min|missing_for_voucher|manual',
    suggested_qty DECIMAL(12, 3) NOT NULL DEFAULT 0.000,
    source_voucher_id INT UNSIGNED NULL DEFAULT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'open' COMMENT 'open|ignored|ordered|done',
    ignored_at DATETIME NULL DEFAULT NULL,
    ignored_by INT UNSIGNED NULL DEFAULT NULL,
    note VARCHAR(500) NOT NULL DEFAULT '',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_dg_pli_article (article_id),
    KEY idx_dg_pli_status (status, updated_at),
    KEY idx_dg_pli_voucher (source_voucher_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
