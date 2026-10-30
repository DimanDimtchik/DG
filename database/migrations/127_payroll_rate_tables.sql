-- Lohn L4b: versionierte Beitrags-/Grenzwert-Tabellen (gültig_ab)

CREATE TABLE IF NOT EXISTS dg_payroll_rate_sets (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    valid_from DATE NOT NULL,
    valid_to DATE NULL,
    label VARCHAR(120) NOT NULL,
    source_note VARCHAR(255) NOT NULL DEFAULT '',
    checksum VARCHAR(64) NOT NULL DEFAULT '',
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_by_user_id INT UNSIGNED NULL,
    superseded_at DATETIME NULL,
    superseded_by_user_id INT UNSIGNED NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_payroll_rate_set_from_label (valid_from, label),
    KEY idx_payroll_rate_set_active_from (is_active, valid_from)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS dg_payroll_rate_params (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    rate_set_id INT UNSIGNED NOT NULL,
    param_key VARCHAR(64) NOT NULL,
    param_value DECIMAL(18, 8) NOT NULL DEFAULT 0,
    PRIMARY KEY (id),
    UNIQUE KEY uq_payroll_rate_param (rate_set_id, param_key),
    KEY idx_payroll_rate_param_key (param_key),
    CONSTRAINT fk_payroll_rate_param_set
        FOREIGN KEY (rate_set_id) REFERENCES dg_payroll_rate_sets (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
