<?php
declare(strict_types=1);

/** Videos für den Website-Editor (Kern-Katalog, Akademie-Bibliothek, Mediathek). */
final class WebsiteVideoLibrary
{
    /**
     * @return list<array{id: string, title: string, url: string, source: string, duration_sec: int|null, playable: bool}>
     */
    public static function listForPicker(): array
    {
        $items = [];
        $seenPaths = [];

        // 1) Kern-Katalog (auch ohne installierte MP4 — für Auswahl/Planung)
        foreach (AcademyTrainingCatalog::all() as $entry) {
            $path = (string) ($entry['video_path'] ?? '');
            if ($path === '') {
                continue;
            }
            $playable = AcademyTrainingCatalog::mp4Exists($path);
            // URL immer setzen (relativ), damit Seitenplanung auch ohne MP4-Sync möglich ist
            $url = '/' . ltrim($path, '/');
            $seenPaths[$path] = true;
            $items[] = [
                'id' => 'kern-' . (string) ($entry['key'] ?? md5($path)),
                'title' => (string) ($entry['title'] ?? 'Video'),
                'url' => $url,
                'source' => $playable ? 'kern' : 'kern-missing',
                'duration_sec' => isset($entry['duration_sec']) ? (int) $entry['duration_sec'] : null,
                'playable' => $playable,
            ];
        }

        if (Database::isConfigured()) {
            // Module aus Katalog in DB nachziehen (Titel-Liste in Akademie)
            try {
                AcademyTrainingCatalog::ensureAcademyModules();
            } catch (Throwable) {
                // Picker soll nicht scheitern
            }

            foreach (AcademyRepository::libraryVideos(true, false) as $module) {
                $path = trim((string) ($module['video_path'] ?? ''));
                if ($path !== '' && isset($seenPaths[$path])) {
                    continue;
                }
                $url = AcademyRepository::publicVideoUrl($module);
                $playable = $url !== null;
                if ($path !== '') {
                    $seenPaths[$path] = true;
                }
                $items[] = [
                    'id' => 'academy-' . (int) ($module['id'] ?? 0),
                    'title' => (string) ($module['title'] ?? 'Video'),
                    'url' => $url ?? '',
                    'source' => $playable ? 'academy' : 'academy-missing',
                    'duration_sec' => isset($module['duration_sec']) ? (int) $module['duration_sec'] : null,
                    'playable' => $playable,
                ];
            }

            foreach (MediaRepository::listWithUsage() as $row) {
                $mime = (string) ($row['mime_type'] ?? '');
                if ($mime !== '' && !str_starts_with($mime, 'video/')) {
                    continue;
                }
                $ext = strtolower((string) ($row['extension'] ?? ''));
                if ($mime === '' && $ext !== 'mp4' && $ext !== 'webm') {
                    continue;
                }
                $mediaId = (string) ($row['media_id'] ?? '');
                if ($mediaId === '') {
                    continue;
                }
                $items[] = [
                    'id' => 'media-' . $mediaId,
                    'title' => (string) (($row['title'] ?? '') !== '' ? $row['title'] : ($row['original_name'] ?? 'Video')),
                    'url' => MediaStorage::publicUrl($mediaId),
                    'source' => 'mediathek',
                    'duration_sec' => null,
                    'playable' => true,
                ];
            }
        }

        usort($items, static function (array $a, array $b): int {
            return strcasecmp((string) $a['title'], (string) $b['title']);
        });

        return $items;
    }

    /** Normalisiert Video-URLs für die öffentliche Website. */
    public static function resolvePublicUrl(string $url): string
    {
        $url = trim($url);
        if ($url === '') {
            return '';
        }

        if (preg_match('#^media/training/#', $url) === 1) {
            return AcademyRepository::publicVideoUrlFromRelative($url) ?? $url;
        }

        if (str_starts_with($url, '/media/training/')) {
            $rel = ltrim($url, '/');
            $resolved = AcademyRepository::publicVideoUrlFromRelative($rel);

            return $resolved ?? $url;
        }

        return $url;
    }
}
