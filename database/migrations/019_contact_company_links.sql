-- Verknüpfung Person ↔ Firma (Beschäftigung / Ansprechpartner)
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS dg_contact_company_links (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    company_contact_id INT UNSIGNED NOT NULL,
    person_contact_id INT UNSIGNED NOT NULL,
    responsibility VARCHAR(191) NOT NULL DEFAULT '',
    work_email VARCHAR(191) NOT NULL DEFAULT '',
    work_phone VARCHAR(50) NOT NULL DEFAULT '',
    availability VARCHAR(255) NOT NULL DEFAULT '',
    sort_order INT NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uniq_company_person (company_contact_id, person_contact_id),
    KEY idx_company (company_contact_id),
    KEY idx_person (person_contact_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
