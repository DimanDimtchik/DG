-- Recruiting Phase B1: Xing/LinkedIn-Checkboxen + Publish-Status/Protokoll (API folgt)
SET NAMES utf8mb4;

ALTER TABLE dg_job_postings
    ADD COLUMN export_xing TINYINT(1) NOT NULL DEFAULT 0 AFTER department_id,
    ADD COLUMN export_linkedin TINYINT(1) NOT NULL DEFAULT 0 AFTER export_xing,
    ADD COLUMN xing_publish_status VARCHAR(32) NOT NULL DEFAULT 'none' AFTER export_linkedin,
    ADD COLUMN linkedin_publish_status VARCHAR(32) NOT NULL DEFAULT 'none' AFTER xing_publish_status,
    ADD COLUMN xing_published_at DATETIME NULL AFTER linkedin_publish_status,
    ADD COLUMN linkedin_published_at DATETIME NULL AFTER xing_published_at,
    ADD COLUMN xing_external_ref VARCHAR(191) NOT NULL DEFAULT '' AFTER linkedin_published_at,
    ADD COLUMN linkedin_external_ref VARCHAR(191) NOT NULL DEFAULT '' AFTER xing_external_ref,
    ADD COLUMN xing_last_message VARCHAR(500) NOT NULL DEFAULT '' AFTER linkedin_external_ref,
    ADD COLUMN linkedin_last_message VARCHAR(500) NOT NULL DEFAULT '' AFTER xing_last_message;

CREATE TABLE IF NOT EXISTS dg_job_posting_publish_events (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    job_posting_id INT UNSIGNED NOT NULL,
    channel VARCHAR(32) NOT NULL,
    event_kind VARCHAR(32) NOT NULL DEFAULT 'note',
    from_status VARCHAR(32) NULL,
    to_status VARCHAR(32) NULL,
    body TEXT NULL,
    created_by INT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_job_publish_event_job (job_posting_id),
    KEY idx_job_publish_event_channel (channel),
    CONSTRAINT fk_job_publish_event_job FOREIGN KEY (job_posting_id) REFERENCES dg_job_postings (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
