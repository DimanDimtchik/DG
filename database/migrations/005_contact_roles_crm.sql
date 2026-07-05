-- Kontakt-Rollen an CRM-Rollen anpassen
UPDATE dg_contacts SET contact_role = 'dg_kunde' WHERE contact_role IN ('kunde', 'lieferant');
UPDATE dg_contacts SET contact_role = 'dg_eigenmitarbeiter' WHERE contact_role = 'mitarbeiter';
