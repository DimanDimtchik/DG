-- Einheitliche Firmen-Nachweise (empfohlen/optional; Ist-Versteuerung bleibt eigene Tabelle)
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS dg_company_proofs (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    proof_key VARCHAR(64) NOT NULL,
    issuer VARCHAR(255) NOT NULL DEFAULT '',
    document_date DATE NULL,
    reference_no VARCHAR(120) NOT NULL DEFAULT '',
    note VARCHAR(500) NOT NULL DEFAULT '',
    file_stored_name VARCHAR(255) NOT NULL DEFAULT '',
    file_original_name VARCHAR(255) NOT NULL DEFAULT '',
    file_mime VARCHAR(120) NOT NULL DEFAULT '',
    file_size INT UNSIGNED NOT NULL DEFAULT 0,
    created_by INT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    superseded_at DATETIME NULL DEFAULT NULL,
    PRIMARY KEY (id),
    KEY idx_company_proof_key (proof_key, superseded_at),
    CONSTRAINT fk_company_proof_user FOREIGN KEY (created_by) REFERENCES dg_users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
