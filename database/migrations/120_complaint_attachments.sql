-- Anhänge zu Reklamationen (Fotos, PDFs, Schreiben)

CREATE TABLE IF NOT EXISTS dg_complaint_attachments (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    complaint_id INT UNSIGNED NOT NULL,
    original_name VARCHAR(255) NOT NULL,
    stored_name VARCHAR(255) NOT NULL,
    mime VARCHAR(120) NOT NULL DEFAULT '',
    size_bytes INT UNSIGNED NOT NULL DEFAULT 0,
    created_by INT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_complaint_att_complaint (complaint_id),
    CONSTRAINT fk_complaint_att_complaint FOREIGN KEY (complaint_id) REFERENCES dg_complaints (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
