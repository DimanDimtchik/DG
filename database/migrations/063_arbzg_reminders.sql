-- ArbZG-Erinnerungen: 6-Monats-Durchschnitt > 48 h/Woche (WD 6/097/19)

CREATE TABLE IF NOT EXISTS dg_time_arbzg_reminders (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    contact_id INT UNSIGNED NOT NULL,
    period_from DATE NOT NULL,
    period_to DATE NOT NULL,
    avg_weekly_minutes INT UNSIGNED NOT NULL,
    reminder_sent_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_arbzg_reminder_contact_period (contact_id, period_to),
    KEY idx_arbzg_reminder_period (period_to)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
