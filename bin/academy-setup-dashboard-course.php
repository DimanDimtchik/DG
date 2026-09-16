#!/usr/bin/env php
<?php
declare(strict_types=1);

if (!defined('DG_ROOT')) {
    define('DG_ROOT', dirname(__DIR__));
}
require_once DG_ROOT . '/src/autoload.php';

MigrationRunner::runPending();

$slug = 'crm-dashboard';
$moduleTitle = 'Dashboard — Kurzüberblick aller Kacheln';

$course = AcademyRepository::findCourseBySlug($slug);
if ($course === null) {
    $courseId = AcademyRepository::saveCourse([
        'title' => 'CRM-Einstieg — Dashboard',
        'slug' => $slug,
        'description' => 'Kurzer Überblick über alle Dashboard-Kacheln. Das Video zeigt immer den vollständigen Inhalt — unabhängig davon, welche Kacheln Sie im CRM sehen.',
        'version' => '1.0',
        'min_tier' => AcademyTier::STARTER,
        'is_published' => 1,
    ]);
    echo "Kurs angelegt: id={$courseId}\n";
} else {
    $courseId = (int) $course['id'];
    echo "Kurs existiert: id={$courseId}\n";
}

$pdo = Database::pdo();
$moduleId = (int) ($pdo->query(
    "SELECT id FROM dg_academy_modules WHERE title = " . $pdo->quote($moduleTitle) . " ORDER BY id DESC LIMIT 1"
)->fetchColumn() ?: 0);

if ($moduleId < 1) {
    $moduleId = (int) ($pdo->query(
        "SELECT id FROM dg_academy_modules WHERE video_path LIKE '%dashboard-ueberblick%' ORDER BY id DESC LIMIT 1"
    )->fetchColumn() ?: 0);
}

if ($moduleId < 1) {
    fwrite(STDERR, "Dashboard-Video nicht in Bibliothek gefunden. Bitte zuerst erzeugen/hochladen.\n");
    exit(1);
}

AcademyRepository::saveCourseModules($courseId, [$moduleId]);
echo "Video module_id={$moduleId} dem Kurs zugeordnet.\n";
echo "Ansehen: /app?page=akademie&view=kurs&slug={$slug}\n";
echo "Admin-Vorschau: /app?page=akademie&view=video-vorschau&module_id={$moduleId}\n";
