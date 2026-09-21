<?php
declare(strict_types=1);

/**
 * Registriert Akademie-Schulungsvideos als Kurs „Akademie“.
 *
 * php bin/academy-setup-akademie-course.php
 */

if (!defined('DG_ROOT')) {
    define('DG_ROOT', dirname(__DIR__));
}
require_once DG_ROOT . '/src/autoload.php';

MigrationRunner::runPending();

$pdo = Database::pdo();

/** @return int module id */
function ensureModule(PDO $pdo, string $title, string $videoRel, string $vttRel, int $durationSec, int $sortOrder = 0): int
{
    $existing = (int) ($pdo->query(
        'SELECT id FROM dg_academy_modules WHERE title = ' . $pdo->quote($title) . ' ORDER BY id DESC LIMIT 1'
    )->fetchColumn() ?: 0);
    if ($existing > 0) {
        $pdo->prepare(
            'UPDATE dg_academy_modules SET video_path = :vp, subtitle_vtt_path = :vt, duration_sec = :d, is_active = 1, sort_order = :s WHERE id = :id'
        )->execute(['vp' => $videoRel, 'vt' => $vttRel, 'd' => $durationSec, 's' => $sortOrder, 'id' => $existing]);

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
        'sort_order' => $sortOrder,
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
    $path = DG_ROOT . '/storage/media/training/akademie/' . $slug . '.meta.json';
    if (!is_file($path)) {
        return null;
    }
    $data = json_decode((string) file_get_contents($path), true);

    return is_array($data) ? $data : null;
}

$modules = [
    [
        'slug' => 'akademie-ueberblick',
        'title' => 'Akademie — Überblick',
        'sort' => 10,
    ],
    [
        'slug' => 'akademie-tabs',
        'title' => 'Akademie — Die Reiter',
        'sort' => 15,
    ],
    [
        'slug' => 'akademie-kurs-lernen',
        'title' => 'Akademie — Kurs lernen',
        'sort' => 20,
    ],
];

$moduleIds = [];
foreach ($modules as $mod) {
    $slug = $mod['slug'];
    $mp4 = DG_ROOT . '/storage/media/training/akademie/' . $slug . '.mp4';
    if (!is_file($mp4)) {
        fwrite(STDERR, "Fehlt: {$mp4}\n");
        continue;
    }
    $meta = loadMeta($slug);
    $sec = durationFromMeta($meta, 150);
    $id = ensureModule(
        $pdo,
        $mod['title'],
        'media/training/akademie/' . $slug . '.mp4',
        'media/training/akademie/' . $slug . '.vtt',
        $sec,
        (int) $mod['sort']
    );
    $moduleIds[] = $id;
    echo "Modul: {$mod['title']} ({$sec}s) id={$id}\n";
}

if ($moduleIds === []) {
    fwrite(STDERR, "Keine Module registriert — MP4 fehlen.\n");
    exit(1);
}

$courseSlug = 'akademie';
$course = AcademyRepository::findCourseBySlug($courseSlug);
if ($course === null) {
    $courseId = AcademyRepository::saveCourse([
        'title' => 'Akademie',
        'slug' => $courseSlug,
        'description' => 'Aufbau der Akademie und wie Sie Kurse starten, Regeln bestätigen und Videos lernen.',
        'version' => '1.0',
        'min_tier' => AcademyTier::STARTER,
        'is_published' => 1,
    ]);
    echo "Kurs angelegt: id={$courseId}\n";
} else {
    $courseId = (int) $course['id'];
    $pdo->prepare(
        'UPDATE dg_academy_courses SET title = :t, description = :d, is_published = 1 WHERE id = :id'
    )->execute([
        't' => 'Akademie',
        'd' => 'Aufbau der Akademie und wie Sie Kurse starten, Regeln bestätigen und Videos lernen.',
        'id' => $courseId,
    ]);
    echo "Kurs aktualisiert: id={$courseId}\n";
}

AcademyRepository::saveCourseModules($courseId, $moduleIds);

$sync = AcademyRepository::syncModuleMediaFromDisk(true);
echo "Sync: deaktiviert={$sync['deactivated']} dauer={$sync['duration_updated']}\n";
echo "Katalog: /app?page=akademie&view=kurs&slug={$courseSlug}\n";
