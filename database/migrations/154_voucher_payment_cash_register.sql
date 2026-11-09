-- K9b: Beleg-Barzahlung optional an Registerkasse koppeln

SET NAMES utf8mb4;

ALTER TABLE dg_voucher_payments
    ADD COLUMN cash_register_id INT UNSIGNED NULL AFTER payment_method,
    ADD KEY idx_voucher_payments_cash_register (cash_register_id),
    ADD CONSTRAINT fk_voucher_payment_cash_register
        FOREIGN KEY (cash_register_id) REFERENCES dg_cash_registers (id) ON DELETE RESTRICT;
