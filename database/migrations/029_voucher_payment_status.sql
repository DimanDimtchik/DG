-- Zahlungsstatus wie Lexoffice (offen, Kasse, privat, wird abgebucht)
SET NAMES utf8mb4;

ALTER TABLE dg_vouchers
    MODIFY payment_status VARCHAR(24) NOT NULL DEFAULT 'open';

UPDATE dg_vouchers SET payment_status = 'cash' WHERE payment_status = 'paid';
