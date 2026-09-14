<?php
declare(strict_types=1);

/**
 * Video-Upload für die Akademie-Bibliothek (storage/media/training/{department_id}/).
 */
final class AcademyVideoService
{
    /** Max. Videogröße in Bytes (500 MiB). */
    public const MAX_VIDEO_BYTES = 524_288_000;

    /** Max. Untertitelgröße in Bytes (2 MiB). */
    public const MAX_VTT_BYTES = 2_097_152;

    /**
     * Speichert Metadaten + optional hochgeladene Dateien als Bibliotheks-Modul.
     *
     * @param array<string, mixed> $post
     * @param array<string, mixed> $files $_FILES
     */
    public static function saveFromUpload(array $post, array $files): int
    {
        $departmentId = trim((string) ($post['department_id'] ?? ''));
        if ($departmentId === '' || !DepartmentRepository::exists($departmentId)) {
            throw new InvalidArgumentException('Bitte gültige Abteilung wählen.');
        }

        $title = trim((string) ($post['title'] ?? ''));
        if ($title === '') {
            throw new InvalidArgumentException('Titel ist Pflicht.');
        }

        $moduleId = (int) ($post['module_id'] ?? 0);
        $existing = $moduleId > 0 ? AcademyRepository::findModule($moduleId) : null;
        if ($moduleId > 0 && $existing === null) {
            throw new InvalidArgumentException('Video nicht gefunden.');
        }

        $videoPath = trim((string) ($existing['video_path'] ?? ''));
        $vttPath = trim((string) ($existing['subtitle_vtt_path'] ?? ''));

        $videoFile = $files['video_file'] ?? null;
        if (is_array($videoFile) && (int) ($videoFile['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            $videoPath = self::storeVideo($departmentId, $videoFile, $title);
        } elseif ($moduleId < 1) {
            throw new InvalidArgumentException('Bitte MP4-Video hochladen.');
        }

        $vttFile = $files['vtt_file'] ?? null;
        if (is_array($vttFile) && (int) ($vttFile['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            $vttPath = self::storeVtt($departmentId, $vttFile, $title);
        }

        return AcademyRepository::saveModule([
            'id' => $moduleId,
            'department_id' => $departmentId,
            'title' => $title,
            'description' => trim((string) ($post['description'] ?? '')),
            'video_path' => $videoPath,
            'subtitle_vtt_path' => $vttPath,
            'duration_sec' => max(1, (int) ($post['duration_sec'] ?? 180)),
            'min_watch_percent' => max(1, min(100, (int) ($post['min_watch_percent'] ?? 90))),
            'is_active' => !empty($post['is_active']) || $moduleId < 1,
        ]);
    }

    /**
     * @param array<string, mixed> $file
     */
    public static function storeVideo(string $departmentId, array $file, string $titleHint): string
    {
        self::assertUploadOk($file, self::MAX_VIDEO_BYTES, 'Video');

        $original = (string) ($file['name'] ?? 'video.mp4');
        $ext = strtolower(pathinfo($original, PATHINFO_EXTENSION));
        if ($ext !== 'mp4') {
            throw new InvalidArgumentException('Nur MP4-Videos erlaubt.');
        }

        $mime = (string) ($file['type'] ?? '');
        if ($mime !== '' && !str_starts_with($mime, 'video/') && $mime !== 'application/octet-stream') {
            throw new InvalidArgumentException('Ungültiger Video-MIME-Typ.');
        }

        $dir = self::ensureDepartmentDir($departmentId);
        $base = self::safeBaseName($titleHint !== '' ? $titleHint : pathinfo($original, PATHINFO_FILENAME));
        $stored = $base . '-' . date('YmdHis') . '.mp4';
        $target = $dir . '/' . $stored;

        if (!move_uploaded_file((string) ($file['tmp_name'] ?? ''), $target)) {
            throw new RuntimeException('Video konnte nicht gespeichert werden.');
        }

        @chmod($target, 0644);

        return 'media/training/' . self::safeDepartmentSegment($departmentId) . '/' . $stored;
    }

    /**
     * @param array<string, mixed> $file
     */
    public static function storeVtt(string $departmentId, array $file, string $titleHint): string
    {
        self::assertUploadOk($file, self::MAX_VTT_BYTES, 'Untertitel');

        $original = (string) ($file['name'] ?? 'subtitles.vtt');
        $ext = strtolower(pathinfo($original, PATHINFO_EXTENSION));
        if ($ext !== 'vtt') {
            throw new InvalidArgumentException('Untertitel nur als .vtt erlaubt.');
        }

        $dir = self::ensureDepartmentDir($departmentId);
        $base = self::safeBaseName($titleHint !== '' ? $titleHint : pathinfo($original, PATHINFO_FILENAME));
        $stored = $base . '-' . date('YmdHis') . '.vtt';
        $target = $dir . '/' . $stored;

        if (!move_uploaded_file((string) ($file['tmp_name'] ?? ''), $target)) {
            throw new RuntimeException('Untertitel konnten nicht gespeichert werden.');
        }

        @chmod($target, 0644);

        return 'media/training/' . self::safeDepartmentSegment($departmentId) . '/' . $stored;
    }

    public static function ensureDepartmentDir(string $departmentId): string
    {
        $segment = self::safeDepartmentSegment($departmentId);
        $dir = DG_ROOT . '/storage/media/training/' . $segment;
        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            throw new RuntimeException('Trainingsverzeichnis konnte nicht erstellt werden.');
        }

        return $dir;
    }

    private static function safeDepartmentSegment(string $departmentId): string
    {
        $segment = preg_replace('/[^a-zA-Z0-9_-]/', '', $departmentId) ?? '';
        if ($segment === '') {
            throw new InvalidArgumentException('Ungültige Abteilungs-ID.');
        }

        return $segment;
    }

    private static function safeBaseName(string $name): string
    {
        $slug = strtolower(trim($name));
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug) ?? '';
        $slug = trim($slug, '-');

        return $slug !== '' ? $slug : 'video';
    }

    /**
     * @param array<string, mixed> $file
     */
    private static function assertUploadOk(array $file, int $maxBytes, string $label): void
    {
        $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($error === UPLOAD_ERR_NO_FILE) {
            throw new InvalidArgumentException($label . ': keine Datei hochgeladen.');
        }
        if ($error !== UPLOAD_ERR_OK) {
            throw new InvalidArgumentException($label . ': Upload fehlgeschlagen (Code ' . $error . ').');
        }

        $size = (int) ($file['size'] ?? 0);
        if ($size <= 0 || $size > $maxBytes) {
            $maxMb = (int) floor($maxBytes / 1_048_576);
            throw new InvalidArgumentException($label . ' zu groß (max. ' . $maxMb . ' MB).');
        }
    }
}
