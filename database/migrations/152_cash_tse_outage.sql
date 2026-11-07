-- Registerkasse: TSE-Ausfall-Flags (K14a)
-- Pro Kasse (aktueller Zustand) + pro Bon (historisch für Nachschau/DSFinV).

SET NAMES utf8mb4;

ALTER TABLE dg_cash_tse_assignments
    ADD COLUMN outage_active TINYINT(1) NOT NULL DEFAULT 0 AFTER last_error,
    ADD COLUMN outage_since DATETIME NULL AFTER outage_active,
    ADD KEY idx_cash_tse_outage (outage_active, outage_since);

ALTER TABLE dg_cash_receipts
    ADD COLUMN tse_outage TINYINT(1) NOT NULL DEFAULT 0 AFTER tse_tx_id,
    ADD KEY idx_cash_receipt_tse_outage (tse_outage, finished_at);
