-- Lohn L6b: revisionssichere Abrechnungs-PDFs

CREATE TABLE IF NOT EXISTS dg_payroll_documents (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    run_id INT UNSIGNED NOT NULL,
    contact_id INT UNSIGNED NOT NULL,
    kind VARCHAR(32) NOT NULL DEFAULT 'payslip',
    period_year SMALLINT UNSIGNED NOT NULL,
    period_month TINYINT UNSIGNED NOT NULL,
    storage_path VARCHAR(500) NOT NULL,
    checksum VARCHAR(64) NOT NULL DEFAULT '',
    byte_size INT UNSIGNED NOT NULL DEFAULT 0,
    is_final TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_by_user_id INT UNSIGNED NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_payroll_doc_run_contact_kind (run_id, contact_id, kind),
    KEY idx_payroll_doc_contact_period (contact_id, period_year, period_month),
    KEY idx_payroll_doc_final (is_final, period_year, period_month),
    CONSTRAINT fk_payroll_doc_run
        FOREIGN KEY (run_id) REFERENCES dg_payroll_runs (id) ON DELETE CASCADE,
    CONSTRAINT fk_payroll_doc_contact
        FOREIGN KEY (contact_id) REFERENCES dg_contacts (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
