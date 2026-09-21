<?php
declare(strict_types=1);

/**
 * Registriert Schulungsvideos Kassenbuch, OPOS, GuV, Steuerberater-Export, Statistik.
 *
 * php bin/academy-setup-buchhaltung-kacheln-course.php
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

/**
 * @param array{slug: string, title: string, description: string, dir: string, file: string, sort: int} $spec
 */
function ensureCourseWithModule(PDO $pdo, array $spec): void
{
    $dir = DG_ROOT . '/storage/media/training/' . $spec['dir'];
    $mp4 = $dir . '/' . $spec['file'] . '.mp4';
    if (!is_file($mp4)) {
        fwrite(STDERR, "Fehlt: {$mp4}\n");
        exit(1);
    }
    $metaPath = $dir . '/' . $spec['file'] . '.meta.json';
    $meta = is_file($metaPath) ? json_decode((string) file_get_contents($metaPath), true) : null;
    $sec = durationFromMeta(is_array($meta) ? $meta : null, 120);

    $moduleId = ensureModule(
        $pdo,
        $spec['title'],
        'media/training/' . $spec['dir'] . '/' . $spec['file'] . '.mp4',
        'media/training/' . $spec['dir'] . '/' . $spec['file'] . '.vtt',
        $sec,
        $spec['sort']
    );
    echo "Modul: {$spec['title']} ({$sec}s) id={$moduleId}\n";

    $course = AcademyRepository::findCourseBySlug($spec['slug']);
    if ($course === null) {
        $courseId = AcademyRepository::saveCourse([
            'title' => $spec['title'],
            'slug' => $spec['slug'],
            'description' => $spec['description'],
            'version' => '1.0',
            'min_tier' => AcademyTier::STARTER,
            'is_published' => 1,
        ]);
        echo "Kurs angelegt: {$spec['slug']} id={$courseId}\n";
    } else {
        $courseId = (int) $course['id'];
        $pdo->prepare(
            'UPDATE dg_academy_courses SET title = :t, description = :d, is_published = 1 WHERE id = :id'
        )->execute([
            't' => $spec['title'],
            'd' => $spec['description'],
            'id' => $courseId,
        ]);
        echo "Kurs aktualisiert: {$spec['slug']} id={$courseId}\n";
    }

    AcademyRepository::saveCourseModules($courseId, [$moduleId]);
}

$specs = [
    [
        'slug' => 'kassenbuch',
        'title' => 'Kassenbuch — Überblick',
        'description' => 'Bar-Ein- und Ausgänge, Zeitraum und Tagesabschluss.',
        'dir' => 'kassenbuch',
        'file' => 'kassenbuch-ueberblick',
        'sort' => 20,
    ],
    [
        'slug' => 'offene-posten',
        'title' => 'Offene Posten — Überblick',
        'description' => 'Forderungen und Verbindlichkeiten filtern und prüfen.',
        'dir' => 'opos',
        'file' => 'opos-ueberblick',
        'sort' => 21,
    ],
    [
        'slug' => 'bilanz-guv',
        'title' => 'Bilanz und GuV — Überblick',
        'description' => 'Gewinn- und Verlustrechnung sowie Bilanz aus dem Journal.',
        'dir' => 'guv',
        'file' => 'guv-ueberblick',
        'sort' => 22,
    ],
    [
        'slug' => 'steuerberater-export',
        'title' => 'Steuerberater-Export — Überblick',
        'description' => 'DATEV, Agenda, Addison und Komplett-Paket für die Kanzlei.',
        'dir' => 'steuerberater-export',
        'file' => 'steuerberater-export-ueberblick',
        'sort' => 23,
    ],
    [
        'slug' => 'website-statistik',
        'title' => 'Statistik — Überblick',
        'description' => 'Lokale Seitenaufrufe und Links zu Google Analytics.',
        'dir' => 'statistik',
        'file' => 'statistik-ueberblick',
        'sort' => 24,
    ],
];

foreach ($specs as $spec) {
    ensureCourseWithModule($pdo, $spec);
}

$sync = AcademyRepository::syncModuleMediaFromDisk(true);
echo "Sync: deaktiviert={$sync['deactivated']} dauer={$sync['duration_updated']}\n";
echo "Fertig.\n";
