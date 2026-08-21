-- Lizenz-Anbindung + Shop-Konto für SaaS-Kunden (KDV)
ALTER TABLE dg_kdv_customers
    ADD COLUMN license_key VARCHAR(40) DEFAULT NULL AFTER notes,
    ADD COLUMN license_remote_id INT UNSIGNED DEFAULT NULL AFTER license_key,
    ADD COLUMN block_reason VARCHAR(60) DEFAULT NULL AFTER license_remote_id,
    ADD COLUMN block_note TEXT DEFAULT NULL AFTER block_reason,
    ADD COLUMN shop_password_hash VARCHAR(255) DEFAULT NULL AFTER block_note,
    ADD COLUMN shop_session_token VARCHAR(64) DEFAULT NULL AFTER shop_password_hash,
    ADD COLUMN shop_session_expires DATETIME DEFAULT NULL AFTER shop_session_token;

ALTER TABLE dg_kdv_customers
    ADD INDEX idx_kdv_contact_email (contact_email),
    ADD INDEX idx_kdv_shop_session (shop_session_token);
