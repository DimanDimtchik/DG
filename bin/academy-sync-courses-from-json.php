#!/usr/bin/env php
<?php
declare(strict_types=1);

/**
 * Synchronisiert Akademie-Kurse (+ Module per video_path) aus einem JSON-Export.
 *
 * Export (Quelle):
 *   php bin/academy-sync-courses-from-json.php --export=/tmp/academy-courses.json
 *
 * Import (Ziel):
 *   php bin/academy-sync-courses-from-json.php --import=/tmp/academy-courses.json [--unpublish-extra=1]
 */

if (!defined('DG_ROOT')) {
    define('DG_ROOT', dirname(__DIR__));
}
require_once DG_ROOT . '/src/autoload.php';

MigrationRunner::runPending();

if (!Database::isConfigured()) {
    fwrite(STDERR, "Keine DB-Konfiguration.\n");
    exit(1);
}

$exportPath = '';
$importPath = '';
$unpublishExtra = false;
foreach ($argv as $arg) {
    if (str_starts_with($arg, '--export=')) {
        $exportPath = substr($arg, 9);
    } elseif (str_starts_with($arg, '--import=')) {
        $importPath = substr($arg, 9);
    } elseif ($arg === '--unpublish-extra=1' || $arg === '--unpublish-extra') {
        $unpublishExtra = true;
    }
}

$pdo = Database::pdo();

if ($exportPath !== '') {
    $rows = $pdo->query(
        "SELECT c.slug AS course_slug, c.title AS course_title, c.description, c.version, c.is_published, c.min_tier,
                cm.sort_order,
                m.title AS module_title, m.description AS module_description,
                m.video_path, m.subtitle_vtt_path, m.duration_sec, m.provider,
                m.min_watch_percent, m.is_active, m.max_playback_rate, m.target_page
         FROM dg_academy_courses c
         LEFT JOIN dg_academy_course_modules cm ON cm.course_id = c.id
         LEFT JOIN dg_academy_modules m ON m.id = cm.module_id
         ORDER BY c.slug, cm.sort_order, m.id"
    )->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $courses = [];
    foreach ($rows as $row) {
        $slug = (string) ($row['course_slug'] ?? '');
        if ($slug === '') {
            continue;
        }
        if (!isset($courses[$slug])) {
            $courses[$slug] = [
                'slug' => $slug,
                'title' => (string) ($row['course_title'] ?? $slug),
                'description' => (string) ($row['description'] ?? ''),
                'version' => (string) ($row['version'] ?? '1.0'),
                'is_published' => (int) ($row['is_published'] ?? 0),
                'min_tier' => (string) ($row['min_tier'] ?? AcademyTier::STARTER),
                'modules' => [],
            ];
        }
        $video = trim((string) ($row['video_path'] ?? ''));
        if ($video === '') {
            continue;
        }
        $courses[$slug]['modules'][] = [
            'sort_order' => (int) ($row['sort_order'] ?? 0),
            'title' => (string) ($row['module_title'] ?? ''),
            'description' => (string) ($row['module_description'] ?? ''),
            'video_path' => $video,
            'subtitle_vtt_path' => (string) ($row['subtitle_vtt_path'] ?? ''),
            'duration_sec' => (int) ($row['duration_sec'] ?? 0),
            'provider' => (string) ($row['provider'] ?? 'self'),
            'min_watch_percent' => (int) ($row['min_watch_percent'] ?? 80),
            'is_active' => (int) ($row['is_active'] ?? 1),
            'max_playback_rate' => (string) ($row['max_playback_rate'] ?? '2'),
            'target_page' => (string) ($row['target_page'] ?? ''),
        ];
    }

    $payload = [
        'exported_at' => date('c'),
        'source' => basename(DG_ROOT),
        'courses' => array_values($courses),
    ];
    file_put_contents($exportPath, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    echo "Export: {$exportPath} (" . count($courses) . " Kurse)\n";
    exit(0);
}

if ($importPath === '' || !is_readable($importPath)) {
    fwrite(STDERR, "Usage:\n  --export=path.json\n  --import=path.json [--unpublish-extra]\n");
    exit(1);
}

$data = json_decode((string) file_get_contents($importPath), true);
if (!is_array($data) || !isset($data['courses']) || !is_array($data['courses'])) {
    fwrite(STDERR, "Ungültiges Import-JSON.\n");
    exit(1);
}

/** @return int */
function ensureModuleByVideo(PDO $pdo, array $mod): int
{
    $video = trim((string) ($mod['video_path'] ?? ''));
    if ($video === '') {
        throw new InvalidArgumentException('video_path fehlt');
    }
    $stmt = $pdo->prepare('SELECT id FROM dg_academy_modules WHERE video_path = :vp ORDER BY id DESC LIMIT 1');
    $stmt->execute(['vp' => $video]);
    $existing = (int) ($stmt->fetchColumn() ?: 0);

    $payload = [
        'id' => $existing,
        'title' => (string) ($mod['title'] ?? basename($video)),
        'description' => (string) ($mod['description'] ?? ''),
        'provider' => (string) ($mod['provider'] ?? 'self'),
        'video_path' => $video,
        'subtitle_vtt_path' => (string) ($mod['subtitle_vtt_path'] ?? ''),
        'duration_sec' => (int) ($mod['duration_sec'] ?? 0),
        'min_watch_percent' => (int) ($mod['min_watch_percent'] ?? 80),
        'is_active' => (int) ($mod['is_active'] ?? 1),
        'max_playback_rate' => (string) ($mod['max_playback_rate'] ?? '2'),
        'target_page' => (string) ($mod['target_page'] ?? ''),
        'sort_order' => 0,
    ];

    return AcademyRepository::saveModule($payload);
}

$sourceSlugs = [];
foreach ($data['courses'] as $course) {
    if (!is_array($course)) {
        continue;
    }
    $slug = trim((string) ($course['slug'] ?? ''));
    if ($slug === '') {
        continue;
    }
    $sourceSlugs[$slug] = true;

    $moduleIds = [];
    foreach (($course['modules'] ?? []) as $mod) {
        if (!is_array($mod)) {
            continue;
        }
        $moduleIds[] = ensureModuleByVideo($pdo, $mod);
    }

    $existing = AcademyRepository::findCourseBySlug($slug);
    $courseId = AcademyRepository::saveCourse([
        'id' => $existing !== null ? (int) $existing['id'] : 0,
        'title' => (string) ($course['title'] ?? $slug),
        'slug' => $slug,
        'description' => (string) ($course['description'] ?? ''),
        'version' => (string) ($course['version'] ?? '1.0'),
        'min_tier' => (string) ($course['min_tier'] ?? AcademyTier::STARTER),
        'is_published' => (int) ($course['is_published'] ?? 0),
    ]);
    AcademyRepository::saveCourseModules($courseId, $moduleIds);
    echo "Kurs {$slug}: id={$courseId}, Module=" . count($moduleIds) . "\n";
}

if ($unpublishExtra) {
    $all = $pdo->query('SELECT id, slug, title FROM dg_academy_courses')->fetchAll(PDO::FETCH_ASSOC) ?: [];
    $upd = $pdo->prepare('UPDATE dg_academy_courses SET is_published = 0 WHERE id = :id');
    foreach ($all as $row) {
        $slug = (string) ($row['slug'] ?? '');
        if ($slug !== '' && !isset($sourceSlugs[$slug]) && (int) ($row['id'] ?? 0) > 0) {
            $upd->execute(['id' => (int) $row['id']]);
            echo "Unpublished extra: {$slug}\n";
        }
    }
}

$sync = AcademyRepository::syncModuleMediaFromDisk(true);
echo "Media-Sync: deaktiviert={$sync['deactivated']} dauer={$sync['duration_updated']}\n";
echo "Fertig.\n";
