-- Globale Kalender-Arbeitszeiten (Öffnungs-/Buchungszeiten ab Stichtag)

CREATE TABLE IF NOT EXISTS dg_calendar_working_hours (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    start_date DATE NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    weekdays VARCHAR(20) NOT NULL DEFAULT '1,2,3,4,5',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_start_date (start_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
