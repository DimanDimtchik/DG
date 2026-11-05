-- HR: Personalvorgänge (Abmahnung / Kündigung / Akte)
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS dg_hr_personnel_events (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    contact_id INT UNSIGNED NOT NULL,
    event_type VARCHAR(32) NOT NULL DEFAULT 'note',
    event_date DATE NOT NULL,
    title VARCHAR(255) NOT NULL DEFAULT '',
    summary TEXT NULL,
    facts_text TEXT NULL,
    expectation_text TEXT NULL,
    threat_text TEXT NULL,
    status VARCHAR(32) NOT NULL DEFAULT 'issued',
    delivery_date DATE NULL,
    delivery_channel VARCHAR(32) NOT NULL DEFAULT '',
    delivery_note TEXT NULL,
    file_doc_type VARCHAR(64) NOT NULL DEFAULT '',
    file_index INT NULL,
    file_path VARCHAR(512) NOT NULL DEFAULT '',
    created_by INT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_hr_pe_contact (contact_id),
    KEY idx_hr_pe_type (event_type),
    KEY idx_hr_pe_date (event_date),
    CONSTRAINT fk_hr_pe_contact FOREIGN KEY (contact_id) REFERENCES dg_contacts (id) ON DELETE CASCADE,
    CONSTRAINT fk_hr_pe_user FOREIGN KEY (created_by) REFERENCES dg_users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
