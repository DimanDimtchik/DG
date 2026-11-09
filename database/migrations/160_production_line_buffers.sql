-- FL2a: Fließlinie — Zwischenpuffer nach Station i (vor i+1); letzte Station ohne Puffer
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS dg_production_line_buffers (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    line_id INT UNSIGNED NOT NULL,
    after_station_id INT UNSIGNED NOT NULL,
    capacity_qty DECIMAL(14, 4) NOT NULL,
    min_qty DECIMAL(14, 4) NOT NULL DEFAULT 0.0000,
    unit VARCHAR(32) NOT NULL DEFAULT 'Stk',
    current_qty DECIMAL(14, 4) NOT NULL DEFAULT 0.0000,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_production_line_buffer_after_station (after_station_id),
    KEY idx_production_line_buffers_line (line_id),
    CONSTRAINT fk_production_line_buffers_line FOREIGN KEY (line_id) REFERENCES dg_production_lines (id) ON DELETE CASCADE,
    CONSTRAINT fk_production_line_buffers_after_station FOREIGN KEY (after_station_id) REFERENCES dg_production_line_stations (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
