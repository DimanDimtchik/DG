-- Kalender: datumsgenaue Arbeitsplan-Fenster (öffnen Buchungsslots)
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS dg_calendar_employee_day_windows (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    employee_id INT UNSIGNED NOT NULL,
    work_date DATE NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    updated_by INT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_cal_day_windows_emp_date (employee_id, work_date),
    KEY idx_cal_day_windows_date (work_date),
    CONSTRAINT fk_cal_day_windows_employee
        FOREIGN KEY (employee_id) REFERENCES dg_calendar_employees (id) ON DELETE CASCADE,
    CONSTRAINT fk_cal_day_windows_user
        FOREIGN KEY (updated_by) REFERENCES dg_users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
