-- Akademie: Abteilungen aus CRM, Video-Bibliothek, Kurs↔Modul M:N

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS dg_academy_course_modules (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    course_id INT UNSIGNED NOT NULL,
    module_id INT UNSIGNED NOT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    PRIMARY KEY (id),
    UNIQUE KEY uq_academy_course_module (course_id, module_id),
    KEY idx_academy_cm_module (module_id),
    CONSTRAINT fk_academy_cm_course FOREIGN KEY (course_id) REFERENCES dg_academy_courses (id) ON DELETE CASCADE,
    CONSTRAINT fk_academy_cm_module FOREIGN KEY (module_id) REFERENCES dg_academy_modules (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE dg_academy_courses
    ADD COLUMN department_id VARCHAR(64) NULL AFTER area_id;

ALTER TABLE dg_academy_modules
    ADD COLUMN department_id VARCHAR(64) NULL AFTER course_id;

-- Bestehende Kurs↔Modul-Verknüpfungen in Junction übernehmen
INSERT IGNORE INTO dg_academy_course_modules (course_id, module_id, sort_order)
SELECT m.course_id, m.id, m.sort_order
FROM dg_academy_modules m
WHERE m.course_id IS NOT NULL AND m.course_id > 0;

-- Abteilung aus Bereichs-Label (Name gleich Abteilung)
UPDATE dg_academy_courses c
INNER JOIN dg_academy_areas a ON a.id = c.area_id
INNER JOIN dg_departments d ON LOWER(TRIM(d.name)) = LOWER(TRIM(a.label))
SET c.department_id = d.id
WHERE c.department_id IS NULL;

-- Fallback: Bereichs-Code in Abteilungs-ID
UPDATE dg_academy_courses c
INNER JOIN dg_academy_areas a ON a.id = c.area_id
INNER JOIN dg_departments d ON (
    d.id LIKE CONCAT('%-', a.code)
    OR d.id LIKE CONCAT('%', a.code, '%')
    OR LOWER(d.name) LIKE CONCAT('%', a.code, '%')
)
SET c.department_id = d.id
WHERE c.department_id IS NULL;

-- Fallback: erste Abteilung
UPDATE dg_academy_courses c
SET c.department_id = (
    SELECT d.id FROM dg_departments d ORDER BY d.sort_order ASC, d.name ASC LIMIT 1
)
WHERE c.department_id IS NULL
  AND EXISTS (SELECT 1 FROM dg_departments LIMIT 1);

-- Module: Abteilung vom Kurs übernehmen
UPDATE dg_academy_modules m
INNER JOIN dg_academy_courses c ON c.id = m.course_id
SET m.department_id = c.department_id
WHERE m.department_id IS NULL AND c.department_id IS NOT NULL;

UPDATE dg_academy_modules m
INNER JOIN dg_academy_course_modules cm ON cm.module_id = m.id
INNER JOIN dg_academy_courses c ON c.id = cm.course_id
SET m.department_id = c.department_id
WHERE m.department_id IS NULL AND c.department_id IS NOT NULL;

-- Module nicht mehr direkt an Kurs binden (Junction ist führend)
ALTER TABLE dg_academy_modules
    MODIFY course_id INT UNSIGNED NULL;

UPDATE dg_academy_modules SET course_id = NULL WHERE course_id IS NOT NULL;

ALTER TABLE dg_academy_courses
    ADD KEY idx_academy_course_department (department_id);
