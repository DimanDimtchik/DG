-- DG CRM Akademie (Lernplattform Phase 1)

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS dg_academy_areas (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    code VARCHAR(32) NOT NULL,
    label VARCHAR(120) NOT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    PRIMARY KEY (id),
    UNIQUE KEY uq_academy_area_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS dg_academy_courses (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    area_id INT UNSIGNED NOT NULL,
    title VARCHAR(200) NOT NULL,
    slug VARCHAR(120) NOT NULL,
    description TEXT NOT NULL,
    version VARCHAR(20) NOT NULL DEFAULT '1.0',
    min_tier ENUM('starter', 'business', 'premium') NOT NULL DEFAULT 'starter',
    access_mode_default ENUM('compare', 'soft', 'hard') NOT NULL DEFAULT 'compare',
    certificate_enabled TINYINT(1) NOT NULL DEFAULT 0,
    certificate_scope ENUM('company', 'platform') NOT NULL DEFAULT 'company',
    certificate_valid_days INT UNSIGNED NOT NULL DEFAULT 365,
    quiz_enabled TINYINT(1) NOT NULL DEFAULT 0,
    quiz_pass_percent TINYINT UNSIGNED NOT NULL DEFAULT 80,
    is_published TINYINT(1) NOT NULL DEFAULT 0,
    sort_order INT NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_academy_course_slug (slug),
    KEY idx_academy_course_area (area_id),
    CONSTRAINT fk_academy_course_area FOREIGN KEY (area_id) REFERENCES dg_academy_areas (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS dg_academy_modules (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    course_id INT UNSIGNED NOT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    title VARCHAR(200) NOT NULL,
    description TEXT NOT NULL,
    provider ENUM('file', 'youtube', 'vimeo', 'external') NOT NULL DEFAULT 'file',
    video_path VARCHAR(500) NOT NULL DEFAULT '',
    external_ref VARCHAR(255) NOT NULL DEFAULT '',
    youtube_visibility ENUM('internal', 'unlisted', 'public') NOT NULL DEFAULT 'internal',
    duration_sec INT UNSIGNED NOT NULL DEFAULT 0,
    min_watch_percent TINYINT UNSIGNED NOT NULL DEFAULT 90,
    max_playback_rate DECIMAL(3, 1) NOT NULL DEFAULT 2.0,
    subtitle_vtt_path VARCHAR(500) NOT NULL DEFAULT '',
    audio_planned TINYINT(1) NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    PRIMARY KEY (id),
    KEY idx_academy_module_course (course_id),
    CONSTRAINT fk_academy_module_course FOREIGN KEY (course_id) REFERENCES dg_academy_courses (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS dg_academy_assignments (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id INT UNSIGNED NOT NULL,
    course_id INT UNSIGNED NOT NULL,
    assigned_by INT UNSIGNED NULL,
    access_mode ENUM('compare', 'soft', 'hard') NOT NULL DEFAULT 'compare',
    due_at DATE NULL,
    status ENUM('open', 'in_progress', 'pending_review', 'completed', 'flagged', 'waived', 'rejected') NOT NULL DEFAULT 'open',
    started_at DATETIME NULL,
    completed_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_academy_assignment_user_course (user_id, course_id),
    KEY idx_academy_assignment_status (status),
    KEY idx_academy_assignment_course (course_id),
    CONSTRAINT fk_academy_assignment_course FOREIGN KEY (course_id) REFERENCES dg_academy_courses (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS dg_academy_rules_acceptance (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id INT UNSIGNED NOT NULL,
    course_id INT UNSIGNED NOT NULL,
    course_version VARCHAR(20) NOT NULL,
    accepted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    ip_address VARCHAR(45) NOT NULL DEFAULT '',
    PRIMARY KEY (id),
    UNIQUE KEY uq_academy_rules_user_course_ver (user_id, course_id, course_version),
    KEY idx_academy_rules_course (course_id),
    CONSTRAINT fk_academy_rules_course FOREIGN KEY (course_id) REFERENCES dg_academy_courses (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS dg_academy_module_progress (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    assignment_id INT UNSIGNED NOT NULL,
    module_id INT UNSIGNED NOT NULL,
    status ENUM('not_started', 'in_progress', 'completed') NOT NULL DEFAULT 'not_started',
    watched_seconds INT UNSIGNED NOT NULL DEFAULT 0,
    last_position_sec INT UNSIGNED NOT NULL DEFAULT 0,
    wall_clock_sec INT UNSIGNED NOT NULL DEFAULT 0,
    max_playback_rate DECIMAL(3, 1) NOT NULL DEFAULT 1.0,
    anomaly_flags JSON NULL,
    completed_at DATETIME NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_academy_progress_assignment_module (assignment_id, module_id),
    KEY idx_academy_progress_module (module_id),
    CONSTRAINT fk_academy_progress_assignment FOREIGN KEY (assignment_id) REFERENCES dg_academy_assignments (id) ON DELETE CASCADE,
    CONSTRAINT fk_academy_progress_module FOREIGN KEY (module_id) REFERENCES dg_academy_modules (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS dg_academy_watch_sessions (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    session_uuid CHAR(36) NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    module_id INT UNSIGNED NOT NULL,
    assignment_id INT UNSIGNED NOT NULL,
    started_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    ended_at DATETIME NULL,
    watched_seconds INT UNSIGNED NOT NULL DEFAULT 0,
    wall_clock_sec INT UNSIGNED NOT NULL DEFAULT 0,
    heartbeat_count INT UNSIGNED NOT NULL DEFAULT 0,
    avg_playback_rate DECIMAL(3, 2) NOT NULL DEFAULT 1.00,
    max_playback_rate DECIMAL(3, 1) NOT NULL DEFAULT 1.0,
    tab_hidden_sec INT UNSIGNED NOT NULL DEFAULT 0,
    anomaly_flags JSON NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_academy_session_uuid (session_uuid),
    KEY idx_academy_session_user (user_id),
    KEY idx_academy_session_module (module_id),
    CONSTRAINT fk_academy_session_assignment FOREIGN KEY (assignment_id) REFERENCES dg_academy_assignments (id) ON DELETE CASCADE,
    CONSTRAINT fk_academy_session_module FOREIGN KEY (module_id) REFERENCES dg_academy_modules (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS dg_academy_watch_events (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    session_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    module_id INT UNSIGNED NOT NULL,
    event_type VARCHAR(32) NOT NULL,
    event_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    payload JSON NULL,
    PRIMARY KEY (id),
    KEY idx_academy_event_session (session_id),
    KEY idx_academy_event_user (user_id),
    CONSTRAINT fk_academy_event_session FOREIGN KEY (session_id) REFERENCES dg_academy_watch_sessions (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS dg_academy_certificates (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    certificate_number VARCHAR(40) NOT NULL,
    assignment_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    course_id INT UNSIGNED NOT NULL,
    course_version VARCHAR(20) NOT NULL,
    scope ENUM('company', 'platform') NOT NULL DEFAULT 'company',
    valid_from DATE NOT NULL,
    valid_until DATE NOT NULL,
    status ENUM('pending', 'approved', 'rejected', 'revoked') NOT NULL DEFAULT 'pending',
    reviewed_by INT UNSIGNED NULL,
    reviewed_at DATETIME NULL,
    review_note VARCHAR(1000) NOT NULL DEFAULT '',
    pdf_path VARCHAR(500) NOT NULL DEFAULT '',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_academy_certificate_number (certificate_number),
    UNIQUE KEY uq_academy_certificate_assignment (assignment_id),
    KEY idx_academy_certificate_status (status),
    CONSTRAINT fk_academy_certificate_assignment FOREIGN KEY (assignment_id) REFERENCES dg_academy_assignments (id) ON DELETE CASCADE,
    CONSTRAINT fk_academy_certificate_course FOREIGN KEY (course_id) REFERENCES dg_academy_courses (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS dg_academy_gates (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    module_key VARCHAR(64) NOT NULL,
    course_id INT UNSIGNED NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    PRIMARY KEY (id),
    UNIQUE KEY uq_academy_gate_module (module_key),
    CONSTRAINT fk_academy_gate_course FOREIGN KEY (course_id) REFERENCES dg_academy_courses (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO dg_academy_areas (code, label, sort_order) VALUES
    ('allgemein', 'Allgemein', 10),
    ('lager', 'Lager', 20),
    ('buchhaltung', 'Buchhaltung', 30),
    ('verkauf', 'Verkauf', 40),
    ('admin', 'Administration', 50)
ON DUPLICATE KEY UPDATE label = VALUES(label);

INSERT INTO dg_academy_courses (area_id, title, slug, description, version, min_tier, access_mode_default, certificate_enabled, certificate_scope, certificate_valid_days, is_published, sort_order)
SELECT a.id, 'Lager — Platz-Check', 'lager-platz-check', 'Einführung in den Platz-Check: Strichcode scannen und manuelle Auswahl in der Lagerstruktur.', '1.0', 'starter', 'compare', 1, 'company', 365, 1, 10
FROM dg_academy_areas a WHERE a.code = 'lager'
ON DUPLICATE KEY UPDATE title = VALUES(title);

INSERT INTO dg_academy_modules (course_id, sort_order, title, description, provider, duration_sec, min_watch_percent, subtitle_vtt_path, is_active)
SELECT c.id, 10, 'Platz-Check — Scannen', 'Strichcode per Handscanner, Tastatur oder Kamera scannen. Anschließend werden Belegung, Reservierung und letzte Bewegungen angezeigt.', 'file', 180, 90, '', 1
FROM dg_academy_courses c WHERE c.slug = 'lager-platz-check'
ON DUPLICATE KEY UPDATE title = VALUES(title);

INSERT INTO dg_academy_modules (course_id, sort_order, title, description, provider, duration_sec, min_watch_percent, subtitle_vtt_path, is_active)
SELECT c.id, 20, 'Platz-Check — Manuell auswählen', 'Ebene wählen (Lagerort bis Stellplatz), kaskadierende Dropdowns nutzen und mit Prüfen das Mini-Audit starten.', 'file', 180, 90, '', 1
FROM dg_academy_courses c WHERE c.slug = 'lager-platz-check'
ON DUPLICATE KEY UPDATE title = VALUES(title);

INSERT INTO dg_academy_gates (module_key, course_id, is_active)
SELECT 'lager', c.id, 0
FROM dg_academy_courses c WHERE c.slug = 'lager-platz-check'
ON DUPLICATE KEY UPDATE course_id = VALUES(course_id);
