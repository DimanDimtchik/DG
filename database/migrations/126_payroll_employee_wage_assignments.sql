-- Lohn L3c: MA ↔ Lohnart (Stamm-Zuordnung, ohne Monatslauf)

CREATE TABLE IF NOT EXISTS dg_payroll_employee_wage_assignments (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    contact_id INT UNSIGNED NOT NULL,
    wage_type_id INT UNSIGNED NOT NULL,
    amount DECIMAL(12, 4) NOT NULL DEFAULT 0.0000,
    valid_from DATE NULL,
    valid_to DATE NULL,
    priority INT NOT NULL DEFAULT 0,
    notes VARCHAR(500) NOT NULL DEFAULT '',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_payroll_ewa_contact_prio (contact_id, priority, id),
    KEY idx_payroll_ewa_wage_type (wage_type_id),
    KEY idx_payroll_ewa_validity (contact_id, valid_from, valid_to),
    CONSTRAINT fk_payroll_ewa_contact
        FOREIGN KEY (contact_id) REFERENCES dg_contacts (id) ON DELETE CASCADE,
    CONSTRAINT fk_payroll_ewa_wage_type
        FOREIGN KEY (wage_type_id) REFERENCES dg_payroll_wage_types (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
