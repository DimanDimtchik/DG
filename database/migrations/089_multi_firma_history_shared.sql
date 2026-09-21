-- Multi-Firma MF4: Firmendaten-Historie (Instanz), Org Shared-Contacts-Flag, Kontakt-Herkunftshinweis
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS dg_company_master_history (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    valid_from DATE NOT NULL,
    legal_name VARCHAR(191) NOT NULL DEFAULT '',
    company_type VARCHAR(80) NOT NULL DEFAULT '',
    gewinnermittlung VARCHAR(20) NOT NULL DEFAULT '',
    tax_number VARCHAR(80) NOT NULL DEFAULT '',
    vat_id VARCHAR(80) NOT NULL DEFAULT '',
    display_name VARCHAR(191) NOT NULL DEFAULT '',
    fingerprint CHAR(64) NOT NULL DEFAULT '',
    snapshot_json LONGTEXT NOT NULL,
    note VARCHAR(500) NOT NULL DEFAULT '',
    created_by INT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_company_hist_valid (valid_from, id),
    KEY idx_company_hist_fp (fingerprint),
    CONSTRAINT fk_company_hist_user FOREIGN KEY (created_by) REFERENCES dg_users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE dg_kdv_orgs
    ADD COLUMN share_contacts TINYINT(1) NOT NULL DEFAULT 0
        COMMENT 'MF4: Shared Contacts gewünscht (kein Cross-DB-Sync in MF4)' AFTER notes;

ALTER TABLE dg_contacts
    ADD COLUMN origin_firm_note VARCHAR(191) NOT NULL DEFAULT ''
        COMMENT 'MF4: Herkunftshinweis bei geteilten/übernommenen Kontakten' AFTER contact_note;
