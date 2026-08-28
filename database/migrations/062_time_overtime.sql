-- Zeiterfassung Phase 2 (Teil): Tagesaggregation + Überstunden-Lots (6-Monats-Regel)

CREATE TABLE IF NOT EXISTS dg_time_work_days (
    contact_id INT UNSIGNED NOT NULL,
    work_date DATE NOT NULL,
    scheduled_minutes INT UNSIGNED NOT NULL DEFAULT 0,
    worked_minutes INT UNSIGNED NOT NULL DEFAULT 0,
    break_minutes INT UNSIGNED NOT NULL DEFAULT 0,
    overtime_minutes INT UNSIGNED NOT NULL DEFAULT 0,
    status VARCHAR(16) NOT NULL DEFAULT 'closed',
    aggregated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (contact_id, work_date),
    KEY idx_time_work_days_date (work_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS dg_time_overtime_lots (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    contact_id INT UNSIGNED NOT NULL,
    accrued_date DATE NOT NULL,
    minutes INT UNSIGNED NOT NULL,
    minutes_remaining INT UNSIGNED NOT NULL,
    expires_at DATE NOT NULL,
    reminder_due_at DATE NOT NULL,
    reminder_sent_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_overtime_lot_contact_day (contact_id, accrued_date),
    KEY idx_overtime_lot_reminder (reminder_due_at, reminder_sent_at),
    KEY idx_overtime_lot_expires (expires_at, minutes_remaining)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
