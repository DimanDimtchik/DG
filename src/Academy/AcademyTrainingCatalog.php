<?php
declare(strict_types=1);

/**
 * Kern-Katalog der Schulungsvideos (aus Meta-Dateien, im Code versioniert).
 * Unabhängig davon, ob MP4 auf der Instanz liegen oder DB-Module existieren.
 */
final class AcademyTrainingCatalog
{
    /**
     * @return list<array{
     *   key: string,
     *   folder: string,
     *   slug: string,
     *   title: string,
     *   duration_sec: int,
     *   video_path: string,
     *   subtitle_vtt_path: string,
     *   locale: string,
     *   voice: string
     * }>
     */
    public static function all(): array
    {
        $path = DG_ROOT . '/src/Academy/data/training-catalog.php';
        if (!is_readable($path)) {
            return [];
        }
        $data = require $path;

        return is_array($data) ? array_values($data) : [];
    }

    public static function mp4Exists(string $videoPath): bool
    {
        $rel = ltrim(trim($videoPath), '/');
        if ($rel === '') {
            return false;
        }

        return is_file(DG_ROOT . '/storage/' . $rel);
    }

    /**
     * Legt fehlende Akademie-Module aus dem Kern-Katalog an (ohne Kurs-Zwang).
     * Bereits vorhandene Titel werden aktualisiert (Pfad/Dauer), MP4 muss nicht existieren.
     *
     * @return array{created: int, updated: int}
     */
    public static function ensureAcademyModules(): array
    {
        if (!Database::isConfigured()) {
            return ['created' => 0, 'updated' => 0];
        }

        $created = 0;
        $updated = 0;
        $pdo = Database::pdo();

        foreach (self::all() as $item) {
            $title = trim((string) ($item['title'] ?? ''));
            $videoPath = trim((string) ($item['video_path'] ?? ''));
            if ($title === '' || $videoPath === '') {
                continue;
            }

            $duration = max(0, (int) ($item['duration_sec'] ?? 0));
            $vtt = trim((string) ($item['subtitle_vtt_path'] ?? ''));
            $existingId = (int) ($pdo->query(
                'SELECT id FROM dg_academy_modules WHERE title = ' . $pdo->quote($title) . ' ORDER BY id DESC LIMIT 1'
            )->fetchColumn() ?: 0);

            if ($existingId > 0) {
                $pdo->prepare(
                    'UPDATE dg_academy_modules
                     SET video_path = :vp, subtitle_vtt_path = :vt, duration_sec = :d, provider = :provider, is_active = 1
                     WHERE id = :id'
                )->execute([
                    'vp' => $videoPath,
                    'vt' => $vtt,
                    'd' => $duration > 0 ? $duration : 60,
                    'provider' => 'self',
                    'id' => $existingId,
                ]);
                ++$updated;
                continue;
            }

            AcademyRepository::saveModule([
                'title' => $title,
                'description' => $title,
                'provider' => 'self',
                'video_path' => $videoPath,
                'subtitle_vtt_path' => $vtt,
                'duration_sec' => $duration > 0 ? $duration : 60,
                'min_watch_percent' => 80,
                'is_active' => 1,
                'sort_order' => 0,
            ]);
            ++$created;
        }

        return ['created' => $created, 'updated' => $updated];
    }
}
