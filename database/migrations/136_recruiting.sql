-- Recruiting Phase A: Stellen, Bewerber, Bewerbungen, Status-Pipeline, Dateien
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS dg_job_postings (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    title VARCHAR(191) NOT NULL,
    slug VARCHAR(191) NOT NULL,
    summary TEXT NULL,
    body TEXT NULL,
    location VARCHAR(191) NOT NULL DEFAULT '',
    employment_type VARCHAR(32) NOT NULL DEFAULT 'full_time',
    status VARCHAR(32) NOT NULL DEFAULT 'draft',
    department_id VARCHAR(64) NULL,
    published_at DATETIME NULL,
    closed_at DATETIME NULL,
    created_by INT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_job_posting_slug (slug),
    KEY idx_job_posting_status (status),
    KEY idx_job_posting_department (department_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS dg_applicants (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    email VARCHAR(191) NOT NULL,
    first_name VARCHAR(120) NOT NULL DEFAULT '',
    last_name VARCHAR(120) NOT NULL DEFAULT '',
    phone VARCHAR(64) NOT NULL DEFAULT '',
    city VARCHAR(120) NOT NULL DEFAULT '',
    notes TEXT NULL,
    contact_id INT UNSIGNED NOT NULL DEFAULT 0,
    privacy_consent_at DATETIME NULL,
    retention_until DATE NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_applicant_email (email),
    KEY idx_applicant_contact (contact_id),
    KEY idx_applicant_retention (retention_until)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS dg_job_applications (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    job_posting_id INT UNSIGNED NOT NULL,
    applicant_id INT UNSIGNED NOT NULL,
    status VARCHAR(32) NOT NULL DEFAULT 'received',
    cover_letter TEXT NULL,
    source VARCHAR(32) NOT NULL DEFAULT 'manual',
    status_changed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    rejected_at DATETIME NULL,
    hired_at DATETIME NULL,
    retention_until DATE NULL,
    created_by INT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_job_application_pair (job_posting_id, applicant_id),
    KEY idx_job_application_status (status),
    KEY idx_job_application_applicant (applicant_id),
    KEY idx_job_application_retention (retention_until),
    CONSTRAINT fk_job_application_posting FOREIGN KEY (job_posting_id) REFERENCES dg_job_postings (id) ON DELETE CASCADE,
    CONSTRAINT fk_job_application_applicant FOREIGN KEY (applicant_id) REFERENCES dg_applicants (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS dg_job_application_events (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    application_id INT UNSIGNED NOT NULL,
    event_kind VARCHAR(32) NOT NULL DEFAULT 'note',
    from_status VARCHAR(32) NULL,
    to_status VARCHAR(32) NULL,
    body TEXT NULL,
    created_by INT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_job_app_event_app (application_id),
    CONSTRAINT fk_job_app_event_app FOREIGN KEY (application_id) REFERENCES dg_job_applications (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS dg_job_application_files (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    application_id INT UNSIGNED NOT NULL,
    file_kind VARCHAR(32) NOT NULL DEFAULT 'other',
    original_name VARCHAR(255) NOT NULL,
    stored_name VARCHAR(255) NOT NULL,
    mime VARCHAR(120) NOT NULL DEFAULT '',
    size_bytes INT UNSIGNED NOT NULL DEFAULT 0,
    created_by INT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_job_app_file_app (application_id),
    CONSTRAINT fk_job_app_file_app FOREIGN KEY (application_id) REFERENCES dg_job_applications (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
