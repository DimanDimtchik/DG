-- Fingerabdruck für Bankumsätze (Duplikat-Erkennung / Geisterumsätze)
SET NAMES utf8mb4;

ALTER TABLE dg_bank_transactions
    ADD COLUMN transaction_fingerprint CHAR(64) NOT NULL DEFAULT '' AFTER end_to_end_id,
    ADD KEY idx_bank_tx_fingerprint (transaction_fingerprint);
