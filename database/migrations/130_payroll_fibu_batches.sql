-- Lohn L7b: Meta für FiBu-Sammelbuchungen (Idempotenz je Run)

CREATE TABLE IF NOT EXISTS dg_payroll_fibu_batches (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    run_id INT UNSIGNED NOT NULL,
    manual_batch_id INT UNSIGNED NOT NULL,
    posting_date DATE NOT NULL,
    checksum VARCHAR(64) NOT NULL DEFAULT '',
    kind VARCHAR(32) NOT NULL DEFAULT 'regular',
    reason VARCHAR(500) NOT NULL DEFAULT '',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_by_user_id INT UNSIGNED NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_payroll_fibu_run (run_id),
    KEY idx_payroll_fibu_batch (manual_batch_id),
    KEY idx_payroll_fibu_date (posting_date),
    CONSTRAINT fk_payroll_fibu_run
        FOREIGN KEY (run_id) REFERENCES dg_payroll_runs (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
