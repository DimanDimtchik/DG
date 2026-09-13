-- Kichel — Anfragen-Protokoll (Admin-Auswertung, DSGVO: nur CRM-Nutzer, keine Besucher)
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS dg_kichel_log (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id INT UNSIGNED NULL,
    query_text VARCHAR(500) NOT NULL,
    answer_text MEDIUMTEXT NOT NULL,
    response_json LONGTEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_kichel_created (created_at),
    KEY idx_kichel_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
