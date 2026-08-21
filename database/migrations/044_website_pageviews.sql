-- Lokale Website-Seitenaufrufe (nur bei Statistik-Consent)
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS dg_website_pageviews (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    viewed_at DATETIME NOT NULL,
    path VARCHAR(255) NOT NULL DEFAULT '/',
    page_id INT UNSIGNED NULL,
    referrer_host VARCHAR(191) NOT NULL DEFAULT '',
    PRIMARY KEY (id),
    KEY idx_wpv_viewed (viewed_at),
    KEY idx_wpv_path_day (path, viewed_at),
    KEY idx_wpv_page (page_id, viewed_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
