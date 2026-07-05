-- DG CRM – erste Tabellen (MariaDB / MySQL)
-- In phpMyAdmin ausführen oder: php bin/db-migrate.php

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS dg_users (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    username VARCHAR(60) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    email VARCHAR(191) NOT NULL DEFAULT '',
    display_name VARCHAR(191) NOT NULL DEFAULT '',
    role VARCHAR(50) NOT NULL DEFAULT 'dg_eigenmitarbeiter',
    employee_active TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY username (username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS dg_departments (
    id VARCHAR(64) NOT NULL,
    name VARCHAR(191) NOT NULL,
    description TEXT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS dg_department_members (
    department_id VARCHAR(64) NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    member_role ENUM('member', 'leader') NOT NULL DEFAULT 'member',
    PRIMARY KEY (department_id, user_id),
    KEY user_id (user_id),
    CONSTRAINT fk_dept_member_dept FOREIGN KEY (department_id) REFERENCES dg_departments (id) ON DELETE CASCADE,
    CONSTRAINT fk_dept_member_user FOREIGN KEY (user_id) REFERENCES dg_users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
