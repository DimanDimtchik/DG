-- Beleg-Dateianhänge (Original-PDF/-Bild pro Beleg, für Anzeige, Import & OCR)
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS dg_voucher_files (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    voucher_id INT UNSIGNED NOT NULL,
    stored_path VARCHAR(255) NOT NULL,
    original_name VARCHAR(255) NOT NULL DEFAULT '',
    mime VARCHAR(100) NOT NULL DEFAULT '',
    size_bytes INT UNSIGNED NOT NULL DEFAULT 0,
    source VARCHAR(24) NOT NULL DEFAULT 'upload',
    uploaded_by INT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_voucher_file_voucher (voucher_id),
    CONSTRAINT fk_voucher_file_voucher FOREIGN KEY (voucher_id) REFERENCES dg_vouchers (id) ON DELETE CASCADE,
    CONSTRAINT fk_voucher_file_user FOREIGN KEY (uploaded_by) REFERENCES dg_users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
