-- Lieferant und Kunde zusammenführen (gleiche Berechtigung)
UPDATE dg_contacts SET contact_role = 'kunde' WHERE contact_role = 'lieferant';
