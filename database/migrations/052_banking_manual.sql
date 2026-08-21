-- Bankabgleich (CAMT), manuelle Buchungen, Abschlussbuchungen
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS dg_bank_transactions (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    import_batch VARCHAR(36) NOT NULL DEFAULT '',
    transaction_date DATE NOT NULL,
    value_date DATE NULL,
    amount DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
    currency CHAR(3) NOT NULL DEFAULT 'EUR',
    counterparty_name VARCHAR(191) NOT NULL DEFAULT '',
    counterparty_iban VARCHAR(34) NOT NULL DEFAULT '',
    reference_text VARCHAR(500) NOT NULL DEFAULT '',
    end_to_end_id VARCHAR(64) NOT NULL DEFAULT '',
    matched_voucher_id INT UNSIGNED NULL,
    match_status ENUM('open', 'matched', 'ignored') NOT NULL DEFAULT 'open',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_bank_tx_date (transaction_date),
    KEY idx_bank_tx_status (match_status),
    KEY idx_bank_tx_batch (import_batch),
    KEY idx_bank_tx_voucher (matched_voucher_id),
    CONSTRAINT fk_bank_tx_voucher FOREIGN KEY (matched_voucher_id) REFERENCES dg_vouchers (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS dg_manual_journal_batches (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    batch_date DATE NOT NULL,
    description VARCHAR(500) NOT NULL DEFAULT '',
    fiscal_year SMALLINT UNSIGNED NOT NULL,
    created_by INT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_manual_batch_year (fiscal_year),
    CONSTRAINT fk_manual_batch_user FOREIGN KEY (created_by) REFERENCES dg_users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE dg_ledger_postings
    ADD COLUMN manual_batch_id INT UNSIGNED NULL AFTER voucher_id,
    ADD KEY idx_ledger_manual_batch (manual_batch_id);
