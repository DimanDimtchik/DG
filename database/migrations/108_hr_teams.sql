-- HR-Teams: Teams je Abteilung, Mitglieder (Kontakte), Inventar (Fahrzeug/Geräte)
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS dg_teams (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    department_id VARCHAR(64) NOT NULL,
    name VARCHAR(191) NOT NULL,
    description TEXT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    sort_order INT NOT NULL DEFAULT 0,
    created_by INT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_teams_department (department_id),
    KEY idx_teams_active (is_active),
    CONSTRAINT fk_teams_department FOREIGN KEY (department_id) REFERENCES dg_departments (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS dg_team_members (
    team_id INT UNSIGNED NOT NULL,
    contact_id INT UNSIGNED NOT NULL,
    member_role ENUM('member', 'lead') NOT NULL DEFAULT 'member',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (team_id, contact_id),
    KEY idx_team_members_contact (contact_id),
    CONSTRAINT fk_team_members_team FOREIGN KEY (team_id) REFERENCES dg_teams (id) ON DELETE CASCADE,
    CONSTRAINT fk_team_members_contact FOREIGN KEY (contact_id) REFERENCES dg_contacts (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS dg_team_assets (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    team_id INT UNSIGNED NOT NULL,
    parent_asset_id INT UNSIGNED NULL,
    kind VARCHAR(32) NOT NULL DEFAULT 'other',
    name VARCHAR(191) NOT NULL,
    inventory_no VARCHAR(64) NOT NULL DEFAULT '',
    notes TEXT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_team_assets_team (team_id),
    KEY idx_team_assets_parent (parent_asset_id),
    CONSTRAINT fk_team_assets_team FOREIGN KEY (team_id) REFERENCES dg_teams (id) ON DELETE CASCADE,
    CONSTRAINT fk_team_assets_parent FOREIGN KEY (parent_asset_id) REFERENCES dg_team_assets (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
