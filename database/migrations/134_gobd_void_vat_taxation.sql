-- GoBD: Beleg-/Journal-Storno statt Löschen · Ist-/Soll-Versteuerung mit FA-Bescheid
SET NAMES utf8mb4;

ALTER TABLE dg_vouchers
    ADD COLUMN voided_at DATETIME NULL DEFAULT NULL AFTER is_draft,
    ADD COLUMN voided_by INT UNSIGNED NULL DEFAULT NULL AFTER voided_at,
    ADD KEY idx_vouchers_voided (voided_at);

ALTER TABLE dg_manual_journal_batches
    ADD COLUMN voided_at DATETIME NULL DEFAULT NULL AFTER created_at,
    ADD COLUMN voided_by INT UNSIGNED NULL DEFAULT NULL AFTER voided_at,
    ADD COLUMN void_reason VARCHAR(500) NOT NULL DEFAULT '' AFTER voided_by,
    ADD COLUMN void_batch_id INT UNSIGNED NULL DEFAULT NULL AFTER void_reason,
    ADD KEY idx_manual_batch_voided (voided_at);

CREATE TABLE IF NOT EXISTS dg_vat_taxation_regimes (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    method ENUM('soll', 'ist') NOT NULL DEFAULT 'soll',
    valid_from DATE NOT NULL,
    bescheid_date DATE NULL,
    aktenzeichen VARCHAR(120) NOT NULL DEFAULT '',
    finanzamt VARCHAR(255) NOT NULL DEFAULT '',
    file_stored_name VARCHAR(255) NOT NULL DEFAULT '',
    file_original_name VARCHAR(255) NOT NULL DEFAULT '',
    file_mime VARCHAR(120) NOT NULL DEFAULT '',
    file_size INT UNSIGNED NOT NULL DEFAULT 0,
    note VARCHAR(500) NOT NULL DEFAULT '',
    created_by INT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    superseded_at DATETIME NULL DEFAULT NULL,
    PRIMARY KEY (id),
    KEY idx_vat_tax_valid (valid_from),
    KEY idx_vat_tax_active (superseded_at, valid_from),
    CONSTRAINT fk_vat_tax_user FOREIGN KEY (created_by) REFERENCES dg_users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
