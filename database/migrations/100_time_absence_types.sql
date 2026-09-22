-- Abwesenheitstypen: Überstundenabbau, unbezahlter Urlaub, Sonderurlaub (Kiosk)

ALTER TABLE dg_time_absences
    MODIFY COLUMN type ENUM(
        'vacation',
        'sick',
        'other',
        'ot_comp',
        'unpaid_leave',
        'special_leave'
    ) NOT NULL;
