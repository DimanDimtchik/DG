-- Lohn L8b: SEPA-Export-Protokoll (pain.001 Sammler)

CREATE TABLE IF NOT EXISTS dg_payroll_sepa_exports (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    run_id INT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_by_user_id INT UNSIGNED NULL,
    is_live TINYINT(1) NOT NULL DEFAULT 0,
    is_probe TINYINT(1) NOT NULL DEFAULT 1,
    tx_count INT UNSIGNED NOT NULL DEFAULT 0,
    ctrl_sum DECIMAL(14, 2) NOT NULL DEFAULT 0.00,
    msg_id VARCHAR(35) NOT NULL DEFAULT '',
    checksum VARCHAR(64) NOT NULL DEFAULT '',
    filename VARCHAR(200) NOT NULL DEFAULT '',
    storage_path VARCHAR(500) NOT NULL DEFAULT '',
    reason VARCHAR(500) NOT NULL DEFAULT '',
    PRIMARY KEY (id),
    KEY idx_payroll_sepa_run (run_id, id),
    KEY idx_payroll_sepa_created (created_at),
    CONSTRAINT fk_payroll_sepa_run
        FOREIGN KEY (run_id) REFERENCES dg_payroll_runs (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
