-- Zeiterfassung: Stempel-Events (Phase 1)

CREATE TABLE IF NOT EXISTS dg_time_clock_events (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    contact_id INT UNSIGNED NOT NULL,
    event_type VARCHAR(16) NOT NULL,
    occurred_at DATETIME NOT NULL,
    source VARCHAR(24) NOT NULL DEFAULT 'web',
    note TEXT NULL,
    created_by INT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_time_clock_contact (contact_id),
    KEY idx_time_clock_occurred (occurred_at),
    KEY idx_time_clock_contact_day (contact_id, occurred_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
