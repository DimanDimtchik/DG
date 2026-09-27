-- Urlaubsplanungs-Erinnerungen (unter Schwelle % Jahresanspruch, typ. März/April)

CREATE TABLE IF NOT EXISTS dg_time_vacation_planning_reminders (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    contact_id INT UNSIGNED NOT NULL,
    year SMALLINT UNSIGNED NOT NULL,
    channel VARCHAR(16) NOT NULL DEFAULT 'auto',
    planned_percent DECIMAL(5,1) NOT NULL DEFAULT 0,
    days_planned DECIMAL(6,1) NOT NULL DEFAULT 0,
    days_budget DECIMAL(6,1) NOT NULL DEFAULT 0,
    reminder_sent_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_vac_plan_reminder_auto (contact_id, year, channel),
    KEY idx_vac_plan_reminder_year (year),
    CONSTRAINT fk_vac_plan_reminder_contact
        FOREIGN KEY (contact_id) REFERENCES dg_contacts (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
