-- Registerkasse: TSE-Zuordnung je Kasse (K1d)
-- Secrets (API-Key/Secret) bleiben in Instanz-Settings/Env — nicht in dieser Tabelle.
-- Fachlich: 1 TSS pro Filiale, 1 Client pro Kasse (tss_id darf je location mehrfach gleich sein).

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS dg_cash_tse_assignments (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    register_id INT UNSIGNED NOT NULL,
    provider VARCHAR(32) NOT NULL DEFAULT 'fiskaly',
    environment VARCHAR(8) NOT NULL DEFAULT 'test',
    tss_id CHAR(36) NOT NULL DEFAULT '',
    client_id CHAR(36) NOT NULL DEFAULT '',
    tss_serial_number VARCHAR(128) NOT NULL DEFAULT '',
    client_serial_number VARCHAR(70) NOT NULL DEFAULT '',
    certificate_valid_until DATE NULL,
    last_state VARCHAR(64) NOT NULL DEFAULT '',
    last_checked_at DATETIME NULL,
    last_error VARCHAR(500) NOT NULL DEFAULT '',
    notes VARCHAR(500) NOT NULL DEFAULT '',
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_cash_tse_register (register_id),
    KEY idx_cash_tse_provider (provider, environment),
    KEY idx_cash_tse_active (is_active),
    CONSTRAINT fk_cash_tse_register FOREIGN KEY (register_id)
        REFERENCES dg_cash_registers (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
