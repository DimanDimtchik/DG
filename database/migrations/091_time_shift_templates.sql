-- Zeiterfassung Z3b: Schicht-Vorlagen (+ Seed Früh/Spät/Nacht)

CREATE TABLE IF NOT EXISTS dg_time_shift_templates (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(80) NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_time_shift_tpl_active (active, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO dg_time_shift_templates (name, start_time, end_time, sort_order, active)
SELECT v.name, v.start_time, v.end_time, v.sort_order, 1
FROM (
    SELECT 'Früh' AS name, '06:00:00' AS start_time, '14:00:00' AS end_time, 10 AS sort_order
    UNION ALL SELECT 'Spät', '14:00:00', '22:00:00', 20
    UNION ALL SELECT 'Nacht', '22:00:00', '06:00:00', 30
) AS v
WHERE NOT EXISTS (SELECT 1 FROM dg_time_shift_templates LIMIT 1);
