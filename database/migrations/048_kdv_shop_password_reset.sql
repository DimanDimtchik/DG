CREATE TABLE IF NOT EXISTS dg_kdv_password_reset_tokens (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    kdv_customer_id INT UNSIGNED NOT NULL,
    token_hash CHAR(64) NOT NULL,
    expires_at DATETIME NOT NULL,
    used_at DATETIME NULL DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_kdv_pw_reset_hash (token_hash),
    INDEX idx_kdv_pw_reset_customer (kdv_customer_id),
    CONSTRAINT fk_kdv_pw_reset_customer
        FOREIGN KEY (kdv_customer_id) REFERENCES dg_kdv_customers (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS dg_kdv_password_reset_throttle (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    email_hash CHAR(64) NOT NULL,
    attempted_at DATETIME NOT NULL,
    INDEX idx_kdv_pw_throttle (email_hash, attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
