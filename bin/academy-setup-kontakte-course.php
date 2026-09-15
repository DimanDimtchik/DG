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

$overviewMeta = json_decode(
    (string) file_get_contents(DG_ROOT . '/storage/media/training/kontakte/kontakte-ueberblick.meta.json'),
    true
) ?: [];
$fieldsMeta = json_decode(
    (string) file_get_contents(DG_ROOT . '/storage/media/training/kontakte/kontakte-felder.meta.json'),
    true
) ?: [];

$modOverview = ensureModule(
    $pdo,
    'Kontakte — Überblick',
    'media/training/kontakte/kontakte-ueberblick.mp4',
    'media/training/kontakte/kontakte-ueberblick.vtt',
    (int) round((float) ($overviewMeta['duration_sec'] ?? 72))
);
$modFields = ensureModule(
    $pdo,
    'Kontakte — Felder im Detail',
    'media/training/kontakte/kontakte-felder.mp4',
    'media/training/kontakte/kontakte-felder.vtt',
    (int) round((float) ($fieldsMeta['duration_sec'] ?? 240))
);

$slug = 'kontakte';
$course = AcademyRepository::findCourseBySlug($slug);
if ($course === null) {
    $courseId = AcademyRepository::saveCourse([
        'title' => 'Kontakte',
        'slug' => $slug,
        'description' => 'Kontakte im CRM: Überblick und Erklärung aller Formularfelder.',
        'version' => '1.0',
        'min_tier' => AcademyTier::STARTER,
        'is_published' => 1,
    ]);
    echo "Kurs angelegt: id={$courseId}\n";
} else {
    $courseId = (int) $course['id'];
    echo "Kurs existiert: id={$courseId}\n";
}

AcademyRepository::saveCourseModules($courseId, [$modOverview, $modFields]);
echo "Module: overview={$modOverview} fields={$modFields}\n";
echo "Katalog: /app?page=akademie&view=kurs&slug={$slug}\n";
