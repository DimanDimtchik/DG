-- Buchungsjournal (doppelte Buchführung) + Geschäftsjahre für Jahresabschluss
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS dg_ledger_postings (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    fiscal_year SMALLINT UNSIGNED NOT NULL,
    posting_date DATE NOT NULL,
    voucher_id INT UNSIGNED NULL,
    account_number VARCHAR(8) NOT NULL,
    contra_account VARCHAR(16) NOT NULL DEFAULT '',
    side ENUM('debit', 'credit') NOT NULL,
    amount DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
    tax_rate TINYINT UNSIGNED NOT NULL DEFAULT 0,
    description VARCHAR(500) NOT NULL DEFAULT '',
    source VARCHAR(24) NOT NULL DEFAULT 'voucher',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_ledger_year_account (fiscal_year, account_number),
    KEY idx_ledger_account_date (account_number, posting_date),
    KEY idx_ledger_voucher (voucher_id),
    KEY idx_ledger_source (source),
    CONSTRAINT fk_ledger_voucher FOREIGN KEY (voucher_id) REFERENCES dg_vouchers (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS dg_fiscal_years (
    year SMALLINT UNSIGNED NOT NULL,
    status ENUM('open', 'closed') NOT NULL DEFAULT 'open',
    closed_at DATETIME NULL,
    closed_by INT UNSIGNED NULL,
    note VARCHAR(255) NOT NULL DEFAULT '',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (year),
    CONSTRAINT fk_fiscal_year_closed_by FOREIGN KEY (closed_by) REFERENCES dg_users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
