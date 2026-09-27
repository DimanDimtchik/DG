-- Mitarbeiter-Zugänge: Audit-Log + Postfach-Passwort-Tokens

CREATE TABLE IF NOT EXISTS dg_staff_access_events (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    contact_id INT UNSIGNED NOT NULL,
    kind VARCHAR(32) NOT NULL,
    channel VARCHAR(16) NOT NULL,
    actor_user_id INT UNSIGNED NULL DEFAULT NULL,
    detail VARCHAR(255) NULL DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_staff_access_contact (contact_id),
    KEY idx_staff_access_kind (kind),
    KEY idx_staff_access_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS dg_mailbox_password_tokens (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    contact_id INT UNSIGNED NOT NULL,
    mailbox_id INT UNSIGNED NOT NULL,
    token_hash CHAR(64) NOT NULL,
    expires_at DATETIME NOT NULL,
    used_at DATETIME NULL DEFAULT NULL,
    created_by INT UNSIGNED NULL DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_mailbox_pw_token (token_hash),
    KEY idx_mailbox_pw_contact (contact_id),
    KEY idx_mailbox_pw_expires (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
