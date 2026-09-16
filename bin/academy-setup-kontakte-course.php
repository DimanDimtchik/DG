#!/usr/bin/env php
<?php
declare(strict_types=1);

if (!defined('DG_ROOT')) {
    define('DG_ROOT', dirname(__DIR__));
}
require_once DG_ROOT . '/src/autoload.php';

MigrationRunner::runPending();

$pdo = Database::pdo();

/** @return int module id */
function ensureModule(PDO $pdo, string $title, string $videoRel, string $vttRel, int $durationSec): int
{
    $existing = (int) ($pdo->query(
        'SELECT id FROM dg_academy_modules WHERE title = ' . $pdo->quote($title) . ' ORDER BY id DESC LIMIT 1'
    )->fetchColumn() ?: 0);
    if ($existing > 0) {
        $pdo->prepare(
            'UPDATE dg_academy_modules SET video_path = :vp, subtitle_vtt_path = :vt, duration_sec = :d, is_active = 1 WHERE id = :id'
        )->execute(['vp' => $videoRel, 'vt' => $vttRel, 'd' => $durationSec, 'id' => $existing]);
        return $existing;
    }

    return AcademyRepository::saveModule([
        'title' => $title,
        'description' => $title,
        'provider' => 'self',
        'video_path' => $videoRel,
        'subtitle_vtt_path' => $vttRel,
        'duration_sec' => $durationSec,
        'min_watch_percent' => 80,
        'is_active' => 1,
        'sort_order' => 0,
    ]);
}

/** @param array<string, mixed>|null $meta */
function durationFromMeta(?array $meta, int $fallback = 120): int
{
    return (int) round((float) ($meta['duration_sec'] ?? $fallback));
}

/** @return array<string, mixed>|null */
function loadMeta(string $slug): ?array
{
    $path = DG_ROOT . '/storage/media/training/kontakte/' . $slug . '.meta.json';
    if (!is_file($path)) {
        return null;
    }
    $data = json_decode((string) file_get_contents($path), true);
    return is_array($data) ? $data : null;
}

// Altes Sammel-Modul deaktivieren
$pdo->exec(
    "UPDATE dg_academy_modules SET is_active = 0 WHERE title = 'Kontakte — Felder im Detail'"
);

$modules = [
    ['Kontakte — Überblick', 'kontakte-ueberblick', 72],
    ['Kontakte — Felder: Stamm', 'kontakte-felder-stamm', 300],
    ['Kontakte — Felder: Kunde / Lieferant', 'kontakte-felder-kunde-lieferant', 180],
    ['Kontakte — Felder: Kommunikation', 'kontakte-felder-kommunikation', 150],
    ['Kontakte — Felder: Adresse', 'kontakte-felder-adresse', 120],
    ['Kontakte — Felder: Bankverbindung', 'kontakte-felder-bank', 180],
    ['Kontakte — Felder: Soziale Medien', 'kontakte-felder-social', 150],
    ['Kontakte — Felder: Mitarbeiterdaten', 'kontakte-felder-mitarbeiter', 600],
];

$moduleIds = [];
foreach ($modules as [$title, $slug, $fallback]) {
    $meta = loadMeta($slug);
    $sec = durationFromMeta($meta, $fallback);
    $moduleIds[] = ensureModule(
        $pdo,
        $title,
        'media/training/kontakte/' . $slug . '.mp4',
        'media/training/kontakte/' . $slug . '.vtt',
        $sec
    );
    echo "Modul: {$title} ({$sec}s) id=" . end($moduleIds) . "\n";
}

$slug = 'kontakte';
$course = AcademyRepository::findCourseBySlug($slug);
if ($course === null) {
    $courseId = AcademyRepository::saveCourse([
        'title' => 'Kontakte',
        'slug' => $slug,
        'description' => 'Kontakte im CRM: Überblick und Felder je Formular-Abschnitt.',
        'version' => '2.0',
        'min_tier' => AcademyTier::STARTER,
        'is_published' => 1,
    ]);
    echo "Kurs angelegt: id={$courseId}\n";
} else {
    $courseId = (int) $course['id'];
    $pdo->prepare(
        'UPDATE dg_academy_courses SET description = :d, version = :v WHERE id = :id'
    )->execute([
        'd' => 'Kontakte im CRM: Überblick und Felder je Formular-Abschnitt.',
        'v' => '2.0',
        'id' => $courseId,
    ]);
    echo "Kurs aktualisiert: id={$courseId}\n";
}

AcademyRepository::saveCourseModules($courseId, $moduleIds);
echo 'Module-IDs: ' . implode(', ', $moduleIds) . "\n";

$sync = AcademyRepository::syncModuleMediaFromDisk(true);
echo "Sync: deaktiviert={$sync['deactivated']} dauer={$sync['duration_updated']}\n";
echo "Katalog: /app?page=akademie&view=kurs&slug={$slug}\n";
