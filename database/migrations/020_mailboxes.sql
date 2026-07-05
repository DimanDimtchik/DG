-- Postfächer, Zugriffsrechte, Eingang in dg_mail_log

CREATE TABLE IF NOT EXISTS dg_mailboxes (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    type ENUM('private', 'shared') NOT NULL DEFAULT 'shared',
    name VARCHAR(191) NOT NULL DEFAULT '',
    email_address VARCHAR(191) NOT NULL,
    local_part VARCHAR(128) NOT NULL DEFAULT '',
    domain_part VARCHAR(128) NOT NULL DEFAULT '',
    owner_user_id INT UNSIGNED NULL,
    contact_id INT UNSIGNED NULL,
    kas_mail_login VARCHAR(191) NULL,
    kas_provisioned TINYINT(1) NOT NULL DEFAULT 0,
    imap_host VARCHAR(191) NOT NULL DEFAULT '',
    imap_port INT UNSIGNED NOT NULL DEFAULT 993,
    imap_encryption VARCHAR(10) NOT NULL DEFAULT 'ssl',
    imap_username VARCHAR(191) NOT NULL DEFAULT '',
    imap_password VARCHAR(500) NOT NULL DEFAULT '',
    inbound_webhook_token VARCHAR(64) NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uniq_mailbox_email (email_address),
    UNIQUE KEY uniq_mailbox_webhook (inbound_webhook_token),
    KEY idx_mailbox_owner (owner_user_id),
    KEY idx_mailbox_contact (contact_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS dg_mailbox_members (
    mailbox_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    can_read TINYINT(1) NOT NULL DEFAULT 1,
    can_send TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (mailbox_id, user_id),
    KEY idx_mailbox_member_user (user_id),
    CONSTRAINT fk_mailbox_member_box FOREIGN KEY (mailbox_id) REFERENCES dg_mailboxes (id) ON DELETE CASCADE,
    CONSTRAINT fk_mailbox_member_user FOREIGN KEY (user_id) REFERENCES dg_users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE dg_mail_log
    ADD COLUMN mailbox_id INT UNSIGNED NULL AFTER direction,
    ADD COLUMN is_read TINYINT(1) NOT NULL DEFAULT 0 AFTER status,
    ADD COLUMN received_at DATETIME NULL AFTER sent_at,
    ADD KEY idx_mail_mailbox (mailbox_id),
    ADD KEY idx_mail_direction_mailbox (direction, mailbox_id, is_read);

ALTER TABLE dg_mail_log
    MODIFY COLUMN status ENUM('queued', 'sent', 'failed', 'received') NOT NULL DEFAULT 'queued';
