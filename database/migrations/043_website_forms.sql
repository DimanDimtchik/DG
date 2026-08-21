-- Website-Formulare (Builder) und Eingänge
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS dg_website_forms (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    title VARCHAR(191) NOT NULL DEFAULT '',
    status VARCHAR(20) NOT NULL DEFAULT 'draft',
    definition_json LONGTEXT NULL,
    created_by INT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_website_form_status (status),
    CONSTRAINT fk_website_form_user FOREIGN KEY (created_by) REFERENCES dg_users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS dg_website_form_submissions (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    form_id INT UNSIGNED NOT NULL,
    payload_json LONGTEXT NULL,
    files_json LONGTEXT NULL,
    ip VARCHAR(64) NOT NULL DEFAULT '',
    user_agent VARCHAR(500) NOT NULL DEFAULT '',
    is_read TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_form_sub_form (form_id, created_at),
    KEY idx_form_sub_unread (form_id, is_read),
    CONSTRAINT fk_form_sub_form FOREIGN KEY (form_id) REFERENCES dg_website_forms (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
