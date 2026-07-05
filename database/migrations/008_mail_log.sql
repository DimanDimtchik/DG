-- Ausgehende E-Mails: Metadaten in DB, Volltext als .eml unter storage/mail/sent/

CREATE TABLE IF NOT EXISTS dg_mail_log (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    direction ENUM('out', 'in') NOT NULL DEFAULT 'out',
    status ENUM('queued', 'sent', 'failed') NOT NULL DEFAULT 'queued',
    sent_at DATETIME NULL,
    from_address VARCHAR(191) NOT NULL DEFAULT '',
    from_name VARCHAR(191) NOT NULL DEFAULT '',
    to_addresses JSON NOT NULL,
    cc_addresses JSON NULL,
    bcc_addresses JSON NULL,
    subject VARCHAR(500) NOT NULL DEFAULT '',
    body_preview VARCHAR(500) NOT NULL DEFAULT '',
    contact_id INT UNSIGNED NULL,
    user_id INT UNSIGNED NULL,
    message_id VARCHAR(191) NULL,
    error_message TEXT NULL,
    storage_path VARCHAR(500) NOT NULL DEFAULT '',
    size_bytes INT UNSIGNED NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_mail_sent_at (sent_at),
    KEY idx_mail_status (status),
    KEY idx_mail_contact (contact_id),
    KEY idx_mail_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
