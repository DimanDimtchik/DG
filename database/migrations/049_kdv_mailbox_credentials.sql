-- Speichert Info-Postfach-Zugangsdaten nach automatischer KAS-Anlage (KDV)
ALTER TABLE dg_kdv_customers
    ADD COLUMN mailbox_email VARCHAR(191) DEFAULT NULL AFTER shop_session_expires,
    ADD COLUMN mailbox_password VARCHAR(191) DEFAULT NULL AFTER mailbox_email,
    ADD COLUMN mailbox_created_at DATETIME DEFAULT NULL AFTER mailbox_password;
