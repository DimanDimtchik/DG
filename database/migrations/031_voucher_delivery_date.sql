-- Liefer- / Leistungsdatum auf Belegen

ALTER TABLE dg_vouchers
    ADD COLUMN delivery_date DATE NULL AFTER voucher_date;
