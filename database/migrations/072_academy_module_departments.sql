-- Akademie: optionale Mehrfach-Abteilungen für Videos und Kurse (leer = für alle)

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS dg_academy_module_departments (
    module_id INT UNSIGNED NOT NULL,
    department_id VARCHAR(64) NOT NULL,
    PRIMARY KEY (module_id, department_id),
    KEY idx_academy_md_dept (department_id),
    CONSTRAINT fk_academy_md_module FOREIGN KEY (module_id) REFERENCES dg_academy_modules (id) ON DELETE CASCADE,
    CONSTRAINT fk_academy_md_dept FOREIGN KEY (department_id) REFERENCES dg_departments (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS dg_academy_course_departments (
    course_id INT UNSIGNED NOT NULL,
    department_id VARCHAR(64) NOT NULL,
    PRIMARY KEY (course_id, department_id),
    KEY idx_academy_cd_dept (department_id),
    CONSTRAINT fk_academy_cd_course FOREIGN KEY (course_id) REFERENCES dg_academy_courses (id) ON DELETE CASCADE,
    CONSTRAINT fk_academy_cd_dept FOREIGN KEY (department_id) REFERENCES dg_departments (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO dg_academy_module_departments (module_id, department_id)
SELECT m.id, m.department_id
FROM dg_academy_modules m
WHERE m.department_id IS NOT NULL AND TRIM(m.department_id) <> '';

INSERT IGNORE INTO dg_academy_course_departments (course_id, department_id)
SELECT c.id, c.department_id
FROM dg_academy_courses c
WHERE c.department_id IS NOT NULL AND TRIM(c.department_id) <> '';
