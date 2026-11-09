-- K15b: FiBu-Kassenbuch-Zeilen aus Registerkassen-Barzahlung (ohne Beleg-FK).
SET NAMES utf8mb4;

ALTER TABLE dg_cash_journal
    ADD COLUMN cash_receipt_id INT UNSIGNED NULL AFTER voucher_id,
    ADD KEY idx_cash_journal_receipt (cash_receipt_id);
