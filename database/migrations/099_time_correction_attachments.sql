-- Zeiterfassung Z2e+: Nachweis-Anhänge zu Stempel-Korrekturen (Screenshot/PDF)

CREATE TABLE IF NOT EXISTS dg_time_correction_attachments (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    correction_id INT UNSIGNED NOT NULL,
    contact_id INT UNSIGNED NOT NULL,
    original_name VARCHAR(255) NOT NULL,
    stored_name VARCHAR(255) NOT NULL,
    mime VARCHAR(120) NOT NULL DEFAULT '',
    size_bytes INT UNSIGNED NOT NULL DEFAULT 0,
    created_by INT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_time_corr_att_corr (correction_id),
    KEY idx_time_corr_att_contact (contact_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
