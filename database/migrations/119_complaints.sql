-- RG4: Reklamationen (Port von Cloud 116)
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS dg_complaints (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    complaint_number VARCHAR(32) NOT NULL DEFAULT '',
    status VARCHAR(32) NOT NULL DEFAULT 'intake',
    fault VARCHAR(16) NOT NULL DEFAULT 'unclear',
    contact_id INT UNSIGNED NOT NULL DEFAULT 0,
    contact_name VARCHAR(255) NOT NULL DEFAULT '',
    source_voucher_id INT UNSIGNED NOT NULL DEFAULT 0,
    article_id INT UNSIGNED NOT NULL DEFAULT 0,
    article_title VARCHAR(255) NOT NULL DEFAULT '',
    warranty_kind VARCHAR(32) NOT NULL DEFAULT 'none',
    warranty_months SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    warranty_text TEXT NULL,
    subject VARCHAR(255) NOT NULL DEFAULT '',
    intake_text TEXT NULL,
    assessment_text TEXT NULL,
    statement_text TEXT NULL,
    measures_text TEXT NULL,
    apology_gift_voucher_id INT UNSIGNED NOT NULL DEFAULT 0,
    created_by INT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    closed_at DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_complaint_number (complaint_number),
    KEY idx_complaint_status (status),
    KEY idx_complaint_contact (contact_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS dg_complaint_events (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    complaint_id INT UNSIGNED NOT NULL,
    event_kind VARCHAR(32) NOT NULL DEFAULT 'note',
    body TEXT NULL,
    created_by INT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_complaint_event_complaint (complaint_id),
    CONSTRAINT fk_complaint_event_complaint FOREIGN KEY (complaint_id) REFERENCES dg_complaints (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
