-- Kalender-Mitarbeiter: Kontakt (Personal) Pflicht — Stammdaten nur noch über dg_contacts.
-- Orphans (contact_id = 0): zuerst Auto-Link per PHP (MigrationRunner), dann Deaktivierung.
-- Historische Buchungen behalten employee_id; inaktive Orphans bleiben sichtbar zum Nachpflegen/Löschen.

UPDATE dg_calendar_employees
SET is_active = 0
WHERE (contact_id IS NULL OR contact_id = 0)
  AND is_active = 1;
