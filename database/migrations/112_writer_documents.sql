-- Dokumentenverfasser D1: Briefe / freie Dokumente (Entwurf/Final + Versionshistorie)
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS dg_writer_documents (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    status ENUM('draft', 'final', 'archived') NOT NULL DEFAULT 'draft',
    version INT UNSIGNED NOT NULL DEFAULT 1,
    title VARCHAR(191) NOT NULL DEFAULT '',
    subject VARCHAR(500) NOT NULL DEFAULT '',
    greeting VARCHAR(255) NOT NULL DEFAULT '',
    closing VARCHAR(255) NOT NULL DEFAULT '',
    signature VARCHAR(255) NOT NULL DEFAULT '',
    recipient_json LONGTEXT NOT NULL,
    info_json LONGTEXT NOT NULL,
    body_json LONGTEXT NOT NULL,
    body_html_cache MEDIUMTEXT NULL,
    contact_id INT UNSIGNED NULL,
    voucher_id INT UNSIGNED NULL,
    created_by INT UNSIGNED NULL,
    updated_by INT UNSIGNED NULL,
    finalized_by INT UNSIGNED NULL,
    finalized_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_writer_docs_status (status),
    KEY idx_writer_docs_updated (updated_at),
    KEY idx_writer_docs_contact (contact_id),
    KEY idx_writer_docs_voucher (voucher_id),
    KEY idx_writer_docs_created_by (created_by),
    CONSTRAINT fk_writer_docs_created_by FOREIGN KEY (created_by) REFERENCES dg_users (id) ON DELETE SET NULL,
    CONSTRAINT fk_writer_docs_updated_by FOREIGN KEY (updated_by) REFERENCES dg_users (id) ON DELETE SET NULL,
    CONSTRAINT fk_writer_docs_finalized_by FOREIGN KEY (finalized_by) REFERENCES dg_users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS dg_writer_document_versions (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    document_id INT UNSIGNED NOT NULL,
    version INT UNSIGNED NOT NULL,
    status ENUM('draft', 'final', 'archived') NOT NULL,
    snapshot_json LONGTEXT NOT NULL,
    created_by INT UNSIGNED NULL,
    note VARCHAR(255) NOT NULL DEFAULT '',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_writer_doc_ver (document_id, version),
    KEY idx_writer_ver_doc (document_id),
    CONSTRAINT fk_writer_ver_doc FOREIGN KEY (document_id) REFERENCES dg_writer_documents (id) ON DELETE CASCADE,
    CONSTRAINT fk_writer_ver_user FOREIGN KEY (created_by) REFERENCES dg_users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
