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
function ensureModule(PDO $pdo, string $title, string $videoRel, string $vttRel, int $durationSec, string $description = ''): int
{
    $desc = $description !== '' ? $description : $title;
    $existing = (int) ($pdo->query(
        'SELECT id FROM dg_academy_modules WHERE title = ' . $pdo->quote($title) . ' ORDER BY id DESC LIMIT 1'
    )->fetchColumn() ?: 0);
    if ($existing > 0) {
        $pdo->prepare(
            'UPDATE dg_academy_modules
             SET video_path = :vp, subtitle_vtt_path = :vt, duration_sec = :d, description = :desc, is_active = 1
             WHERE id = :id'
        )->execute([
            'vp' => $videoRel,
            'vt' => $vttRel,
            'd' => $durationSec,
            'desc' => $desc,
            'id' => $existing,
        ]);
        return $existing;
    }

    return AcademyRepository::saveModule([
        'title' => $title,
        'description' => $desc,
        'provider' => 'self',
        'video_path' => $videoRel,
        'subtitle_vtt_path' => $vttRel,
        'duration_sec' => $durationSec,
        'min_watch_percent' => 80,
        'is_active' => 1,
        'sort_order' => 0,
    ]);
}

/** Lesetext-Intro aus Locale-JSON (Fallback: Titel). */
function descriptionFromLocale(string $slug, string $fallback): string
{
    $path = DG_ROOT . '/docs/akademie/locales/de/' . $slug . '.json';
    if (!is_readable($path)) {
        return $fallback;
    }
    $data = json_decode((string) file_get_contents($path), true);
    if (!is_array($data)) {
        return $fallback;
    }
    $intro = trim((string) ($data['intro'] ?? ''));
    if ($intro !== '') {
        return $intro;
    }
    $segments = $data['segments'] ?? null;
    if (is_array($segments) && $segments !== []) {
        $first = $segments[0] ?? null;
        if (is_array($first)) {
            $n = trim((string) ($first['narration'] ?? ''));
            if ($n !== '') {
                return $n;
            }
        }
    }

    return $fallback;
}

/** @param array<string, mixed>|null $meta */
function durationFromMeta(?array $meta, int $fallback = 120): int
{
    return (int) round((float) ($meta['duration_sec'] ?? $fallback));
}

/** @return array<string, mixed>|null */
function loadMeta(string $slug, string $subdir = 'terminkalender'): ?array
{
    $path = DG_ROOT . '/storage/media/training/' . $subdir . '/' . $slug . '.meta.json';
    if (!is_file($path)) {
        return null;
    }
    $data = json_decode((string) file_get_contents($path), true);
    return is_array($data) ? $data : null;
}

$modules = [
    ['Terminkalender — Überblick', 'terminkalender', 'terminkalender-ueberblick', 90],
    ['Terminkalender — Neuer Termin', 'terminkalender', 'terminkalender-neuer-termin', 240],
    ['Terminkalender — Online-Buchung', 'terminkalender', 'terminkalender-online-buchung', 180],
    ['Einstellungen — Arbeitszeiten', 'einstellungen', 'einstellungen-arbeitszeiten', 150],
    ['Einstellungen — Kalender Design', 'einstellungen', 'einstellungen-kalender-design', 150],
    ['Einstellungen — Kalender-Bereiche', 'einstellungen', 'einstellungen-kalender-bereiche', 150],
    ['Einstellungen — Kalender-Mitglieder', 'einstellungen', 'einstellungen-kalender-mitglieder', 180],
];

$moduleIds = [];
foreach ($modules as [$title, $dir, $slug, $fallback]) {
    $meta = loadMeta($slug, $dir);
    $sec = durationFromMeta($meta, $fallback);
    $desc = descriptionFromLocale($slug, $title);
    $moduleIds[] = ensureModule(
        $pdo,
        $title,
        'media/training/' . $dir . '/' . $slug . '.mp4',
        'media/training/' . $dir . '/' . $slug . '.vtt',
        $sec,
        $desc
    );
    echo "Modul: {$title} ({$sec}s) id=" . end($moduleIds) . "\n";
}

$slug = 'terminkalender';
$course = AcademyRepository::findCourseBySlug($slug);
if ($course === null) {
    $courseId = AcademyRepository::saveCourse([
        'title' => 'Terminkalender',
        'slug' => $slug,
        'description' => 'Terminkalender: Übersicht, Termin, Arbeitszeiten, Design, Bereiche, Mitglieder, Online-Buchung.',
        'version' => '1.4',
        'min_tier' => AcademyTier::STARTER,
        'is_published' => 1,
    ]);
    echo "Kurs angelegt: id={$courseId}\n";
} else {
    $courseId = (int) $course['id'];
    $pdo->prepare(
        'UPDATE dg_academy_courses SET description = :d, version = :v WHERE id = :id'
    )->execute([
        'd' => 'Terminkalender: Übersicht, Termin, Arbeitszeiten, Design, Bereiche, Mitglieder, Online-Buchung.',
        'v' => '1.4',
        'id' => $courseId,
    ]);
    echo "Kurs aktualisiert: id={$courseId}\n";
}

AcademyRepository::saveCourseModules($courseId, $moduleIds);
echo 'Kurs-Module-IDs: ' . implode(', ', $moduleIds) . "\n";

// Endkunden-Video: für Website / Videobibliothek — nicht im Schulungskurs (Mitarbeiter-Video bleibt Modul 3).
$kundeSlug = 'terminkalender-online-kunde';
$kundeMeta = loadMeta($kundeSlug, 'terminkalender');
$kundeSec = durationFromMeta($kundeMeta, 116);
$kundeId = ensureModule(
    $pdo,
    'Online-Terminbuchung — Kundenansicht',
    'media/training/terminkalender/' . $kundeSlug . '.mp4',
    'media/training/terminkalender/' . $kundeSlug . '.vtt',
    $kundeSec
);
echo "Bibliothek (Website): Online-Terminbuchung — Kundenansicht id={$kundeId} ({$kundeSec}s)\n";

$sync = AcademyRepository::syncModuleMediaFromDisk(true);
echo "Sync: deaktiviert={$sync['deactivated']} dauer={$sync['duration_updated']}\n";
echo "Katalog: /app?page=akademie&view=kurs&slug={$slug}\n";
