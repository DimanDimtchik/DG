-- Zeiterfassung Z7: Kiosk-PIN + PIN-Reset-Freigabe (HR)

CREATE TABLE IF NOT EXISTS dg_time_kiosk_pins (
    contact_id INT UNSIGNED NOT NULL,
    pin_hash VARCHAR(255) NOT NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    updated_by INT UNSIGNED NULL DEFAULT NULL,
    PRIMARY KEY (contact_id),
    KEY idx_time_kiosk_pins_updated (updated_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS dg_time_kiosk_pin_resets (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    contact_id INT UNSIGNED NOT NULL,
    status VARCHAR(24) NOT NULL DEFAULT 'pending_hr',
    hr_token_hash CHAR(64) NOT NULL,
    set_token_hash CHAR(64) NULL DEFAULT NULL,
    request_ip VARCHAR(45) NULL DEFAULT NULL,
    requested_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    decided_at DATETIME NULL DEFAULT NULL,
    decided_by INT UNSIGNED NULL DEFAULT NULL,
    set_token_expires_at DATETIME NULL DEFAULT NULL,
    completed_at DATETIME NULL DEFAULT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_time_kiosk_hr_token (hr_token_hash),
    KEY idx_time_kiosk_set_token (set_token_hash),
    KEY idx_time_kiosk_reset_contact (contact_id),
    KEY idx_time_kiosk_reset_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS dg_time_kiosk_sessions (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    contact_id INT UNSIGNED NOT NULL,
    session_hash CHAR(64) NOT NULL,
    expires_at DATETIME NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_time_kiosk_session (session_hash),
    KEY idx_time_kiosk_session_contact (contact_id),
    KEY idx_time_kiosk_session_expires (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
