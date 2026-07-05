-- Mitarbeiterdaten (nur Rolle Mitarbeiter / Administrator)
SET NAMES utf8mb4;

ALTER TABLE dg_contacts
  ADD COLUMN IF NOT EXISTS employee_data JSON NULL AFTER bank_accounts,
  ADD COLUMN IF NOT EXISTS employee_files JSON NULL AFTER employee_data;
