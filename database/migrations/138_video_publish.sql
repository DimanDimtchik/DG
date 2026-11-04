-- Video-Publish Foundation + YouTube (Multi-Tenant = eigene Instanz-DB)
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS dg_video_assets (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    stored_name VARCHAR(191) NOT NULL,
    original_name VARCHAR(255) NOT NULL DEFAULT '',
    mime_type VARCHAR(120) NOT NULL DEFAULT 'video/mp4',
    size_bytes BIGINT UNSIGNED NOT NULL DEFAULT 0,
    sha256 CHAR(64) NOT NULL DEFAULT '',
    created_by INT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_video_assets_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS dg_oauth_connections (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    platform VARCHAR(32) NOT NULL,
    account_id VARCHAR(191) NOT NULL DEFAULT '',
    account_label VARCHAR(255) NOT NULL DEFAULT '',
    access_token_enc TEXT NULL,
    refresh_token_enc TEXT NULL,
    expires_at DATETIME NULL,
    scopes VARCHAR(500) NOT NULL DEFAULT '',
    status VARCHAR(32) NOT NULL DEFAULT 'disconnected',
    meta_json LONGTEXT NULL,
    connected_at DATETIME NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_oauth_platform (platform),
    KEY idx_oauth_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS dg_publish_jobs (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    asset_id INT UNSIGNED NOT NULL,
    platform VARCHAR(32) NOT NULL,
    state VARCHAR(32) NOT NULL DEFAULT 'pending',
    attempts INT UNSIGNED NOT NULL DEFAULT 0,
    last_error VARCHAR(1000) NOT NULL DEFAULT '',
    payload_json LONGTEXT NULL,
    upload_session_url TEXT NULL,
    upload_offset BIGINT UNSIGNED NOT NULL DEFAULT 0,
    external_id VARCHAR(191) NOT NULL DEFAULT '',
    created_by INT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_publish_jobs_state (state),
    KEY idx_publish_jobs_platform (platform),
    KEY idx_publish_jobs_asset (asset_id),
    CONSTRAINT fk_publish_jobs_asset FOREIGN KEY (asset_id) REFERENCES dg_video_assets (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS dg_publish_posts (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    job_id INT UNSIGNED NOT NULL,
    platform VARCHAR(32) NOT NULL,
    external_id VARCHAR(191) NOT NULL DEFAULT '',
    title VARCHAR(255) NOT NULL DEFAULT '',
    description TEXT NULL,
    privacy VARCHAR(32) NOT NULL DEFAULT 'private',
    published_at DATETIME NULL,
    editable_flags VARCHAR(191) NOT NULL DEFAULT 'title,description,privacy,delete',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_publish_posts_job (job_id),
    KEY idx_publish_posts_platform (platform),
    KEY idx_publish_posts_external (external_id),
    CONSTRAINT fk_publish_posts_job FOREIGN KEY (job_id) REFERENCES dg_publish_jobs (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
