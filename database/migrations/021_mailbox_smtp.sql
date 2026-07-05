-- SMTP pro Postfach (Google, Legacy, Kasserver …) + Provider-Vorlage

ALTER TABLE dg_mailboxes
    ADD COLUMN provider_preset VARCHAR(32) NOT NULL DEFAULT 'manual' AFTER kas_provisioned,
    ADD COLUMN smtp_host VARCHAR(191) NOT NULL DEFAULT '' AFTER imap_password,
    ADD COLUMN smtp_port INT UNSIGNED NOT NULL DEFAULT 587 AFTER smtp_host,
    ADD COLUMN smtp_encryption VARCHAR(10) NOT NULL DEFAULT 'tls' AFTER smtp_port,
    ADD COLUMN smtp_username VARCHAR(191) NOT NULL DEFAULT '' AFTER smtp_encryption,
    ADD COLUMN smtp_password VARCHAR(500) NOT NULL DEFAULT '' AFTER smtp_username,
    ADD COLUMN from_name VARCHAR(191) NOT NULL DEFAULT '' AFTER smtp_password;
