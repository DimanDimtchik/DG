-- IMAP-Ordner pro Nachricht (Posteingang, Gesendet, …)

ALTER TABLE dg_mail_log
    ADD COLUMN imap_folder VARCHAR(191) NOT NULL DEFAULT 'INBOX' AFTER mailbox_id,
    ADD KEY idx_mail_imap_folder (mailbox_id, imap_folder, direction, status);
