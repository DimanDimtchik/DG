-- ELSTER-Abgaben (Historie) — aktiv nach Server-Umzug / ERiC Phase 3
-- @see docs/ELSTER-ERIC-TODO.md

CREATE TABLE IF NOT EXISTS dg_elster_submissions (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    form_type VARCHAR(32) NOT NULL DEFAULT 'ustva',
    period_year SMALLINT UNSIGNED NOT NULL,
    period_month TINYINT UNSIGNED NULL DEFAULT NULL,
    status VARCHAR(16) NOT NULL DEFAULT 'draft',
    test_mode TINYINT(1) NOT NULL DEFAULT 1,
    transfer_ticket VARCHAR(64) NOT NULL DEFAULT '',
    response_summary TEXT NULL,
    pdf_path VARCHAR(255) NULL DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    submitted_at DATETIME NULL DEFAULT NULL,
    submitted_by INT UNSIGNED NULL DEFAULT NULL,
    PRIMARY KEY (id),
    KEY idx_elster_period (form_type, period_year, period_month),
    KEY idx_elster_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
