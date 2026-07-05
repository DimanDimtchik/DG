-- Zentrale Medienbibliothek (nur Bilder, kein HR-Dokumenten-Storage)

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS dg_media (
    media_id VARCHAR(32) NOT NULL,
    original_name VARCHAR(255) NOT NULL DEFAULT '',
    stored_name VARCHAR(255) NOT NULL,
    mime_type VARCHAR(64) NOT NULL,
    extension VARCHAR(16) NOT NULL,
    width INT UNSIGNED NULL,
    height INT UNSIGNED NULL,
    size_bytes BIGINT UNSIGNED NOT NULL DEFAULT 0,
    source_note VARCHAR(500) NOT NULL DEFAULT '',
    title VARCHAR(255) NOT NULL DEFAULT '',
    alt_text VARCHAR(255) NOT NULL DEFAULT '',
    uploaded_by INT UNSIGNED NULL,
    uploaded_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    status VARCHAR(16) NOT NULL DEFAULT 'active',
    PRIMARY KEY (media_id),
    KEY idx_media_status_uploaded (status, uploaded_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS dg_media_usage (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    media_id VARCHAR(32) NOT NULL,
    context_key VARCHAR(191) NOT NULL,
    context_label VARCHAR(255) NOT NULL,
    used_from DATETIME NOT NULL,
    used_until DATETIME NULL,
    last_seen_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uniq_media_context (media_id, context_key),
    KEY idx_media_usage_media (media_id),
    KEY idx_media_usage_active (media_id, used_until)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
