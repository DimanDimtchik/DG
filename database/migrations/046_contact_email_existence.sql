-- E-Mail-Existenzprüfung bei Kontakten (Kunden) speichern
SET NAMES utf8mb4;

ALTER TABLE dg_contacts
  ADD COLUMN email_existence_status VARCHAR(20) NOT NULL DEFAULT 'unknown' AFTER email_2,
  ADD COLUMN email_existence_detail VARCHAR(255) NOT NULL DEFAULT '' AFTER email_existence_status,
  ADD COLUMN email_existence_checked_at DATETIME NULL AFTER email_existence_detail;
