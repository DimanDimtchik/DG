-- CTI / Anrufliste (Fritzbox CallMonitor + Click-to-Call)

CREATE TABLE IF NOT EXISTS dg_call_log (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    direction ENUM('in', 'out') NOT NULL,
    status ENUM('ringing', 'dialing', 'answered', 'ended', 'missed', 'failed') NOT NULL DEFAULT 'ringing',
    phone_e164 VARCHAR(32) NOT NULL DEFAULT '',
    phone_raw VARCHAR(64) NOT NULL DEFAULT '',
    contact_id INT UNSIGNED NULL,
    user_id INT UNSIGNED NULL,
    external_id VARCHAR(64) NOT NULL DEFAULT '',
    started_at DATETIME NOT NULL,
    answered_at DATETIME NULL,
    ended_at DATETIME NULL,
    duration_sec INT UNSIGNED NOT NULL DEFAULT 0,
    note VARCHAR(500) NOT NULL DEFAULT '',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_call_started (started_at),
    KEY idx_call_status (status),
    KEY idx_call_contact (contact_id),
    KEY idx_call_phone (phone_e164),
    KEY idx_call_external (external_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
