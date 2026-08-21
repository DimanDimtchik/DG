-- Handelsregister und WEEE-Registrierungsnr. auf Kontakten

ALTER TABLE dg_contacts
    ADD COLUMN commercial_register VARCHAR(191) NOT NULL DEFAULT '' AFTER vat_id,
    ADD COLUMN weee_registration VARCHAR(50) NOT NULL DEFAULT '' AFTER commercial_register;
