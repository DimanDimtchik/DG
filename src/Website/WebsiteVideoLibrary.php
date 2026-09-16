<?php
declare(strict_types=1);

/** Videos für den Website-Editor (Akademie-Bibliothek + MP4 in der Mediathek). */
final class WebsiteVideoLibrary
{
    /**
     * @return list<array{id: string, title: string, url: string, source: string, duration_sec: int|null}>
     */
    public static function listForPicker(): array
    {
        if (!Database::isConfigured()) {
            return [];
        }

        $items = [];

        foreach (AcademyRepository::libraryVideos(true, true) as $module) {
            $url = AcademyRepository::publicVideoUrl($module);
            if ($url === null) {
                continue;
            }
            $items[] = [
                'id' => 'academy-' . (int) ($module['id'] ?? 0),
                'title' => (string) ($module['title'] ?? 'Video'),
                'url' => $url,
                'source' => 'academy',
                'duration_sec' => isset($module['duration_sec']) ? (int) $module['duration_sec'] : null,
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
            ];
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
