-- Kontenrahmen (SKR03/SKR04) und Abteilungsmodul Buchhaltung
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS dg_chart_accounts (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    skr_type ENUM('skr03', 'skr04') NOT NULL DEFAULT 'skr03',
    account_number VARCHAR(8) NOT NULL,
    name VARCHAR(255) NOT NULL,
    account_class CHAR(1) NOT NULL DEFAULT '0',
    section ENUM('aktiva', 'passiva', 'aufwand', 'ertrag') NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    hints_json LONGTEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_chart_account (skr_type, account_number),
    KEY idx_chart_section (skr_type, section, account_number)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO dg_department_module_access (department_id, module_key, access_level)
SELECT d.id, 'buchhaltung', 'none'
FROM dg_departments d
WHERE NOT EXISTS (
    SELECT 1 FROM dg_department_module_access m
    WHERE m.department_id = d.id AND m.module_key = 'buchhaltung'
);

INSERT INTO dg_department_module_access (department_id, module_key, access_level)
VALUES ('dept-buchhaltung', 'buchhaltung', 'full')
ON DUPLICATE KEY UPDATE access_level = 'full';
