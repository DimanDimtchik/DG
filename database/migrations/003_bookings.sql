-- Terminkalender-Buchungen
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS dg_bookings (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    article_id INT UNSIGNED NOT NULL DEFAULT 0,
    employee_id INT UNSIGNED NOT NULL DEFAULT 0,
    slot_datetime DATETIME NOT NULL,
    customer_name VARCHAR(191) NOT NULL,
    customer_email VARCHAR(191) NOT NULL DEFAULT '',
    customer_phone VARCHAR(50) NOT NULL DEFAULT '',
    status VARCHAR(50) NOT NULL DEFAULT 'gebucht',
    admin_notes TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_slot (slot_datetime),
    KEY idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
