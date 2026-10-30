-- Lohn L9b: SV-Portal-Export-Protokoll (Kontrolllisten, kein Versand)

CREATE TABLE IF NOT EXISTS dg_payroll_sv_exports (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    run_id INT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_by_user_id INT UNSIGNED NULL,
    kind VARCHAR(32) NOT NULL DEFAULT 'combined',
    checksum VARCHAR(64) NOT NULL DEFAULT '',
    filename VARCHAR(200) NOT NULL DEFAULT '',
    storage_path VARCHAR(500) NOT NULL DEFAULT '',
    reason VARCHAR(500) NOT NULL DEFAULT '',
    PRIMARY KEY (id),
    KEY idx_payroll_sv_run (run_id, id),
    KEY idx_payroll_sv_created (created_at),
    CONSTRAINT fk_payroll_sv_run
        FOREIGN KEY (run_id) REFERENCES dg_payroll_runs (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
