-- Angebot-Mail ↔ Beleg; Antwort-Threading; Annahme-Metadaten; optional für Auto-AB.
ALTER TABLE dg_mail_log
    ADD COLUMN voucher_id INT UNSIGNED NULL AFTER contact_id,
    ADD COLUMN in_reply_to VARCHAR(255) NULL AFTER message_id,
    ADD COLUMN references_header VARCHAR(1000) NULL AFTER in_reply_to,
    ADD KEY idx_mail_log_voucher (voucher_id),
    ADD KEY idx_mail_log_in_reply_to (in_reply_to);

ALTER TABLE dg_vouchers
    ADD COLUMN document_acceptance JSON NULL AFTER document_legal_clauses;
