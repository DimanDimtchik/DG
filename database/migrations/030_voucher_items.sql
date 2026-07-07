-- Rechnungspositionen (Artikel / Leistungen) für Einnahme-Belege

CREATE TABLE IF NOT EXISTS dg_voucher_items (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    voucher_id INT UNSIGNED NOT NULL,
    line_no SMALLINT UNSIGNED NOT NULL DEFAULT 1,
    article_id INT UNSIGNED NOT NULL DEFAULT 0,
    catalog_kind VARCHAR(16) NOT NULL DEFAULT 'service',
    article_number VARCHAR(64) NOT NULL DEFAULT '',
    title VARCHAR(255) NOT NULL DEFAULT '',
    area_id INT UNSIGNED NOT NULL DEFAULT 0,
    area_name VARCHAR(191) NOT NULL DEFAULT '',
    unit VARCHAR(64) NOT NULL DEFAULT 'Stück',
    quantity DECIMAL(12, 3) NOT NULL DEFAULT 1.000,
    unit_price_gross DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
    gross_amount DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
    tax_rate TINYINT UNSIGNED NOT NULL DEFAULT 19,
    tax_type VARCHAR(32) NOT NULL DEFAULT 'ust19',
    PRIMARY KEY (id),
    KEY idx_voucher_item_voucher (voucher_id),
    CONSTRAINT fk_voucher_item_voucher FOREIGN KEY (voucher_id) REFERENCES dg_vouchers (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
