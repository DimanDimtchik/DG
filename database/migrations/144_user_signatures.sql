-- ES1b: User-Unterschriften mit Verwendungszeitraum (Historie, kein Löschen der Zeilen)
-- Bilder in dg_media; Metadaten hier. Wechsel = valid_to setzen + neue Zeile (App ES1c).
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS dg_user_signatures (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id INT UNSIGNED NOT NULL,
    contact_id INT UNSIGNED NOT NULL,
    media_id VARCHAR(32) NOT NULL,
    source ENUM('upload', 'canvas', 'app', 'import') NOT NULL,
    source_ref VARCHAR(191) NOT NULL DEFAULT '',
    valid_from DATETIME NOT NULL,
    valid_to DATETIME NULL COMMENT 'NULL = aktuell aktiv',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_by_user_id INT UNSIGNED NULL,
    PRIMARY KEY (id),
    KEY idx_user_signatures_user (user_id),
    KEY idx_user_signatures_contact (contact_id),
    KEY idx_user_signatures_user_valid_to (user_id, valid_to),
    KEY idx_user_signatures_contact_valid_from (contact_id, valid_from),
    KEY idx_user_signatures_media (media_id),
    CONSTRAINT fk_user_signatures_user
        FOREIGN KEY (user_id) REFERENCES dg_users (id) ON DELETE RESTRICT,
    CONSTRAINT fk_user_signatures_contact
        FOREIGN KEY (contact_id) REFERENCES dg_contacts (id) ON DELETE RESTRICT,
    CONSTRAINT fk_user_signatures_media
        FOREIGN KEY (media_id) REFERENCES dg_media (media_id) ON DELETE RESTRICT,
    CONSTRAINT fk_user_signatures_created_by
        FOREIGN KEY (created_by_user_id) REFERENCES dg_users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
