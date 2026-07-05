-- Historie vergangener Nummernkreis-Formeln

CREATE TABLE IF NOT EXISTS dg_number_range_history (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    document_type VARCHAR(32) NOT NULL,
    prefix VARCHAR(191) NOT NULL DEFAULT '',
    number_pattern VARCHAR(191) NOT NULL DEFAULT '{NR}',
    suffix VARCHAR(191) NOT NULL DEFAULT '',
    number_display VARCHAR(16) NOT NULL DEFAULT 'decimal',
    number_pad TINYINT UNSIGNED NOT NULL DEFAULT 0,
    country_code CHAR(2) NOT NULL DEFAULT 'DE',
    formula_label VARCHAR(500) NOT NULL,
    counter_from INT UNSIGNED NOT NULL DEFAULT 1,
    counter_to INT UNSIGNED NULL,
    used_from DATETIME NOT NULL,
    used_until DATETIME NULL,
    PRIMARY KEY (id),
    KEY idx_number_range_history_type (document_type, used_from DESC),
    KEY idx_number_range_history_active (document_type, used_until)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
