-- Lohn L5b: Monatslauf (Runs, MA-Ergebnisse, Lines, Status-Events)

CREATE TABLE IF NOT EXISTS dg_payroll_runs (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    period_year SMALLINT UNSIGNED NOT NULL,
    period_month TINYINT UNSIGNED NOT NULL,
    kind VARCHAR(16) NOT NULL DEFAULT 'regular',
    status VARCHAR(16) NOT NULL DEFAULT 'draft',
    supersedes_run_id INT UNSIGNED NULL,
    is_superseded TINYINT(1) NOT NULL DEFAULT 0,
    rate_set_id INT UNSIGNED NULL,
    label VARCHAR(120) NOT NULL DEFAULT '',
    notes TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_by_user_id INT UNSIGNED NULL,
    approved_at DATETIME NULL,
    approved_by_user_id INT UNSIGNED NULL,
    locked_at DATETIME NULL,
    locked_by_user_id INT UNSIGNED NULL,
    PRIMARY KEY (id),
    KEY idx_payroll_runs_period (period_year, period_month, kind, is_superseded),
    KEY idx_payroll_runs_status (status),
    KEY idx_payroll_runs_supersedes (supersedes_run_id),
    CONSTRAINT fk_payroll_runs_supersedes
        FOREIGN KEY (supersedes_run_id) REFERENCES dg_payroll_runs (id) ON DELETE SET NULL,
    CONSTRAINT fk_payroll_runs_rate_set
        FOREIGN KEY (rate_set_id) REFERENCES dg_payroll_rate_sets (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS dg_payroll_run_employees (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    run_id INT UNSIGNED NOT NULL,
    contact_id INT UNSIGNED NOT NULL,
    status VARCHAR(16) NOT NULL DEFAULT 'pending',
    engine VARCHAR(32) NOT NULL DEFAULT '',
    path VARCHAR(16) NOT NULL DEFAULT '',
    result_json LONGTEXT NULL,
    snapshot_json LONGTEXT NULL,
    warnings_json LONGTEXT NULL,
    net_pay DECIMAL(12, 2) NULL,
    gross_total DECIMAL(12, 2) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_payroll_run_employee (run_id, contact_id),
    KEY idx_payroll_run_emp_contact (contact_id),
    KEY idx_payroll_run_emp_status (run_id, status),
    CONSTRAINT fk_payroll_run_emp_run
        FOREIGN KEY (run_id) REFERENCES dg_payroll_runs (id) ON DELETE CASCADE,
    CONSTRAINT fk_payroll_run_emp_contact
        FOREIGN KEY (contact_id) REFERENCES dg_contacts (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS dg_payroll_run_lines (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    run_id INT UNSIGNED NOT NULL,
    contact_id INT UNSIGNED NOT NULL,
    source VARCHAR(16) NOT NULL DEFAULT 'manual',
    wage_type_id INT UNSIGNED NULL,
    code VARCHAR(32) NOT NULL DEFAULT '',
    amount DECIMAL(12, 4) NOT NULL DEFAULT 0,
    hours DECIMAL(10, 4) NULL,
    unit VARCHAR(16) NOT NULL DEFAULT 'amount',
    direction VARCHAR(16) NOT NULL DEFAULT 'earning',
    taxable TINYINT(1) NOT NULL DEFAULT 1,
    subject_to_sv TINYINT(1) NOT NULL DEFAULT 1,
    sort_order INT NOT NULL DEFAULT 0,
    notes VARCHAR(255) NOT NULL DEFAULT '',
    PRIMARY KEY (id),
    KEY idx_payroll_run_lines_run_contact (run_id, contact_id, sort_order, id),
    CONSTRAINT fk_payroll_run_lines_run
        FOREIGN KEY (run_id) REFERENCES dg_payroll_runs (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS dg_payroll_run_events (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    run_id INT UNSIGNED NOT NULL,
    from_status VARCHAR(16) NOT NULL DEFAULT '',
    to_status VARCHAR(16) NOT NULL DEFAULT '',
    user_id INT UNSIGNED NULL,
    reason VARCHAR(500) NOT NULL DEFAULT '',
    details_json LONGTEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_payroll_run_events_run (run_id, id),
    CONSTRAINT fk_payroll_run_events_run
        FOREIGN KEY (run_id) REFERENCES dg_payroll_runs (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
