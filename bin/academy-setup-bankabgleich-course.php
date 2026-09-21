<?php
declare(strict_types=1);

/**
 * Registriert Schulungsvideo Bankabgleich als Akademie-Kurs.
 *
 * php bin/academy-setup-bankabgleich-course.php
 */

if (!defined('DG_ROOT')) {
    define('DG_ROOT', dirname(__DIR__));
}
require_once DG_ROOT . '/src/autoload.php';

MigrationRunner::runPending();

$pdo = Database::pdo();

/** @return int */
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

$slug = 'bankabgleich-ueberblick';
$mp4 = DG_ROOT . '/storage/media/training/bankabgleich/' . $slug . '.mp4';
if (!is_file($mp4)) {
    fwrite(STDERR, "Fehlt: {$mp4}\n");
    exit(1);
}

$metaPath = DG_ROOT . '/storage/media/training/bankabgleich/' . $slug . '.meta.json';
$meta = is_file($metaPath) ? json_decode((string) file_get_contents($metaPath), true) : null;
$sec = durationFromMeta(is_array($meta) ? $meta : null, 150);

$id = ensureModule(
    $pdo,
    'Bankabgleich — Überblick',
    'media/training/bankabgleich/' . $slug . '.mp4',
    'media/training/bankabgleich/' . $slug . '.vtt',
    $sec,
    10
);
echo "Modul: Bankabgleich — Überblick ({$sec}s) id={$id}\n";

$courseSlug = 'bankabgleich';
$course = AcademyRepository::findCourseBySlug($courseSlug);
if ($course === null) {
    $courseId = AcademyRepository::saveCourse([
        'title' => 'Bankabgleich',
        'slug' => $courseSlug,
        'description' => 'CAMT.053 und MT940 importieren, offene Umsätze zuordnen, Geisterumsätze prüfen.',
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
        't' => 'Bankabgleich',
        'd' => 'CAMT.053 und MT940 importieren, offene Umsätze zuordnen, Geisterumsätze prüfen.',
        'id' => $courseId,
    ]);
    echo "Kurs aktualisiert: id={$courseId}\n";
}

AcademyRepository::saveCourseModules($courseId, [$id]);
$sync = AcademyRepository::syncModuleMediaFromDisk(true);
echo "Sync: deaktiviert={$sync['deactivated']} dauer={$sync['duration_updated']}\n";
echo "Katalog: /app?page=akademie&view=kurs&slug={$courseSlug}\n";
