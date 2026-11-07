-- Registerkasse: Audit-Log + Export-Metadaten (K1i)
-- Getrennt von dg_audit_log (allgemein) und FiBu-GoBD-Export.

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS dg_cash_audit_log (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    location_id INT UNSIGNED NULL,
    register_id INT UNSIGNED NULL,
    session_id INT UNSIGNED NULL,
    receipt_id INT UNSIGNED NULL,
    user_id INT UNSIGNED NULL,
    event_type VARCHAR(64) NOT NULL,
    severity VARCHAR(16) NOT NULL DEFAULT 'info',
    message VARCHAR(500) NOT NULL DEFAULT '',
    details_json TEXT NULL,
    ip VARCHAR(45) NOT NULL DEFAULT '',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_cash_audit_created (created_at),
    KEY idx_cash_audit_event (event_type, created_at),
    KEY idx_cash_audit_register (register_id, created_at),
    KEY idx_cash_audit_receipt (receipt_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS dg_cash_exports (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    location_id INT UNSIGNED NULL,
    register_id INT UNSIGNED NULL,
    export_type VARCHAR(32) NOT NULL,
    date_from DATE NULL,
    date_to DATE NULL,
    status VARCHAR(16) NOT NULL DEFAULT 'pending',
    file_path VARCHAR(500) NOT NULL DEFAULT '',
    file_checksum VARCHAR(128) NOT NULL DEFAULT '',
    created_by INT UNSIGNED NULL,
    error_message VARCHAR(500) NOT NULL DEFAULT '',
    meta_json TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    finished_at DATETIME NULL,
    PRIMARY KEY (id),
    KEY idx_cash_export_type (export_type, created_at),
    KEY idx_cash_export_status (status),
    KEY idx_cash_export_register (register_id, date_from, date_to)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
