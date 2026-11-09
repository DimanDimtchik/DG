-- Registerkasse: §146a-Meldefelder speichern (K19a) — ohne Senden an Finanzamt.
-- Steuerpflichtiger (Instanz) + ERS-/Kassen-Zusatzdaten je Kasse.

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS dg_cash_ers_taxpayer (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    tax_number VARCHAR(13) NOT NULL DEFAULT '',
    tax_office_number VARCHAR(4) NOT NULL DEFAULT '',
    notes VARCHAR(500) NOT NULL DEFAULT '',
    updated_by INT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS dg_cash_ers_clients (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    register_id INT UNSIGNED NOT NULL,
    ers_type VARCHAR(8) NOT NULL DEFAULT '1',
    manufacturer VARCHAR(120) NOT NULL DEFAULT '',
    model VARCHAR(120) NOT NULL DEFAULT '',
    serial_number VARCHAR(120) NOT NULL DEFAULT '',
    software VARCHAR(120) NOT NULL DEFAULT '',
    software_version VARCHAR(64) NOT NULL DEFAULT '',
    date_acquisition DATE NULL,
    date_commissioning DATE NULL,
    date_tss_commissioning DATE NULL,
    date_decommissioning DATE NULL,
    decommissioning_reason VARCHAR(255) NOT NULL DEFAULT '',
    remarks VARCHAR(500) NOT NULL DEFAULT '',
    data_status VARCHAR(16) NOT NULL DEFAULT 'draft',
    notes VARCHAR(500) NOT NULL DEFAULT '',
    updated_by INT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_cash_ers_client_register (register_id),
    KEY idx_cash_ers_client_status (data_status),
    CONSTRAINT fk_cash_ers_client_register FOREIGN KEY (register_id)
        REFERENCES dg_cash_registers (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
