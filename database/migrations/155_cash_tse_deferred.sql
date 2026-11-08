-- Registerkasse: Nachsignatur-Queue-Flag (K14d)
-- Nur setzen wenn Provider supportsDeferredSignature() === true.

SET NAMES utf8mb4;

ALTER TABLE dg_cash_receipts
    ADD COLUMN tse_deferred_pending TINYINT(1) NOT NULL DEFAULT 0 AFTER tse_outage,
    ADD KEY idx_cash_receipt_tse_deferred (tse_deferred_pending, status, register_id);
