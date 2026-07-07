-- §13b Reverse Charge (Lexoffice-Parität): Typ, UStVA-Kennziffern, Buchungszeilen-Arten
SET NAMES utf8mb4;

ALTER TABLE dg_vouchers
    ADD COLUMN IF NOT EXISTS reverse_charge_type VARCHAR(24) NOT NULL DEFAULT '' AFTER tax_key;

ALTER TABLE dg_vouchers
    ADD COLUMN IF NOT EXISTS ustva_snapshot JSON NULL AFTER reverse_charge_type;

ALTER TABLE dg_voucher_lines
    ADD COLUMN IF NOT EXISTS line_kind VARCHAR(24) NOT NULL DEFAULT 'booking' AFTER line_no;

ALTER TABLE dg_voucher_lines
    ADD COLUMN IF NOT EXISTS ustva_kz VARCHAR(8) NOT NULL DEFAULT '' AFTER tax_rate;

ALTER TABLE dg_voucher_lines
    ADD COLUMN IF NOT EXISTS posting_side ENUM('debit', 'credit') NULL DEFAULT NULL AFTER ustva_kz;

UPDATE dg_vouchers
SET reverse_charge_type = 'eu'
WHERE tax_key = '94' AND reverse_charge_type = '';
