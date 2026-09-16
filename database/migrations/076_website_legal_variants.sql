-- Rechtstexte je Produkt/Produktgruppe (Tabs auf impressum, datenschutz, agb, widerruf)
CREATE TABLE dg_website_legal_variants (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    page_slug VARCHAR(191) NOT NULL,
    product_key VARCHAR(64) NOT NULL,
    label VARCHAR(191) NOT NULL DEFAULT '',
    status VARCHAR(20) NOT NULL DEFAULT 'draft',
    layout_json LONGTEXT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_legal_variant (page_slug, product_key),
    KEY idx_legal_variant_slug (page_slug),
    KEY idx_legal_variant_status (page_slug, status)
);
