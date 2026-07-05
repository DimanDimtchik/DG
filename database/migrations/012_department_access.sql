-- Abteilungsrechte: HR-Kennzeichnung, optionales Löschen, Sidebar-Module
SET NAMES utf8mb4;

ALTER TABLE dg_departments
    ADD COLUMN is_hr TINYINT(1) NOT NULL DEFAULT 0 AFTER description,
    ADD COLUMN allow_contact_delete TINYINT(1) NOT NULL DEFAULT 0 AFTER is_hr;

CREATE TABLE IF NOT EXISTS dg_department_module_access (
    department_id VARCHAR(64) NOT NULL,
    module_key VARCHAR(32) NOT NULL,
    access_level ENUM('none', 'partial', 'full') NOT NULL DEFAULT 'partial',
    PRIMARY KEY (department_id, module_key),
    CONSTRAINT fk_dept_mod_dept FOREIGN KEY (department_id) REFERENCES dg_departments (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
