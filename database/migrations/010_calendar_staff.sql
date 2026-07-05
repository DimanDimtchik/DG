-- Kalender: Bereiche, Mitarbeiter, Arbeitszeiten, Abwesenheiten
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS dg_calendar_areas (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(120) NOT NULL,
    sort_order INT UNSIGNED NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_sort (sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS dg_calendar_employees (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(191) NOT NULL,
    user_id INT UNSIGNED NOT NULL DEFAULT 0,
    supervisor_id INT UNSIGNED NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    sort_order INT UNSIGNED NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_sort (sort_order),
    KEY idx_user (user_id),
    KEY idx_supervisor (supervisor_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS dg_calendar_employee_areas (
    employee_id INT UNSIGNED NOT NULL,
    area_id INT UNSIGNED NOT NULL,
    PRIMARY KEY (employee_id, area_id),
    KEY idx_area (area_id),
    CONSTRAINT fk_cal_emp_area_employee FOREIGN KEY (employee_id) REFERENCES dg_calendar_employees (id) ON DELETE CASCADE,
    CONSTRAINT fk_cal_emp_area_area FOREIGN KEY (area_id) REFERENCES dg_calendar_areas (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS dg_calendar_employee_hours (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    employee_id INT UNSIGNED NOT NULL,
    weekday TINYINT UNSIGNED NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    PRIMARY KEY (id),
    KEY idx_employee_weekday (employee_id, weekday),
    CONSTRAINT fk_cal_hours_employee FOREIGN KEY (employee_id) REFERENCES dg_calendar_employees (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS dg_calendar_employee_absences (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    employee_id INT UNSIGNED NOT NULL,
    absence_type VARCHAR(20) NOT NULL DEFAULT 'vacation',
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    note VARCHAR(255) NOT NULL DEFAULT '',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_employee_dates (employee_id, start_date, end_date),
    CONSTRAINT fk_cal_absence_employee FOREIGN KEY (employee_id) REFERENCES dg_calendar_employees (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
