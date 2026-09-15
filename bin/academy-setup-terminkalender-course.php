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
    $path = DG_ROOT . '/storage/media/training/terminkalender/' . $slug . '.meta.json';
    if (!is_file($path)) {
        return null;
    }
    $data = json_decode((string) file_get_contents($path), true);
    return is_array($data) ? $data : null;
}

$modules = [
    ['Terminkalender — Überblick', 'terminkalender-ueberblick', 90],
    ['Terminkalender — Neuer Termin', 'terminkalender-neuer-termin', 240],
    ['Terminkalender — Online-Buchung', 'terminkalender-online-buchung', 180],
];

$moduleIds = [];
foreach ($modules as [$title, $slug, $fallback]) {
    $meta = loadMeta($slug);
    $sec = durationFromMeta($meta, $fallback);
    $moduleIds[] = ensureModule(
        $pdo,
        $title,
        'media/training/terminkalender/' . $slug . '.mp4',
        'media/training/terminkalender/' . $slug . '.vtt',
        $sec
    );
    echo "Modul: {$title} ({$sec}s) id=" . end($moduleIds) . "\n";
}

$slug = 'terminkalender';
$course = AcademyRepository::findCourseBySlug($slug);
if ($course === null) {
    $courseId = AcademyRepository::saveCourse([
        'title' => 'Terminkalender',
        'slug' => $slug,
        'description' => 'Terminkalender: Übersicht, Neuer Termin, Online-Buchung für Kunden inkl. Bestätigungsmail.',
        'version' => '1.1',
        'min_tier' => AcademyTier::STARTER,
        'is_published' => 1,
    ]);
    echo "Kurs angelegt: id={$courseId}\n";
} else {
    $courseId = (int) $course['id'];
    $pdo->prepare(
        'UPDATE dg_academy_courses SET description = :d, version = :v WHERE id = :id'
    )->execute([
        'd' => 'Terminkalender: Übersicht, Neuer Termin, Online-Buchung für Kunden inkl. Bestätigungsmail.',
        'v' => '1.1',
        'id' => $courseId,
    ]);
    echo "Kurs aktualisiert: id={$courseId}\n";
}

AcademyRepository::saveCourseModules($courseId, $moduleIds);
echo 'Module-IDs: ' . implode(', ', $moduleIds) . "\n";

$sync = AcademyRepository::syncModuleMediaFromDisk(true);
echo "Sync: deaktiviert={$sync['deactivated']} dauer={$sync['duration_updated']}\n";
echo "Katalog: /app?page=akademie&view=kurs&slug={$slug}\n";
